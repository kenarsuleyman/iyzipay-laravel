<?php

namespace Iyzico\IyzipayLaravel\StorableClasses;

use Illuminate\Support\Arr;
use Iyzico\IyzipayLaravel\Exceptions\Fields\PlanFieldsException;
use Iyzico\IyzipayLaravel\ProductContract;
use Iyzipay\Model\BasketItemType;

class Plan extends StorableClass implements ProductContract
{

    /**
     * The plan's id
     *
     * @var string
     */
    public string $id;

    /**
     * The plan's displayable name
     *
     * @var string
     */
    public string $name;

    /**
     * The plan's price.
     *
     * @var integer
     */
    public int $price = 0;

    /**
     * The plan's interval.
     *
     * @var string
     */
    public string $interval = 'monthly';

    /**
     * The number of trial days that come with the plan.
     *
     * @var int
     */
    public int $trialDays = 0;

    /**
     * The plan's features.
     *
     * @var array
     */
    public array $features = [];

    /**
     * The plan's attributes.
     *
     * @var array
     */
    public array $attributes = [];

    /**
     * The plan's currency
     *
     * @var string
     */
    public string $currency = 'TRY';

    /**
     * Set the name of the plan.
     *
     * @param string $name
     *
     * @return $this
     */
    public function name(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    /**
     * Set the id of the plan.
     *
     * @param string $id
     *
     * @return $this
     */
    public function id(string $id): static
    {
        $this->id = $id;

        return $this;
    }

    /**
     * Set the price of the plan.
     *
     * @param integer $price
     *
     * @return $this
     */
    public function price(int $price): static
    {
        $this->price = $price;

        return $this;
    }

    /**
     * Specify that the plan is on a yearly interval.
     *
     * @return $this
     */
    public function yearly(): static
    {
        $this->interval = 'yearly';

        return $this;
    }

    /**
     * Specify the number of trial days that come with the plan.
     *
     * @param int $trialDays
     *
     * @return $this
     */
    public function trialDays(int $trialDays): static
    {
        $this->trialDays = $trialDays;

        return $this;
    }

    /**
     * Specify the currency of plan.
     *
     * @param $currency
     *
     * @return $this
     */
    public function currency($currency): static
    {
        $this->currency = $currency;

        return $this;
    }

    /**
     * Specify the plan's features.
     *
     * @param  array $features
     *
     * @return $this
     */
    public function features(array $features): static
    {
        $this->features = $features;

        return $this;
    }

    /**
     * Get a given attribute from the plan.
     *
     * @param  string $key
     * @param  mixed $default
     *
     * @return mixed
     */
    public function attribute($key, $default = null): mixed
    {
        return Arr::get($this->attributes, $key, $default);
    }

    /**
     * Specify the plan's attributes.
     *
     * @param  array $attributes
     *
     * @return $this
     */
    public function attributes(array $attributes): static
    {
        $this->attributes = array_merge($this->attributes, $attributes);

        return $this;
    }


    protected function getFieldExceptionClass(): string
    {
        return PlanFieldsException::class;
    }

    public function getKey(): string
    {
        return $this->name;
    }

    public function getKeyName(): string
    {
        return 'name';
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getPrice(): int
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
            'name' => $this->name,
            'price' => $this->price,
            'currency' => $this->currency
        ];
    }
}
