<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Business rule violation (RN01-RN08 and the rules derived from them).
 *
 * The HTTP status travels in the exception code, which is what
 * MessageService::throwable() reads to build the response. Messages are in
 * Portuguese because they reach the end user.
 */
class BusinessRuleException extends RuntimeException
{
    /** Conflict with data already stored, such as an overlapping reservation. */
    public static function conflict(string $message): self
    {
        return new self($message, 409);
    }

    /** The request is well formed but breaks a rule of the domain. */
    public static function unprocessable(string $message): self
    {
        return new self($message, 422);
    }

    /** The authenticated user may not act on this specific record. */
    public static function forbidden(string $message): self
    {
        return new self($message, 403);
    }

    public static function notFound(string $message): self
    {
        return new self($message, 404);
    }
}
