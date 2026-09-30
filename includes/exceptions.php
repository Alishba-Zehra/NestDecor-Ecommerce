<?php
/**
 * Thrown by service-layer functions when a business rule is violated
 * (e.g. duplicate slug, negative stock). Carries the HTTP status code
 * the API layer should respond with.
 */
class ApiException extends \RuntimeException
{
    public int $statusCode;

    public function __construct(string $message, int $statusCode)
    {
        parent::__construct($message, $statusCode);
        $this->statusCode = $statusCode;
    }
}