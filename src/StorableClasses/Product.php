<?php

namespace Iyzico\IyzipayLaravel\StorableClasses;

use Iyzico\IyzipayLaravel\ProductContract;
use Iyzipay\Model\BasketItemType;

class Product implements ProductContract
{
    public function __construct(
        public string|int $id,
        public string $name,
        public float $price,
        public string $currency = 'TRY',
        public string $category = 'General',
        public string $type = BasketItemType::PHYSICAL
    ) {}

    /**
     * Reconstruct the object from the database array.
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id:       $data['id'],
            name:     $data['name'],
            price:    (float) $data['price'],
            currency: $data['currency'] ?? 'TRY',
            category: $data['category'] ?? 'General',
            type:     $data['type'] ?? BasketItemType::PHYSICAL
        );
    }


    public function getKey()
    {
        return $this->id;
    }

    public function getKeyName()
    {
        return 'id';
    }

    public function getName()
    {
        return $this->name;
    }

    public function getPrice()
    {
        return $this->price;
    }

    public function getCategory()
    {
        return $this->category;
    }

    public function getType()
    {
        return $this->type;
    }

    public function toArray()
    {
        return [
            'id'       => $this->id,
            'name'     => $this->name,
            'price'    => $this->price,
            'currency' => $this->currency,
            'category' => $this->category,
            'type'     => $this->type,
        ];
    }
}
