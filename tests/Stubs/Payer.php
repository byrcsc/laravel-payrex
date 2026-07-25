<?php

declare(strict_types=1);

namespace ByRcsc\LaravelPayrex\Tests\Stubs;

use ByRcsc\LaravelPayrex\Concerns\HasPayrexCustomer;
use Illuminate\Database\Eloquent\Model;

/**
 * A minimal model with the PayRex customer trait, for exercising it.
 */
class Payer extends Model
{
    use HasPayrexCustomer;

    protected $table = 'payers';

    protected $guarded = [];

    public $timestamps = false;
}
