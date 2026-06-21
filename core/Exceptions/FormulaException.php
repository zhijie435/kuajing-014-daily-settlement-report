<?php

namespace App\Exceptions;

class FormulaException extends \Exception
{
    public const FORMULA_NOT_FOUND = 'FORMULA_NOT_FOUND';
    public const FORMULA_DISABLED = 'FORMULA_DISABLED';
    public const VARIABLE_MISSING = 'VARIABLE_MISSING';
    public const INVALID_FORMULA = 'INVALID_FORMULA';
    public const NEGATIVE_RESULT = 'NEGATIVE_RESULT';
    public const UNSAFE_CODE = 'UNSAFE_CODE';
    public const CALCULATION_ERROR = 'CALCULATION_ERROR';

    private $errorCode;

    public function __construct(string $message = '', string $errorCode = self::CALCULATION_ERROR, int $code = 0, \Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
        $this->errorCode = $errorCode;
    }

    public function getErrorCode(): string
    {
        return $this->errorCode;
    }
}
