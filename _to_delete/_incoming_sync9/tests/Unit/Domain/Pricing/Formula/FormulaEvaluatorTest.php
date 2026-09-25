<?php

namespace Tests\Unit\Domain\Pricing\Formula;

use App\Domain\Pricing\Formula\FormulaEvaluator;
use App\Domain\Pricing\Formula\InvalidFormulaException;
use Tests\TestCase;

class FormulaEvaluatorTest extends TestCase
{
    public function test_it_evaluates_basic_arithmetic_with_correct_precedence(): void
    {
        $result = (new FormulaEvaluator)->evaluate('2 + 3 * 4', []);

        $this->assertSame(14.0, $result);
    }

    public function test_it_respects_parentheses(): void
    {
        $result = (new FormulaEvaluator)->evaluate('(2 + 3) * 4', []);

        $this->assertSame(20.0, $result);
    }

    public function test_it_substitutes_named_variables(): void
    {
        $result = (new FormulaEvaluator)->evaluate(
            'base_price + length_cm * 5000',
            ['base_price' => 1_000_000.0, 'length_cm' => 140.0],
        );

        $this->assertSame(1_700_000.0, $result);
    }

    public function test_it_supports_unary_minus(): void
    {
        $result = (new FormulaEvaluator)->evaluate('-length_cm + 10', ['length_cm' => 4.0]);

        $this->assertSame(6.0, $result);
    }

    public function test_it_supports_decimal_number_literals(): void
    {
        $result = (new FormulaEvaluator)->evaluate('base_price * 1.5', ['base_price' => 200.0]);

        $this->assertSame(300.0, $result);
    }

    public function test_it_divides(): void
    {
        $result = (new FormulaEvaluator)->evaluate('base_price / 2', ['base_price' => 100.0]);

        $this->assertSame(50.0, $result);
    }

    public function test_it_rejects_division_by_zero(): void
    {
        $this->expectException(InvalidFormulaException::class);
        $this->expectExceptionMessage('chia cho 0');

        (new FormulaEvaluator)->evaluate('base_price / 0', ['base_price' => 100.0]);
    }

    public function test_it_rejects_an_unknown_variable(): void
    {
        $this->expectException(InvalidFormulaException::class);
        $this->expectExceptionMessage('width_cm');

        (new FormulaEvaluator)->evaluate('width_cm * 2', ['base_price' => 100.0]);
    }

    public function test_it_rejects_an_empty_expression(): void
    {
        $this->expectException(InvalidFormulaException::class);

        (new FormulaEvaluator)->evaluate('   ', []);
    }

    public function test_it_rejects_a_syntax_error_from_a_dangling_operator(): void
    {
        $this->expectException(InvalidFormulaException::class);

        (new FormulaEvaluator)->evaluate('2 +', []);
    }

    public function test_it_rejects_trailing_input_after_a_valid_expression(): void
    {
        $this->expectException(InvalidFormulaException::class);

        (new FormulaEvaluator)->evaluate('2 + 3)', []);
    }

    public function test_it_rejects_an_unclosed_parenthesis(): void
    {
        $this->expectException(InvalidFormulaException::class);

        (new FormulaEvaluator)->evaluate('(2 + 3', []);
    }

    public function test_it_rejects_an_invalid_character(): void
    {
        $this->expectException(InvalidFormulaException::class);

        (new FormulaEvaluator)->evaluate('base_price @ 2', ['base_price' => 100.0]);
    }

    public function test_it_rejects_implicit_multiplication_with_no_operator(): void
    {
        $this->expectException(InvalidFormulaException::class);

        (new FormulaEvaluator)->evaluate('2 base_price', ['base_price' => 100.0]);
    }
}
