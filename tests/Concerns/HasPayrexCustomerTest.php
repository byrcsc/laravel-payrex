<?php

declare(strict_types=1);

use ByRcsc\LaravelPayrex\Data\Customer;
use ByRcsc\LaravelPayrex\Exceptions\CustomerAlreadyCreatedException;
use ByRcsc\LaravelPayrex\Exceptions\CustomerNotCreatedException;
use ByRcsc\LaravelPayrex\PayrexServiceProvider;
use ByRcsc\LaravelPayrex\Tests\Stubs\Merchant;
use ByRcsc\LaravelPayrex\Tests\Stubs\Payer;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

/**
 * @return array<string, mixed>
 */
function customerBody(string $id = 'cus_3QxSample000001'): array
{
    return [
        'resource' => 'customer',
        'id' => $id,
        'name' => 'Joe Dela Cruz',
        'email' => 'joe@example.test',
        'currency' => 'PHP',
    ];
}

beforeEach(function () {
    Schema::create('payers', function ($table) {
        $table->increments('id');
        $table->string('name')->nullable();
        $table->string('email')->nullable();
        $table->string('payrex_customer_id')->nullable();
    });

    Schema::create('merchants', function ($table) {
        $table->increments('id');
        $table->string('trading_name')->nullable();
        $table->string('billing_email')->nullable();
        $table->string('payrex_id')->nullable();
    });
});

afterEach(function () {
    Schema::dropIfExists('payers');
    Schema::dropIfExists('merchants');
});

describe('reading the stored id', function () {
    it('reports no customer when the column is empty', function () {
        $payer = Payer::create(['name' => 'Joe Dela Cruz', 'email' => 'joe@example.test']);

        expect($payer->payrexCustomerId())->toBeNull()
            ->and($payer->hasPayrexCustomerId())->toBeFalse();
    });

    it('treats a blank column as no customer', function () {
        $payer = Payer::create(['name' => 'Joe', 'payrex_customer_id' => '   ']);

        expect($payer->payrexCustomerId())->toBeNull()
            ->and($payer->hasPayrexCustomerId())->toBeFalse();
    });

    it('reads the id once one is stored', function () {
        $payer = Payer::create(['name' => 'Joe', 'payrex_customer_id' => 'cus_1']);

        expect($payer->payrexCustomerId())->toBe('cus_1')
            ->and($payer->hasPayrexCustomerId())->toBeTrue();
    });
});

describe('createAsPayrexCustomer', function () {
    it('registers the model and persists the returned id', function () {
        Http::fake(['*' => Http::response(customerBody())]);
        $payer = Payer::create(['name' => 'Joe Dela Cruz', 'email' => 'joe@example.test']);

        $customer = $payer->createAsPayrexCustomer();

        expect($customer)->toBeInstanceOf(Customer::class)
            ->and($customer->id)->toBe('cus_3QxSample000001')
            ->and($payer->payrexCustomerId())->toBe('cus_3QxSample000001')
            ->and($payer->fresh()?->payrex_customer_id)->toBe('cus_3QxSample000001');

        Http::assertSent(fn (Request $request) => $request->method() === 'POST'
            && $request->url() === 'https://api.payrexhq.test/customers'
            && formBody($request->body()) === [
                'name' => 'Joe Dela Cruz',
                'email' => 'joe@example.test',
                'currency' => 'PHP',
            ]);
    });

    it('merges extra options over the derived parameters', function () {
        Http::fake(['*' => Http::response(customerBody())]);
        $payer = Payer::create(['name' => 'Joe', 'email' => 'joe@example.test']);

        $payer->createAsPayrexCustomer(['metadata' => ['user_id' => '7']]);

        Http::assertSent(fn (Request $request) => formBody($request->body())['metadata'] === ['user_id' => '7']);
    });

    it('refuses to create a second customer for the same model', function () {
        Http::fake(['*' => Http::response(customerBody())]);
        $payer = Payer::create(['name' => 'Joe', 'payrex_customer_id' => 'cus_existing']);

        expect(fn () => $payer->createAsPayrexCustomer())
            ->toThrow(CustomerAlreadyCreatedException::class, 'already the PayRex customer [cus_existing]');

        Http::assertNothingSent();
    });
});

describe('asPayrexCustomer', function () {
    it('retrieves the customer behind the model', function () {
        Http::fake(['*' => Http::response(customerBody('cus_stored'))]);
        $payer = Payer::create(['name' => 'Joe', 'payrex_customer_id' => 'cus_stored']);

        expect($payer->asPayrexCustomer()->id)->toBe('cus_stored');

        Http::assertSent(
            fn (Request $request) => $request->url() === 'https://api.payrexhq.test/customers/cus_stored'
        );
    });

    it('refuses to retrieve a customer the model does not have', function () {
        $payer = Payer::create(['name' => 'Joe']);

        expect(fn () => $payer->asPayrexCustomer())
            ->toThrow(CustomerNotCreatedException::class, 'is not a PayRex customer yet');
    });
});

describe('createOrGetPayrexCustomer', function () {
    it('creates one when the model has none', function () {
        Http::fake(['*' => Http::response(customerBody())]);
        $payer = Payer::create(['name' => 'Joe', 'email' => 'joe@example.test']);

        $payer->createOrGetPayrexCustomer();

        Http::assertSent(fn (Request $request) => $request->method() === 'POST');
    });

    it('retrieves the existing one instead of creating a duplicate', function () {
        Http::fake(['*' => Http::response(customerBody('cus_stored'))]);
        $payer = Payer::create(['name' => 'Joe', 'payrex_customer_id' => 'cus_stored']);

        expect($payer->createOrGetPayrexCustomer()->id)->toBe('cus_stored');

        Http::assertSent(fn (Request $request) => $request->method() === 'GET');
    });
});

describe('updatePayrexCustomer', function () {
    it('pushes the current name and email to PayRex', function () {
        Http::fake(['*' => Http::response(customerBody('cus_stored'))]);
        $payer = Payer::create([
            'name' => 'Joe Dela Cruz-Reyes',
            'email' => 'joe.reyes@example.test',
            'payrex_customer_id' => 'cus_stored',
        ]);

        $payer->updatePayrexCustomer();

        Http::assertSent(fn (Request $request) => $request->method() === 'PUT'
            && $request->url() === 'https://api.payrexhq.test/customers/cus_stored'
            && formBody($request->body()) === [
                'name' => 'Joe Dela Cruz-Reyes',
                'email' => 'joe.reyes@example.test',
            ]);
    });

    it('refuses to update a customer the model does not have', function () {
        $payer = Payer::create(['name' => 'Joe']);

        expect(fn () => $payer->updatePayrexCustomer())->toThrow(CustomerNotCreatedException::class);
    });
});

describe('deleteAsPayrexCustomer', function () {
    it('deletes the customer and clears the stored id', function () {
        Http::fake(['*' => Http::response(['id' => 'cus_stored', 'deleted' => true])]);
        $payer = Payer::create(['name' => 'Joe', 'payrex_customer_id' => 'cus_stored']);

        $deleted = $payer->deleteAsPayrexCustomer();

        expect($deleted->deleted)->toBeTrue()
            ->and($payer->payrexCustomerId())->toBeNull()
            ->and($payer->fresh()?->payrex_customer_id)->toBeNull();

        Http::assertSent(fn (Request $request) => $request->method() === 'DELETE'
            && $request->url() === 'https://api.payrexhq.test/customers/cus_stored');
    });

    it('refuses to delete a customer the model does not have', function () {
        $payer = Payer::create(['name' => 'Joe']);

        expect(fn () => $payer->deleteAsPayrexCustomer())->toThrow(CustomerNotCreatedException::class);
    });
});

describe('payrexPaymentMethods', function () {
    it('lists the payment methods saved against the customer', function () {
        Http::fake(['*' => Http::response([
            'data' => [['resource' => 'payment_method', 'id' => 'pm_1', 'type' => 'card']],
            'has_more' => false,
        ])]);
        $payer = Payer::create(['name' => 'Joe', 'payrex_customer_id' => 'cus_stored']);

        expect($payer->payrexPaymentMethods()->first()?->id)->toBe('pm_1');

        Http::assertSent(
            fn (Request $request) => $request->url() === 'https://api.payrexhq.test/customers/cus_stored/payment_methods'
        );
    });
});

describe('overriding the naming hooks', function () {
    it('uses the model\'s own column, name, and email', function () {
        Http::fake(['*' => Http::response(customerBody('cus_merchant'))]);
        $merchant = Merchant::create([
            'trading_name' => 'Acme Trading',
            'billing_email' => 'billing@acme.test',
        ]);

        $merchant->createAsPayrexCustomer();

        expect($merchant->payrexCustomerId())->toBe('cus_merchant')
            ->and($merchant->fresh()?->payrex_id)->toBe('cus_merchant');

        Http::assertSent(fn (Request $request) => formBody($request->body()) === [
            'name' => 'Acme Trading',
            'email' => 'billing@acme.test',
            'currency' => 'PHP',
        ]);
    });
});

describe('the published migration', function () {
    it('is offered under the package publish tag', function () {
        $paths = ServiceProvider::pathsToPublish(PayrexServiceProvider::class, 'payrex-migrations');

        expect($paths)->toHaveCount(1)
            ->and(array_key_first($paths))->toEndWith('add_payrex_customer_id_column.php.stub')
            ->and(array_values($paths)[0])->toContain('_add_payrex_customer_id_column.php');
    });
});
