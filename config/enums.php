<?php

return [
  'role' => [
    'super_admin' => 'Super Admin',
    'admin' => 'Admin',
    'user' => 'User',
    'partner' => 'Partner',
    'customer' => 'Customer',
  ],
  'otp_factor' => [
    'email',
    'sms',
    'instagram',
  ],
  'registerable_role' => [
    'partner',
    'customer',
  ],
  'transaction_type' => [
    'debit' => 'D',
    'credit' => 'C',
  ],
  'story_status' => [
    'rejected' => 0,
    'approved' => 1,
    'review' => 2,
  ],
];
