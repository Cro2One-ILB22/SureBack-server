<?php

return [
  'role' => [
    'super_admin' => 'Super Admin',
    'admin' => 'Admin',
    'user' => 'User',
    'merchant' => 'Merchant',
    'customer' => 'Customer',
  ],
  'otp_factor' => [
    'email',
    'sms',
    'instagram',
  ],
  'registerable_role' => [
    'merchant',
    'customer',
  ],
  'transaction_type' => [
    'debit' => 'D',
    'credit' => 'C',
  ],
  'story_approval_status' => [
    'rejected' => 0,
    'approved' => 1,
    'review' => 2,
  ],
  'story_status' => [
    'uploaded' => 'uploaded',
    'validated' => 'validated',
    'deleted' => 'deleted',
  ],
  'payment_instrument' => [
    'coins' => 'coins',
    'balance' => 'balance',
  ],
];
