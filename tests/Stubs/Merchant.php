<?php

declare(strict_types=1);

namespace ByRcsc\LaravelPayrex\Tests\Stubs;

use ByRcsc\LaravelPayrex\Concerns\HasPayrexCustomer;
use Illuminate\Database\Eloquent\Model;

/**
 * A model that keeps its PayRex details under different column names, to prove
 * every naming hook on the trait is overridable.
 */
class Merchant extends Model
{
    use HasPayrexCustomer;

    protected $table = 'merchants';

    protected $guarded = [];

    public $timestamps = false;

    public function payrexCustomerIdColumn(): string
    {
        return 'payrex_id';
    }

    public function payrexCustomerName(): string
    {
        $name = $this->getAttribute('trading_name');

        return is_string($name) ? $name : '';
    }

    public function payrexCustomerEmail(): string
    {
        $email = $this->getAttribute('billing_email');

        return is_string($email) ? $email : '';
    }
}
