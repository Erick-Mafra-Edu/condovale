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
    /** The request field the broken rule is about, when there is one. */
    private ?string $field = null;

    /** Conflict with data already stored, such as an overlapping reservation. */
    public static function conflict(string $message): self
    {
        return new self($message, 409);
    }

    /**
     * The request is well formed but breaks a rule of the domain.
     *
     * Naming the field lets the response carry `errors` in the same shape as
     * Laravel's validation, so the client points at the field either way.
     */
    public static function unprocessable(string $message, ?string $field = null): self
    {
        $exception = new self($message, 422);
        $exception->field = $field;

        return $exception;
    }

    public function field(): ?string
    {
        return $this->field;
    }

    /**
     * The validation-shaped errors for the response: empty when no field.
     *
     * @return array<string, list<string>>
     */
    public function errors(): array
    {
        return $this->field === null ? [] : [$this->field => [$this->getMessage()]];
    }

    /** The credentials do not identify anyone: wrong e-mail or wrong password. */
    public static function unauthorized(string $message): self
    {
        return new self($message, 401);
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
