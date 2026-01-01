<?php

namespace Iyzico\IyzipayLaravel\StorableClasses;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Arr;
use Iyzico\IyzipayLaravel\Exceptions\Fields\PlanFieldsException;
use Iyzico\IyzipayLaravel\ProductContract;
use Iyzipay\Model\BasketItemType;
use JsonSerializable;

class Plan implements ProductContract, Arrayable, JsonSerializable
{
    public function __construct(
        public string $id = '',
        public string $name = '',
        public float $price = 0.0,
        public string $currency = 'TRY',
        public string $interval = 'monthly',
        public int $trialDays = 0,
        public array $features = [],
        public array $attributes = []
    ) {
        $this->validate();
    }

    private function validate(): void
    {
        if (empty($this->name)) {
            throw new PlanFieldsException('Plan name cannot be blank!');
        }

        if (empty($this->id)) {
            throw new PlanFieldsException('Plan ID cannot be blank!');
        }
    }

    public function __toString(): string
    {
        return json_encode($this);
    }

    /**
     * Create instance from array (Helper for config/JSON hydration).
     * @throws \Exception
     */
    public static function fromArray(array $attributes): self
    {
        return new self(
            id:         $attributes['id'] ?? '',
            name:       $attributes['name'] ?? '',
            price:      (float) ($attributes['price'] ?? 0),
            currency:   $attributes['currency'] ?? 'TRY',
            interval:   $attributes['interval'] ?? 'monthly',
            trialDays:  (int) ($attributes['trialDays'] ?? 0),
            features:   $attributes['features'] ?? [],
            attributes: $attributes['attributes'] ?? []
        );
    }

    /**
     * Find a plan from the iyzipay config file.
     *
     * @param string $key
     * @return Plan
     * @throws \Exception
     */
    public static function find(string $key): Plan
    {
        $config = config("iyzipay.subscription_plans.{$key}");

        if (empty($config)) {
            throw new \Exception("Subscription plan with key [{$key}] not found in iyzipay configuration.");
        }

        // Inject the Key as the ID, since it's the array key in config
        $config['id'] = $key;

        return self::fromArray($config);
    }

    public function name(string $name): static
    {
        $this->name = $name;
        return $this;
    }

    public function id(string $id): static
    {
        $this->id = $id;
        return $this;
    }

    public function price(float $price): static
    {
        $this->price = $price;
        return $this;
    }

    public function currency(string $currency): static
    {
        $this->currency = $currency;
        return $this;
    }

    public function yearly(): static
    {
        $this->interval = 'yearly';
        return $this;
    }

    public function monthly(): static
    {
        $this->interval = 'monthly';
        return $this;
    }

    public function trialDays(int $trialDays): static
    {
        $this->trialDays = $trialDays;
        return $this;
    }

    public function features(array $features): static
    {
        $this->features = $features;
        return $this;
    }

    public function attributes(array $attributes): static
    {
        $this->attributes = array_merge($this->attributes, $attributes);
        return $this;
    }

    public function attribute(string $key, mixed $default = null): mixed
    {
        return Arr::get($this->attributes, $key, $default);
    }

    public function getKey(): string
    {
        return $this->id;
    }

    public function getKeyName(): string
    {
        return 'id';
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getPrice(): float
    {
        return $this->price;
    }

    public function getCategory(): string
    {
        return 'Plan';
    }

    public function getType(): string
    {
        return BasketItemType::VIRTUAL;
    }

    public function toArray(): array
    {
        return [
            'id'        => $this->id,
            'name'      => $this->name,
            'price'     => $this->price,
            'currency'  => $this->currency,
            'interval'  => $this->interval,
            'trialDays' => $this->trialDays,
            'features'  => $this->features,
            'attributes'=> $this->attributes,
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    protected function getFieldExceptionClass(): string
    {
        return PlanFieldsException::class;
    }
}
