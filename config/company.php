<?php

/*
| Company details printed on salary slips and other documents.
| Set them in .env (COMPANY_NAME, COMPANY_ADDRESS, ...).
*/

return [
    'name' => env('COMPANY_NAME', 'VR Polymers'),
    'address' => env('COMPANY_ADDRESS', ''),
    'phone' => env('COMPANY_PHONE', ''),
    'email' => env('COMPANY_EMAIL', ''),
    'gstin' => env('COMPANY_GSTIN', ''),
];
