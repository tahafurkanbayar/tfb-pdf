<?php

declare(strict_types=1);

namespace App\Http;

use App\Exceptions\AppException;
use App\Exceptions\ErrorCategory;

final class MethodNotAllowedException extends AppException
{
    /**
     * @param list<string> $allowed
     */
    public function __construct(public readonly array $allowed)
    {
        parent::__construct('Method not allowed', 'errors.method_not_allowed');
    }

    public function category(): ErrorCategory
    {
        return ErrorCategory::Validation;
    }

    public function httpStatus(): int
    {
        return 405;
    }
}
