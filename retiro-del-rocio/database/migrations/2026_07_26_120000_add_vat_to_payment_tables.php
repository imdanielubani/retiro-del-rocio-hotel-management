<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * VAT (7.5%) collected on top of each service's price at Paystack payment time.
 * Stored separately from the folio amount so revenue stays net of tax and the
 * total collected VAT can be reported. Existing rows default to 0.
 */
return new class extends Migration
{
    /** table => the column the VAT sits after. */
    private array $tables = [
        'bookings' => 'amount',
        'spa_bookings' => 'total',
        'gym_memberships' => 'price',
        'restaurant_reservations' => 'fee',
        'cinema_bookings' => 'amount',
    ];

    public function up(): void
    {
        foreach ($this->tables as $table => $after) {
            Schema::table($table, function (Blueprint $t) use ($after) {
                $t->unsignedInteger('vat')->default(0)->after($after);
            });
        }
    }

    public function down(): void
    {
        foreach (array_keys($this->tables) as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->dropColumn('vat');
            });
        }
    }
};
