<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Hotel profile defaults
    |--------------------------------------------------------------------------
    |
    | Fallbacks for Settings → Hotel Info. Once an admin saves the form the
    | values live in `site_contents` (see App\Support\HotelSettings) and these
    | are only used for keys that have never been set.
    |
    */

    'name' => env('HOTEL_NAME', 'Retiro Del Rocio'),

    'tagline' => env('HOTEL_TAGLINE', 'Where Stillness Finds You'),

    'address' => env('HOTEL_ADDRESS', '15 Ibrahim Taiwo Road, Jos'),

    'city' => env('HOTEL_CITY', 'Jos, Plateau State'),

    'country' => env('HOTEL_COUNTRY', 'Nigeria'),

    'phone' => env('HOTEL_PHONE', '+234 803 000 0001'),

    'email' => env('HOTEL_EMAIL', 'info@retirodelrocio.com'),

    'description' => env('HOTEL_DESCRIPTION', ''),

    /*
    |--------------------------------------------------------------------------
    | Front-desk policy times
    |--------------------------------------------------------------------------
    |
    | Bookings store check-in / check-out as dates only. These are the hotel's
    | standard arrival and departure times, applied wherever a date is shown as
    | a moment (booking details, the in-room tablet's stay card).
    |
    */

    'check_in_time' => env('HOTEL_CHECK_IN_TIME', '15:00'),

    'check_out_time' => env('HOTEL_CHECK_OUT_TIME', '11:00'),

];
