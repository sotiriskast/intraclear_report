<?php
namespace App\Api\V1\Services\RollingReserve\Responses;

readonly class ApiResponse
{
    private function __construct(
        public bool $success,
        public string $message,
        public mixed $data = null,
        public array $meta = [],
        public ?array $errors = null
    ) {}

    public static function success(mixed $data, array $meta = []): self
    {
        return new self(
            success: true,
            message: 'Operation successful',
            data: $data,
            meta: $meta
        );
    }

    public static function failure(string $message, ?array $errors = null): self
    {
        return new self(
            success: false,
            message: $message,
            errors: $errors
        );
    }
}
