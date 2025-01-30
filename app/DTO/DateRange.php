<?php

namespace App\DTO;

readonly class DateRange
{
    public function __construct(
        public string $start,
        public string $end
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            $data['start'],
            $data['end']
        );
    }
}
