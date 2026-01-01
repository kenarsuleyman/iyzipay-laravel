<?php

namespace Iyzico\IyzipayLaravel\Tests;

use Faker\Generator;
use Iyzico\IyzipayLaravel\StorableClasses\Address;
use Iyzico\IyzipayLaravel\StorableClasses\BillFields;
use Iyzico\IyzipayLaravel\Tests\Models\User;
use Iyzico\IyzipayLaravel\IyzipayLaravelServiceProvider;
use Dotenv\Dotenv;
use Faker\Factory;
use Orchestra\Testbench\TestCase as Orchestra;
use Iyzico\IyzipayLaravel\IyzipayLaravelFacade as IyzipayLaravel;

abstract class TestCase extends Orchestra
{

    protected Generator $faker;

    /**
     * Set up the test environment.
     */
    public function setUp(): void
    {
        if (file_exists(__DIR__ . '/../.env')) {
            $dotenv = Dotenv::createImmutable(__DIR__ . '/../');
            $dotenv->load();
        }

        parent::setUp();

        $this->faker = Factory::create();

        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        $this->loadMigrationsFrom(__DIR__ . '/resources/database/migrations');
    }

    /**
     * Define environment setup.
     */
    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver'   => 'sqlite',
            'database' => ':memory:', // Fast in-memory database
            'prefix'   => '',
        ]);

        // Point to the Test User model
        $app['config']->set('iyzipay.billableModel', User::class);
    }

    public function getPackageProviders($app): array
    {
        return [
            IyzipayLaravelServiceProvider::class,
        ];
    }

    protected function createUser(): User
    {
        return User::create([
            'name' => $this->faker->name
        ]);
    }

    protected function prepareBillFields(): BillFields
    {
        return new BillFields(
            firstName:      $this->faker->firstName,
            lastName:       $this->faker->lastName,
            email:          $this->faker->email,
            identityNumber: $this->faker->bothify(string: str_repeat('#', 11)),
            mobileNumber:   $this->faker->e164PhoneNumber,

            shippingAddress: new Address(
                city:    $this->faker->city,
                country: $this->faker->country,
                address: $this->faker->address
            ),

            billingAddress: new Address(
                city:    $this->faker->city,
                country: $this->faker->country,
                address: $this->faker->address
            )
        );
    }

    protected function prepareBilledUser(): User
    {
        $user = $this->createUser();
        $user->bill_fields = $this->prepareBillFields();

        return $user;
    }

    protected function prepareCreditCardFields(): array
    {
        return [
            'alias' => $this->faker->word,
            'holder' => $this->faker->name,
            'number' => $this->faker->randomElement($this->correctCardNumbers()),
            'month' => '01',
            'year' => '2030'
        ];
    }

    protected function createPlans(): void
    {
        config()->set('iyzipay.plans', [
            'aylik-ucretsiz' => [
                'name' => 'Aylık Ücretisiz',
                'price' => 0,
                'currency' => 'TRY'
            ],
            'aylik-standart' => [
                'name' => 'Aylık Standart',
                'price' => 20,
                'currency' => 'TRY',
                'trialDays' => 15
            ],
            'aylik-platinum' => [
                'name' => 'Aylık Platinum',
                'price' => 40,
                'currency' => 'TRY',
                'trialDays' => 15
            ],
            'yillik-kucuk' => [
                'name' => 'Yıllık Küçük',
                'price' => 150,
                'currency' => 'TRY',
                'trialDays' => 15,
                'interval' => 'yearly'
            ],
        ]);
    }

    protected function correctCardNumbers(): array
    {
        return [
            '5526080000000006',
            '4603450000000000',
            '5311570000000005',
            // Non turkish cards below:
            '5400010000000004',
            '6221060000000004'
        ];
    }
}
