# iyzipay-laravel

Iyzipay (by iyzico) integration for Laravel. Handles card storage, single payments, 3D Secure payments, subscriptions with automated recurring billing, refunds, and voids.

You can sign up for an iyzico account at [iyzico.com](https://www.iyzico.com).

## Requirements

- PHP 8.2+
- Laravel 11 or 12

## Installation

```bash
composer require istanbay/iyzipay-laravel
```

Publish the configuration file:

```bash
php artisan vendor:publish --provider="Iyzico\IyzipayLaravel\IyzipayLaravelServiceProvider"
```

Run migrations:

```bash
php artisan migrate
```

Add your iyzipay credentials to `.env`:

```env
IYZIPAY_BASE_URL=https://api.iyzipay.com
IYZIPAY_API_KEY=your-api-key
IYZIPAY_SECRET_KEY=your-secret-key
```

> For the sandbox environment, use `https://sandbox-api.iyzipay.com` as the base URL.

## Configuration

The published config file (`config/iyzipay.php`) contains:

```php
return [
    'baseUrl'       => env('IYZIPAY_BASE_URL', ''),
    'apiKey'        => env('IYZIPAY_API_KEY', ''),
    'secretKey'     => env('IYZIPAY_SECRET_KEY', ''),
    'billableModel' => 'App\Models\User',

    'subscription_plans' => [
        'gold-monthly' => [
            'name'       => 'Gold Membership',
            'price'      => 100,
            'currency'   => 'TRY',
            'interval'   => 'monthly', // 'monthly' or 'yearly'
            'trialDays'  => 7,
            'features'   => ['access_all', 'no_ads'],
        ],
    ],

    'load_migrations' => true,
];
```

Set `billableModel` to whatever model represents your paying user. Set `load_migrations` to `false` if you want to publish and customize the migrations yourself.

## Setting Up the Billable Model

Implement `PayableContract` and use the `Payable` trait on your User model (or whichever model you configured as `billableModel`):

```php
use Iyzico\IyzipayLaravel\Payable;
use Iyzico\IyzipayLaravel\PayableContract;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable implements PayableContract
{
    use Payable;

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
        $this->initializePayable();
    }
}
```

The `initializePayable()` call registers the `bill_fields` JSON cast on the model.

## Bill Fields

Before any payment can be processed, the user must have billing information set. This is required by iyzipay's API.

```php
use Iyzico\IyzipayLaravel\StorableClasses\BillFields;
use Iyzico\IyzipayLaravel\StorableClasses\Address;

$user->bill_fields = new BillFields(
    firstName:      'John',
    lastName:       'Doe',
    email:          'john@example.com',
    identityNumber: '11111111111',
    mobileNumber:   '5551234567',
    shippingAddress: new Address(
        city:    'Istanbul',
        country: 'Turkey',
        address: 'Atasehir, 123 Sokak'
    ),
    billingAddress: new Address(
        city:    'Istanbul',
        country: 'Turkey',
        address: 'Atasehir, 123 Sokak'
    ),
);

$user->save();
```

Check if a user has billing info:

```php
$user->isBillable(); // true or false
```

## Credit Card Management

Cards are tokenized through iyzipay's API. The full card number is never stored in your database.

### Adding a Card

```php
use Iyzico\IyzipayLaravel\DTOs\CardData;

// Using CardData DTO
$card = $user->addCreditCard(new CardData(
    holderName:  'John Doe',
    cardNumber:  '5528790000000008',
    expireMonth: '12',
    expireYear:  '2030',
    alias:       'My Mastercard'
));

// Or using a legacy array
$card = $user->addCreditCard([
    'holder' => 'John Doe',
    'number' => '5528790000000008',
    'month'  => '12',
    'year'   => '2030',
    'alias'  => 'My Mastercard',
]);
```

The returned `CreditCard` model contains:
- `alias` - User-friendly name
- `number` - BIN (first 6 digits)
- `last_four` - Last 4 digits
- `association` - Card brand (VISA, MASTERCARD, etc.)
- `bank` - Issuing bank name
- `token` - iyzipay token (used internally for charges)
- `verified` - Becomes `true` after the first successful payment

### Removing a Card

```php
$user->removeCreditCard($card);
```

### Listing Cards

```php
$user->creditCards; // Collection of CreditCard models
```

## Single Payments (Non-3DS)

For direct charges that don't require 3D Secure verification. Suitable for recurring billing, in-app purchases, or cases where 3DS is not needed.

```php
use Iyzico\IyzipayLaravel\StorableClasses\Product;
use Iyzipay\Model\BasketItemType;

$products = collect([
    new Product(
        id:       'PRODUCT-1',
        name:     'Premium Widget',
        price:    50.00,
        category: 'Widgets',
        type:     BasketItemType::PHYSICAL
    ),
    new Product(
        id:       'PRODUCT-2',
        name:     'Widget Addon',
        price:    25.00,
        category: 'Addons',
        type:     BasketItemType::VIRTUAL
    ),
]);

$transaction = $user->pay($products, $card, currency: 'TRY', installment: 1);

// $transaction->status === TransactionStatus::SUCCESS
// $transaction->amount === '75.00'
// $transaction->iyzipay_key contains the iyzipay payment ID
```

If the payment fails, a `TransactionSaveException` is thrown and the transaction record is saved with `status = FAILED` and `error` containing the failure details.

## 3D Secure Payments

For payments that require bank verification (redirects the user to the bank's 3DS page).

```php
$transaction = new Transaction([
    'amount'      => 100.00,
    'currency'    => 'TRY',
    'installment' => 1,
    'products'    => $products,
    'type'        => TransactionType::CHARGE,
    'status'      => TransactionStatus::PENDING,
]);

$transaction->billable()->associate($user);
$transaction->creditCard()->associate($card);
$transaction->save();

$threedsInitialize = $user->securePay($transaction);

// $threedsInitialize->getHtmlContent() returns the HTML form
// that redirects the user to the bank's 3DS page.
// Render this in the browser.
```

After the user completes bank verification, iyzipay posts back to `/iyzipay/threeds/callback` (handled automatically by the package). The package then fires one of two events:

- `ThreedsCallback` - Payment succeeded
- `ThreedsCancelCallback` - Payment failed or user canceled

Both events carry the `$transaction` model.

> You must define a route named `iyzico.callback` in your application. The package redirects to this route after processing the 3DS callback, passing `transaction` and `request` data via session flash.

## Subscriptions

### Defining Plans

Plans can be defined in the config file (`config/iyzipay.php`) and retrieved by key:

```php
use Iyzico\IyzipayLaravel\StorableClasses\Plan;

$plan = Plan::find('gold-monthly');
```

Or created inline:

```php
$plan = new Plan(
    id:        'pro-monthly',
    name:      'Pro Membership',
    price:     200.00,
    currency:  'TRY',
    interval:  'monthly',
    trialDays: 14,
    features:  ['priority_support', 'advanced_analytics'],
);
```

Plans support fluent building:

```php
$plan = (new Plan(id: 'starter', name: 'Starter'))
    ->price(49.90)
    ->monthly()
    ->trialDays(7)
    ->features(['basic_access']);
```

### Subscribing a User

Subscription creation requires 3D Secure verification:

```php
$threedsInitialize = $user->subscribe($plan, $card);
// Returns ThreedsInitialize - render the HTML to redirect user to bank
```

What happens internally:
1. A `Subscription` record is created with `next_charge_at` calculated from the plan interval (or trial days)
2. A `Transaction` record is created:
   - If the plan has a trial and the card is unverified: a 1.00 TRY **verification** charge
   - Otherwise: a full-price **charge**
3. The user is redirected to 3DS for bank verification
4. On successful callback, the transaction is marked as `SUCCESS` and the card is marked as `verified`

> Verification charges (1.00 TRY) are automatically voided/refunded by the `iyzipay:reverse_verifications` command. See [Artisan Commands](#artisan-commands).

### Checking Subscription Status

```php
$user->isSubscribeTo($plan); // true if active or in grace period

$subscription = $user->subscriptions->first();

$subscription->status;
// SubscriptionStatus::ACTIVE               - Paid and active
// SubscriptionStatus::OVERDUE              - Payment due (next_charge_at has passed)
// SubscriptionStatus::PENDING_CANCELLATION - Canceled but grace period still active
// SubscriptionStatus::CANCELED             - Fully canceled

$subscription->is_trial;      // true if currently in trial period
$subscription->trial_ends_at; // Carbon date when trial ends (null if no trial)
```

Query scopes:

```php
use Iyzico\IyzipayLaravel\Models\Subscription;

Subscription::active()->get();               // Active, not due yet
Subscription::notPaid()->get();              // Overdue, needs charging
Subscription::pendingCancellation()->get();  // Canceled with grace period
Subscription::canceled()->get();             // Fully canceled
```

### Canceling Subscriptions

**Graceful cancellation** - User keeps access until the end of the paid period:

```php
$subscription->cancel();
// Sets canceled_at = next_charge_at
// Status becomes PENDING_CANCELLATION until the period ends
```

**Immediate cancellation** - Access revoked instantly:

```php
$subscription->forceCancel();
// Sets canceled_at = now
// Status becomes CANCELED immediately
```

### Recurring Payments

The package provides a `processDuePayments()` method that finds all overdue subscriptions and charges them automatically using non-3DS payments.

**Schedule the command** in your application however you want:

```php
// In routes/console.php (Laravel 11+)
use Illuminate\Support\Facades\Schedule;

Schedule::command('iyzipay:charge')->hourly();
```

```php
// Or in app/Console/Kernel.php (Laravel 10 and earlier)
protected function schedule(Schedule $schedule)
{
    $schedule->command('iyzipay:charge')->daily();
}
```

You can also call it directly from your own code if needed:

```php
use Iyzico\IyzipayLaravel\IyzipayLaravelFacade as IyzipayLaravel;

IyzipayLaravel::processDuePayments();
```

**How it works:**

1. Queries all subscriptions where `next_charge_at` has passed and the subscription is not canceled
2. For each subscription, picks the owner's most recently added credit card
3. Creates a `Transaction` record (type: `CHARGE`, status: `PENDING`)
4. Calls the non-3DS payment method (`singlePayment`)
5. On **success**: advances `next_charge_at` by 1 month or 1 year (based on plan interval) and fires `SubscriptionCharged`
6. On **failure**: fires `SubscriptionChargeFailed` with the failed transaction (which contains error details)

If the user has no credit card on file, `SubscriptionChargeFailed` is fired with a `null` transaction.

### Listening to Subscription Events

The package fires events but **does not include built-in retry logic**. You decide the retry policy, notification cadence, and cancellation rules in your own listeners.

```php
use Iyzico\IyzipayLaravel\Events\SubscriptionCharged;
use Iyzico\IyzipayLaravel\Events\SubscriptionChargeFailed;

// In a service provider or EventServiceProvider
Event::listen(SubscriptionCharged::class, function (SubscriptionCharged $event) {
    // $event->subscription - the renewed Subscription model
    // $event->transaction  - the successful Transaction model

    // Send receipt, log renewal, etc.
    Mail::to($event->subscription->owner)->send(new PaymentReceipt($event->transaction));
});

Event::listen(SubscriptionChargeFailed::class, function (SubscriptionChargeFailed $event) {
    // $event->subscription - the Subscription that failed to charge
    // $event->transaction  - the failed Transaction (null if no card on file)

    // Error details (when transaction exists):
    // $event->transaction->error['message']

    // Examples of what you can do:
    // - Send dunning email to the user
    // - Retry after a delay using a queued job
    // - Cancel the subscription after N consecutive failures
    // - Log to your own failed_payments table

    Mail::to($event->subscription->owner)->send(new PaymentFailed($event->subscription));
});
```

**Example: Retry with cancellation after 3 failures**

```php
Event::listen(SubscriptionChargeFailed::class, function (SubscriptionChargeFailed $event) {
    $subscription = $event->subscription;

    // Count recent consecutive failures
    $failureCount = $subscription->transactions()
        ->where('status', TransactionStatus::FAILED)
        ->where('created_at', '>=', $subscription->next_charge_at)
        ->count();

    if ($failureCount >= 3) {
        $subscription->forceCancel();
        Mail::to($subscription->owner)->send(new SubscriptionCanceledDueToPayment($subscription));
        return;
    }

    // Notify the user
    Mail::to($subscription->owner)->send(new PaymentFailed($subscription, $failureCount));
});
```

## Refunds and Voids

### Voiding a Transaction (Same Day)

Voids cancel the transaction before settlement. Only works on the same day as the original charge.

```php
$transaction->cancel();
```

### Refunding a Transaction

Full refund:

```php
$transaction->refund();
```

Partial refund:

```php
$transaction->refund(50.00);
```

Multiple partial refunds are supported. Each refund is recorded in the transaction's `refunds` array. The transaction status updates to `PARTIAL_REFUNDED` or `REFUNDED` automatically.

```php
$transaction->refunded_amount; // Total amount refunded so far
$transaction->refunds;         // Array of all refund records
```

You can also call refund/void through the facade:

```php
IyzipayLaravel::cancel($transaction);
IyzipayLaravel::refund($transaction, 50.00);
```

## Artisan Commands

| Command | Description |
|---------|-------------|
| `iyzipay:charge` | Process all due subscription payments. Schedule this to run daily (or at your preferred frequency). |
| `iyzipay:reverse_verifications` | Voids or refunds 1 TRY card verification charges from trial subscriptions. Schedule this to run daily. |
| `iyzipay:publish-upgrade` | Publishes the v1 to v2 upgrade migration. Only needed when upgrading from v1.x. |

Recommended scheduler setup:

```php
Schedule::command('iyzipay:charge')->daily();
Schedule::command('iyzipay:reverse_verifications')->daily();
```

## Events Reference

| Event | Payload | When |
|-------|---------|------|
| `SubscriptionCharged` | `$subscription`, `$transaction` | Recurring payment succeeded |
| `SubscriptionChargeFailed` | `$subscription`, `$transaction` (nullable) | Recurring payment failed |
| `ThreedsCallback` | `$transaction` | 3DS payment succeeded |
| `ThreedsCancelCallback` | `$transaction` | 3DS payment failed or canceled |

All events are in the `Iyzico\IyzipayLaravel\Events` namespace.

## Upgrading from v1.x

If you're upgrading from version 1.x to 2.0, please follow these steps:

1. **Backup your database**
2. Update the package: `composer require istanbay/iyzipay-laravel:^2.0`
3. Publish the upgrade migration: `php artisan iyzipay:publish-upgrade`
4. Run migrations: `php artisan migrate`
5. Verify your data and test functionality

For detailed upgrade instructions, troubleshooting, and rollback procedures, see **[UPGRADE.md](UPGRADE.md)**.

## Author

Originally developed by Mehmet Aydin Bahadir. Now officially maintained by iyzico.

## License

MIT
