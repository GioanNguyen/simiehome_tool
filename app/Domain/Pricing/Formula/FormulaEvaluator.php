<?php

namespace App\Domain\Pricing\Formula;

/**
 * Evaluates a small arithmetic expression an admin types into the pricing
 * rule form — e.g. "base_price + length_cm * 5000" — against a fixed set
 * of named variables (product columns / quote context values).
 *
 * Supports only: +, -, *, / (with the usual precedence), unary +/-,
 * parentheses, and decimal number literals. No function calls, no
 * comparisons, no string handling, and no way to reach arbitrary PHP —
 * the parser below is the only thing that ever runs, so a typo or a
 * malicious paste can, at worst, fail to parse. It can never execute code.
 */
final class FormulaEvaluator
{
    private const OPERATORS = ['+', '-', '*', '/', '(', ')'];

    /**
     * @var array<int, array{type: string, value: string}>
     */
    private array $tokens = [];

    private int $position = 0;

    /**
     * @var array<string, float>
     */
    private array $variables = [];

    /**
     * @param  array<string, float>  $variables  variable name => value, e.g.
     *                                           ['base_price' => 1_000_000, 'length_cm' => 140]
     */
    public function evaluate(string $expression, array $variables): float
    {
        $this->tokens = $this->tokenize($expression);
        $this->position = 0;
        $this->variables = $variables;

        if ($this->tokens === []) {
            throw new InvalidFormulaException('Công thức đang trống.');
        }

        $result = $this->parseExpression();

        if ($this->position < count($this->tokens)) {
            $unexpected = $this->tokens[$this->position]['value'];

            throw new InvalidFormulaException("Công thức sai cú pháp gần \"{$unexpected}\".");
        }

        return $result;
    }

    /**
     * @return array<int, array{type: string, value: string}>
     */
    private function tokenize(string $expression): array
    {
        $tokens = [];
        $length = strlen($expression);
        $i = 0;

        while ($i < $length) {
            $char = $expression[$i];

            if (ctype_space($char)) {
                $i++;

                continue;
            }

            if (in_array($char, self::OPERATORS, true)) {
                $tokens[] = ['type' => 'operator', 'value' => $char];
                $i++;

                continue;
            }

            if (ctype_digit($char) || $char === '.') {
                $start = $i;
                $dotSeen = false;

                while ($i < $length && (ctype_digit($expression[$i]) || ($expression[$i] === '.' && ! $dotSeen))) {
                    if ($expression[$i] === '.') {
                        $dotSeen = true;
                    }

                    $i++;
                }

                $numberText = substr($expression, $start, $i - $start);

                if (! is_numeric($numberText)) {
                    throw new InvalidFormulaException("Công thức chứa số không hợp lệ: \"{$numberText}\".");
                }

                $tokens[] = ['type' => 'number', 'value' => $numberText];

                continue;
            }

            if (ctype_alpha($char) || $char === '_') {
                $start = $i;

                while ($i < $length && (ctype_alnum($expression[$i]) || $expression[$i] === '_')) {
                    $i++;
                }

                $tokens[] = ['type' => 'identifier', 'value' => substr($expression, $start, $i - $start)];

                continue;
            }

            throw new InvalidFormulaException("Công thức chứa ký tự không hợp lệ: \"{$char}\".");
        }

        return $tokens;
    }

    private function parseExpression(): float
    {
        $value = $this->parseTerm();

        while ($this->peekIsOperator('+') || $this->peekIsOperator('-')) {
            $operator = $this->tokens[$this->position]['value'];
            $this->position++;
            $right = $this->parseTerm();
            $value = $operator === '+' ? $value + $right : $value - $right;
        }

        return $value;
    }

    private function parseTerm(): float
    {
        $value = $this->parseFactor();

        while ($this->peekIsOperator('*') || $this->peekIsOperator('/')) {
            $operator = $this->tokens[$this->position]['value'];
            $this->position++;
            $right = $this->parseFactor();

            if ($operator === '*') {
                $value *= $right;

                continue;
            }

            if ($right === 0.0) {
                throw new InvalidFormulaException('Công thức chia cho 0.');
            }

            $value /= $right;
        }

        return $value;
    }

    private function parseFactor(): float
    {
        if ($this->peekIsOperator('-')) {
            $this->position++;

            return -$this->parseFactor();
        }

        if ($this->peekIsOperator('+')) {
            $this->position++;

            return $this->parseFactor();
        }

        return $this->parsePrimary();
    }

    private function parsePrimary(): float
    {
        $token = $this->tokens[$this->position] ?? null;

        if ($token === null) {
            throw new InvalidFormulaException('Công thức thiếu giá trị hoặc biến.');
        }

        if ($token['type'] === 'number') {
            $this->position++;

            return (float) $token['value'];
        }

        if ($token['type'] === 'identifier') {
            $this->position++;

            if (! array_key_exists($token['value'], $this->variables)) {
                throw new InvalidFormulaException("Công thức dùng biến không hợp lệ: \"{$token['value']}\".");
            }

            return (float) $this->variables[$token['value']];
        }

        if ($token['value'] === '(') {
            $this->position++;
            $value = $this->parseExpression();

            if (! $this->peekIsOperator(')')) {
                throw new InvalidFormulaException('Công thức thiếu dấu ")".');
            }

            $this->position++;

            return $value;
        }

        throw new InvalidFormulaException("Công thức sai cú pháp gần \"{$token['value']}\".");
    }

    private function peekIsOperator(string $operator): bool
    {
        $token = $this->tokens[$this->position] ?? null;

        return $token !== null && $token['type'] === 'operator' && $token['value'] === $operator;
    }
}
