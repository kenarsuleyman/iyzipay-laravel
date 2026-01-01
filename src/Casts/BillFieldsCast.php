<?php

namespace Iyzico\IyzipayLaravel\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use Iyzico\IyzipayLaravel\StorableClasses\BillFields;

class BillFieldsCast implements CastsAttributes
{
    /**
     * Cast the given value from database JSON to BillFields object.
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?BillFields
    {
        if (is_null($value)) {
            return null;
        }

        $data = json_decode($value, true);

        return BillFields::fromArray(is_array($data) ? $data : []);
    }

    /**
     * Prepare the BillFields object for storage as JSON.
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if (is_null($value)) {
            return null;
        }

        if ($value instanceof BillFields) {
            return json_encode($value);
        }

        if (is_array($value)) {
            return json_encode($value);
        }

        throw new \InvalidArgumentException('The given value is not a BillFields instance or array.');
    }
}
