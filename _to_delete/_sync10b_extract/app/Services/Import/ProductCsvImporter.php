<?php

namespace App\Services\Import;

use App\Models\Product;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Imports a WooCommerce product export CSV directly into the local
 * `products` table — no WooCommerce API call involved.
 *
 * Column mapping notes (based on the real Simie Home export):
 *  - "ID" is the only reliable unique key. SKU is present on a small
 *    minority of rows, so it is kept as a display field, not the mapping
 *    key.
 *  - "Type" is simple | variable | variation. Only simple/variation rows
 *    carry an actual price and are sellable; "variable" rows are just the
 *    parent/grouping row and are skipped in product search.
 *  - "Categories" is only populated on the parent row (simple or variable).
 *    Variation rows inherit their parent's category in a second pass via
 *    "Parent" (format "id:1234").
 *  - "Regular price"/"Sale price" are the catalog's own fixed prices per
 *    SKU/variation — most products do not need a size/length formula at
 *    all, the Pricing Engine's default "catalog_price" rule just reads
 *    these as-is.
 *  - "Base price" (optional, not a standard WooCommerce column — add it to
 *    the export manually if needed) feeds the "custom_formula" pricing rule
 *    type, for computing a price when the customer wants a bespoke size the
 *    catalog doesn't already list (e.g. "base_price + length_cm * 5000").
 *    Left null when the column is absent or blank.
 *  - Attribute 1/2/3 name+value columns become a generic attributes map
 *    (e.g. {"Size": "140x60x75", "Color": "..."}) used for quote detail
 *    display, since the dedicated Length/Width/Height columns in the
 *    export do not reliably reflect per-variation size.
 *  - "Visibility in catalog" (visible | catalog | search | hidden) gates
 *    whether a row is imported at all — only "visible" rows become
 *    products; everything else is left out of the local catalog. Like
 *    Categories, this is only populated on the parent row (simple or
 *    variable) in the real export, so a variation row with no value of
 *    its own inherits its parent's value in the second pass and is
 *    removed if the parent turns out not to be visible.
 *
 * Optional `$filters` (all admin-configurable, on top of the hardcoded
 * "Visibility in catalog = visible" rule above):
 *  - 'statuses': list of allowed `status` values (active|draft, derived
 *    from "Published"). Applied immediately per row — every row states its
 *    own Published value, there is no inheritance to wait for.
 *  - 'types': list of allowed `type` values (simple|variable|variation).
 *    Also applied immediately — "Type" is always present per row.
 *  - 'categories': list of allowed top-level category names (matched
 *    case-insensitively against the same value the "Categories" column
 *    resolves to). A row whose own Categories is blank only gets its
 *    category once its parent is resolved, so — exactly like the
 *    visibility inheritance above — a row with no category of its own but
 *    a parent reference has its filter check deferred to the second pass;
 *    a row with neither is excluded immediately since it can never match.
 * An empty/absent list for any of these means "no restriction" for that
 * dimension, matching the pre-existing (unfiltered) import behaviour.
 */
final class ProductCsvImporter
{
    /**
     * @var array<int, string>
     */
    private const REQUIRED_COLUMNS = ['ID', 'Type', 'Name'];

    /**
     * @param  array{statuses?: array<int, string>, types?: array<int, string>, categories?: array<int, string>}  $filters
     */
    public function import(string $filePath, array $filters = []): ImportSummary
    {
        $statusFilter = ! empty($filters['statuses']) ? array_map('strtolower', $filters['statuses']) : null;
        $typeFilter = ! empty($filters['types']) ? array_map('strtolower', $filters['types']) : null;
        $categoryFilter = ! empty($filters['categories'])
            ? array_map(static fn ($category) => mb_strtolower(trim($category)), $filters['categories'])
            : null;

        if (! is_readable($filePath)) {
            throw new RuntimeException("Không đọc được file CSV: {$filePath}");
        }

        $handle = fopen($filePath, 'r');

        if ($handle === false) {
            throw new RuntimeException("Không mở được file CSV: {$filePath}");
        }

        $header = fgetcsv($handle);

        if ($header === false) {
            fclose($handle);

            return new ImportSummary(errors: ['File CSV rỗng hoặc không đọc được dòng tiêu đề.']);
        }

        $header = $this->normalizeHeader($header);

        foreach (self::REQUIRED_COLUMNS as $required) {
            if (! in_array($required, $header, true)) {
                fclose($handle);

                return new ImportSummary(errors: ["Thiếu cột bắt buộc trong CSV: {$required}"]);
            }
        }

        $totalRows = 0;
        // Rows are added to these by external_id as they're created/updated
        // in the first pass, then reconciled against $hiddenExternalIds
        // after the second pass — a variation tentatively created while its
        // parent's visibility was still unknown ends up removed, and must
        // not be counted as created/updated in the final summary.
        $createdExternalIds = [];
        $updatedExternalIds = [];
        $skipped = 0;
        $errors = [];
        $categoryByExternalId = [];
        $externalIdBySku = [];
        $visibilityByExternalId = [];
        // external_ids excluded from the catalog because their own (or
        // inherited) "Visibility in catalog" is not "visible".
        $hiddenExternalIds = [];
        // Subset of $hiddenExternalIds excluded specifically because of the
        // admin-configurable status/type/category filters below (as opposed
        // to the always-on "Visibility in catalog" rule) — tracked
        // separately purely to report an accurate count to the admin.
        $filteredExternalIds = [];
        // Rows whose "Parent" column could not be resolved to a numeric id
        // while reading (either it references the parent by SKU, or the
        // parent row appears later in the file). Resolved in a second pass
        // once every row has been read.
        $pendingParentResolution = [];

        DB::transaction(function () use (
            $handle,
            $header,
            &$totalRows,
            &$createdExternalIds,
            &$updatedExternalIds,
            &$skipped,
            &$errors,
            &$categoryByExternalId,
            &$externalIdBySku,
            &$visibilityByExternalId,
            &$hiddenExternalIds,
            &$filteredExternalIds,
            &$pendingParentResolution,
            $statusFilter,
            $typeFilter,
            $categoryFilter,
        ) {
            $rowNumber = 1;

            while (($row = fgetcsv($handle)) !== false) {
                $rowNumber++;

                if ($row === [null] || $row === false) {
                    continue;
                }

                $totalRows++;
                $data = $this->combineRow($header, $row);

                $externalId = $this->intOrNull($data['ID'] ?? null);
                $name = trim((string) ($data['Name'] ?? ''));

                if ($externalId === null || $name === '') {
                    $skipped++;
                    $errors[] = "Dòng {$rowNumber}: thiếu ID hoặc Name, đã bỏ qua.";

                    continue;
                }

                $type = trim((string) ($data['Type'] ?? 'simple')) ?: 'simple';
                $sku = $this->stringOrNull($data['SKU'] ?? null);
                $category = $this->firstCategorySegment($data['Categories'] ?? null);
                $status = ($data['Published'] ?? '1') === '-1' ? 'draft' : 'active';

                // "Parent" is either "id:1234" (numeric WooCommerce id) or,
                // when the parent product has a SKU, the parent's SKU string
                // directly. Both forms show up in the real export.
                $parentRef = $this->parseParentReference($data['Parent'] ?? null);
                $parentExternalId = $parentRef !== null && $parentRef['type'] === 'id'
                    ? $parentRef['value']
                    : null;

                if ($sku !== null) {
                    $externalIdBySku[$sku] = $externalId;
                }

                if ($category !== null) {
                    $categoryByExternalId[$externalId] = $category;
                } elseif ($parentRef !== null) {
                    // Variation rows usually have no Categories/Parent-id of
                    // their own; resolve from the parent in the second pass.
                    $pendingParentResolution[$externalId] = $parentRef;
                }

                $ownVisibility = $this->visibilityOrNull($data['Visibility in catalog'] ?? null);

                if ($ownVisibility !== null) {
                    $visibilityByExternalId[$externalId] = $ownVisibility;
                }

                // Only a row with its own explicit, non-"visible" value is
                // excluded here. A variation with no value of its own
                // ($ownVisibility === null) is created tentatively and
                // reconciled against its parent's visibility in the second
                // pass below, once every parent row has been read.
                if ($ownVisibility !== null && $ownVisibility !== 'visible') {
                    $hiddenExternalIds[] = $externalId;
                    $skipped++;

                    continue;
                }

                // Admin-configurable filters (on top of the always-on
                // visibility rule above). "Type" and "Published" are always
                // present on every row — no inheritance to wait for — so
                // both are checked immediately.
                if ($typeFilter !== null && ! in_array(strtolower($type), $typeFilter, true)) {
                    $hiddenExternalIds[] = $externalId;
                    $filteredExternalIds[] = $externalId;
                    $skipped++;

                    continue;
                }

                if ($statusFilter !== null && ! in_array($status, $statusFilter, true)) {
                    $hiddenExternalIds[] = $externalId;
                    $filteredExternalIds[] = $externalId;
                    $skipped++;

                    continue;
                }

                // The category filter is trickier: a row can only be judged
                // here if it has its own Categories value, or has neither a
                // category nor a parent to ever inherit one from (so it can
                // never match and is excluded now). A row with no category
                // of its own but a parent reference is judged later, once
                // the parent is resolved in the second pass below.
                if ($categoryFilter !== null) {
                    if ($category !== null && ! in_array(mb_strtolower($category), $categoryFilter, true)) {
                        $hiddenExternalIds[] = $externalId;
                        $filteredExternalIds[] = $externalId;
                        $skipped++;

                        continue;
                    }

                    if ($category === null && $parentRef === null) {
                        $hiddenExternalIds[] = $externalId;
                        $filteredExternalIds[] = $externalId;
                        $skipped++;

                        continue;
                    }
                }

                $attributes = $this->extractAttributes($data);

                $values = [
                    'parent_external_id' => $parentExternalId,
                    'type' => $type,
                    'sku' => $sku,
                    'name' => $name,
                    'category' => $category,
                    'regular_price' => $this->decimalOrNull($data['Regular price'] ?? null),
                    'sale_price' => $this->decimalOrNull($data['Sale price'] ?? null),
                    'base_price' => $this->decimalOrNull($data['Base price'] ?? null),
                    'image_url' => $this->firstImageUrl($data['Images'] ?? null),
                    'attributes_json' => $attributes !== [] ? $attributes : null,
                    'status' => $status,
                    'source' => 'csv_import',
                    'last_synced_at' => now(),
                ];

                $product = Product::query()->updateOrCreate(['external_id' => $externalId], $values);

                if ($product->wasRecentlyCreated) {
                    $createdExternalIds[] = $externalId;
                } else {
                    $updatedExternalIds[] = $externalId;
                }
            }

            fclose($handle);

            // A row is only registered in $pendingParentResolution based on
            // whether it *has* a category to inherit — independent of
            // whether it actually got created. A row can be present here
            // and still have been skipped already in the first pass (its
            // own explicit visibility was non-"visible", or it failed the
            // type/status/category filters above) — in that case there is
            // no product row to update/delete, and it must not be counted
            // as skipped a second time here.
            $createdOrUpdatedExternalIds = array_flip(array_merge($createdExternalIds, $updatedExternalIds));

            // Second pass: resolve parent_external_id (for SKU-referenced
            // parents), inherit category for every row that needed it, and
            // remove any variation whose parent turned out not to be
            // "visible" — it was created tentatively above since its own
            // visibility was unknown at the time.
            foreach ($pendingParentResolution as $externalId => $parentRef) {
                if (! isset($createdOrUpdatedExternalIds[$externalId])) {
                    continue;
                }

                $parentExternalId = $parentRef['type'] === 'id'
                    ? $parentRef['value']
                    : ($externalIdBySku[$parentRef['value']] ?? null);

                if ($parentExternalId === null) {
                    // Parent could never be resolved, so this row's category
                    // can never be known either — if a category filter is
                    // active, it can never match, so it must be excluded
                    // rather than silently kept with category = null.
                    if ($categoryFilter !== null) {
                        Product::query()->where('external_id', $externalId)->delete();

                        $hiddenExternalIds[] = $externalId;
                        $filteredExternalIds[] = $externalId;
                        $skipped++;
                    }

                    continue;
                }

                $parentVisibility = $visibilityByExternalId[$parentExternalId] ?? null;

                if ($parentVisibility !== null && $parentVisibility !== 'visible') {
                    Product::query()->where('external_id', $externalId)->delete();

                    $hiddenExternalIds[] = $externalId;
                    $skipped++;

                    continue;
                }

                $category = $categoryByExternalId[$parentExternalId] ?? null;

                if (
                    $categoryFilter !== null
                    && ($category === null || ! in_array(mb_strtolower($category), $categoryFilter, true))
                ) {
                    Product::query()->where('external_id', $externalId)->delete();

                    $hiddenExternalIds[] = $externalId;
                    $filteredExternalIds[] = $externalId;
                    $skipped++;

                    continue;
                }

                $update = ['parent_external_id' => $parentExternalId];

                if ($category !== null) {
                    $update['category'] = $category;
                }

                Product::query()
                    ->where('external_id', $externalId)
                    ->update($update);
            }

            $visibilityExcludedCount = count(array_diff($hiddenExternalIds, $filteredExternalIds));

            if ($visibilityExcludedCount > 0) {
                $errors[] = "Đã bỏ qua {$visibilityExcludedCount} sản phẩm do Visibility in catalog khác 'visible'.";
            }

            if ($filteredExternalIds !== []) {
                $errors[] = 'Đã bỏ qua '.count($filteredExternalIds).' sản phẩm do không khớp điều kiện lọc (trạng thái/nhóm/loại) đã chọn.';
            }
        });

        $created = count(array_diff($createdExternalIds, $hiddenExternalIds));
        $updated = count(array_diff($updatedExternalIds, $hiddenExternalIds));

        return new ImportSummary(
            totalRows: $totalRows,
            created: $created,
            updated: $updated,
            skipped: $skipped,
            errors: $errors,
        );
    }

    /**
     * @param  array<int, string>  $header
     * @return array<int, string>
     */
    private function normalizeHeader(array $header): array
    {
        $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $header[0]);

        return array_map(static fn ($column) => trim((string) $column), $header);
    }

    /**
     * @param  array<int, string>  $header
     * @param  array<int, string|null>  $row
     * @return array<string, string|null>
     */
    private function combineRow(array $header, array $row): array
    {
        if (count($row) < count($header)) {
            $row = array_pad($row, count($header), null);
        } elseif (count($row) > count($header)) {
            $row = array_slice($row, 0, count($header));
        }

        return array_combine($header, $row);
    }

    private function intOrNull(?string $value): ?int
    {
        $value = trim((string) $value);

        return $value !== '' && is_numeric($value) ? (int) $value : null;
    }

    private function stringOrNull(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }

    private function decimalOrNull(?string $value): ?float
    {
        $value = trim((string) $value);

        return $value !== '' && is_numeric($value) ? (float) $value : null;
    }

    private function visibilityOrNull(?string $value): ?string
    {
        $value = strtolower(trim((string) $value));

        return $value !== '' ? $value : null;
    }

    /**
     * The "Parent" column is either "id:1234" (numeric WooCommerce id) or,
     * when the parent product has a SKU, the parent's bare SKU string.
     *
     * @return array{type: 'id'|'sku', value: int|string}|null
     */
    private function parseParentReference(?string $value): ?array
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        if (str_starts_with($value, 'id:')) {
            $id = substr($value, 3);

            return is_numeric($id) ? ['type' => 'id', 'value' => (int) $id] : null;
        }

        return ['type' => 'sku', 'value' => $value];
    }

    private function firstCategorySegment(?string $value): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        $firstCategory = explode(',', $value)[0];
        $topLevel = explode('>', $firstCategory)[0];

        return trim($topLevel) ?: null;
    }

    private function firstImageUrl(?string $value): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        return trim(explode(',', $value)[0]) ?: null;
    }

    /**
     * @param  array<string, string|null>  $data
     * @return array<string, string>
     */
    private function extractAttributes(array $data): array
    {
        $attributes = [];

        foreach ([1, 2, 3] as $index) {
            $name = trim((string) ($data["Attribute {$index} name"] ?? ''));
            $value = trim((string) ($data["Attribute {$index} value(s)"] ?? ''));

            if ($name !== '' && $value !== '') {
                $attributes[$name] = $value;
            }
        }

        return $attributes;
    }
}
