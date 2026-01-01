<?php

namespace Iyzico\IyzipayLaravel\StorableClasses;

use Illuminate\Contracts\Database\Eloquent\Castable;
use Illuminate\Contracts\Support\Arrayable;
use Iyzico\IyzipayLaravel\Casts\BillFieldsCast;
use Iyzico\IyzipayLaravel\Exceptions\Fields\BillFieldsException;
use JsonSerializable;

class BillFields implements Castable, Arrayable, JsonSerializable
{
    public function __construct(
        public string $firstName,
        public string $lastName,
        public string $email,
        public string $identityNumber,
        public string $mobileNumber,
        public Address $shippingAddress,
        public Address $billingAddress
    ) {
        if (empty($firstName) || empty($lastName) || empty($email) || empty($identityNumber)) {
            throw new BillFieldsException(
                'Bill fields cannot be empty.'
            );
        }
    }

    /**
     * Define the caster class for this object.
     */
    public static function castUsing(array $arguments): string
    {
        return BillFieldsCast::class;
    }

    /**
     * Hydrate the object from database array/json.
     */
    public static function fromArray(array $data): self
    {
        return new self(
            firstName: $data['firstName'] ?? '',
            lastName:  $data['lastName'] ?? '',
            email:     $data['email'] ?? '',
            identityNumber: $data['identityNumber'] ?? '',
            mobileNumber:   $data['mobileNumber'] ?? '',
            shippingAddress: Address::fromArray($data['shippingAddress'] ?? []),
            billingAddress:  Address::fromArray($data['billingAddress'] ?? [])
        );
    }

    public function toArray(): array
    {
        return [
            'firstName'       => $this->firstName,
            'lastName'        => $this->lastName,
            'email'           => $this->email,
            'identityNumber'  => $this->identityNumber,
            'mobileNumber'    => $this->mobileNumber,
            'shippingAddress' => $this->shippingAddress->toArray(),
            'billingAddress'  => $this->billingAddress->toArray(),
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
