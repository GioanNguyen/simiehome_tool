<?php

namespace App\Domain\Pricing\Formula;

use RuntimeException;

/**
 * Thrown by FormulaEvaluator for anything wrong with an admin-authored
 * formula: a syntax error, a variable name that isn't in the allowed set,
 * or a division by zero. The message is Vietnamese and safe to show
 * directly to an admin filling in the pricing rule form.
 */
final class InvalidFormulaException extends RuntimeException {}
