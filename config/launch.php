<?php
return [
    'legal_entity' => env('LAUNCH_LEGAL_ENTITY'),
    'contact_email' => env('LAUNCH_CONTACT_EMAIL'),
    'legal_approved' => filter_var(env('LAUNCH_LEGAL_APPROVED', false), FILTER_VALIDATE_BOOLEAN),
    'pricing_approved' => filter_var(env('LAUNCH_PRICING_APPROVED', false), FILTER_VALIDATE_BOOLEAN),
];
