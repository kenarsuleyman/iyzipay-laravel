<?php

namespace Iyzico\IyzipayLaravel\StorableClasses;

use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

class Address implements Arrayable, JsonSerializable
{
    public function __construct(
        public string $city,
        public string $country,
        public string $address
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            city: $data['city'] ?? '',
            country: $data['country'] ?? '',
            address: $data['address'] ?? ''
        );
    }

    public function toArray(): array
    {
        return [
            'city'    => $this->city,
            'country' => $this->country,
            'address' => $this->address,
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
