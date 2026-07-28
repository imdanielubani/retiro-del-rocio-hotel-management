<?php

namespace App\Support;

/**
 * Nigerian VAT (7.5%) charged on top of a service's price at Paystack payment
 * time, across the whole platform (room bookings, spa, gym, restaurant, cinema
 * and in-room stay extensions). VAT is a tax line the guest pays and the hotel
 * remits — it is never counted as service revenue, only tracked separately.
 */
class Vat
{
    /** The statutory VAT rate. */
    public const RATE = 0.075;

    /** VAT owed on a base amount (naira), rounded to the nearest naira. */
    public static function on(int $base): int
    {
        return (int) round(max(0, $base) * self::RATE);
    }

    /** The base plus its VAT — what the guest actually pays. */
    public static function total(int $base): int
    {
        return max(0, $base) + self::on($base);
    }

    /** "7.5%" for labels. */
    public static function rateLabel(): string
    {
        return rtrim(rtrim(number_format(self::RATE * 100, 1), '0'), '.').'%';
    }
}
