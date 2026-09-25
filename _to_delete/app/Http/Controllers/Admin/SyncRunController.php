<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\SyncWooCommerceProductsJob;
use App\Models\SyncRun;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class SyncRunController extends Controller
{
    public function index(): View
    {
        return view('admin.sync-runs.index', [
            'syncRuns' => SyncRun::query()->latest()->get(),
        ]);
    }

    public function store(): RedirectResponse
    {
        dispatch_sync(new SyncWooCommerceProductsJob);

        return redirect()->route('admin.sync-runs.index');
    }
}
