<?php

/*
| Statutory rates used when payroll is generated (India). Change them here when
| the government revises them; payslips already generated keep their amounts.
*/

return [
    // Provident Fund: % of earned Basic, on wages up to the ceiling.
    'pf' => [
        'employee_rate' => 12,
        'employer_rate' => 12,
        'wage_ceiling' => 15000,
    ],

    // ESI: % of gross earnings, for employees whose monthly salary is within the limit.
    'esi' => [
        'employee_rate' => 0.75,
        'employer_rate' => 3.25,
        'wage_limit' => 21000,
    ],

    // Default monthly Professional Tax for new employees (varies by state; editable per employee).
    'default_professional_tax' => 200,

    // Overtime multiple of the hourly rate (salary ÷ days in month ÷ standard hours) when an
    // employee has no fixed OT rate; standard hours per day used for that rate.
    'overtime_multiplier' => 2,
    'standard_hours_per_day' => 8,
];
