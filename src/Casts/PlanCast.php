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

        $data = json_decode($value, true);

        // Uses the Plan/StorableClass constructor which accepts an array
        return new Plan(is_array($data) ? $data : []);
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

        throw new \InvalidArgumentException('The given value is not a Plan instance or array.');
    }
}
