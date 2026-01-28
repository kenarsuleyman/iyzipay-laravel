<?php

namespace Iyzico\IyzipayLaravel\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Support\Collection;
use Iyzico\IyzipayLaravel\StorableClasses\Plan;
use Iyzico\IyzipayLaravel\StorableClasses\Product;
use Iyzico\IyzipayLaravel\ProductContract;

class ProductCollectionCast implements CastsAttributes
{
    public function get($model, string $key, $value, array $attributes)
    {
        if (! $value) {
            return collect([]);
        }

        $data = json_decode($value, true);

        return collect($data)->map(function ($item) {
            // If the item has an 'interval' key, it MUST be a Subscription Plan.
            if (isset($item['interval'])) {
                return Plan::fromArray($item);
            }

            // Otherwise, it's a standard Product.
            // We use a similar static factory for Product to keep things clean.
            return Product::fromArray($item);
        });
    }

    public function set($model, string $key, $value, array $attributes)
    {
        // This part remains valid because both Plan and Product implement toArray()
        if ($value instanceof Collection) {
            return $value->map(fn (ProductContract $item) => $item->toArray())->toJson();
        }

        if (is_array($value)) {
            return json_encode($value);
        }

        throw new \InvalidArgumentException('Products must be a Collection of ProductContracts.');
    }
}
