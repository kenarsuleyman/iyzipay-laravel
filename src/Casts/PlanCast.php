<?php

namespace Iyzico\IyzipayLaravel\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use Iyzico\IyzipayLaravel\StorableClasses\Plan;

class PlanCast implements CastsAttributes
{
    /**
     * Cast the stored JSON to a Plan object.
     * @throws \Exception
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?Plan
    {
        if (is_null($value)) {
            return null;
        }

        $data = is_string($value) ? json_decode($value, true) : $value;

        if (! is_array($data)) {
            return null;
        }

        // Uses the Plan/StorableClass constructor which accepts an array
        return Plan::fromArray($data);
    }

    /**
     * Prepare the Plan object for storage as JSON.
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if (is_null($value)) {
            return null;
        }

        if ($value instanceof Plan || is_array($value)) {
            return json_encode($value);
        }

        if (is_array($value)) {
            return json_encode($value);
        }

        throw new \InvalidArgumentException('The given value is not a Plan instance or array.');
    }
}
