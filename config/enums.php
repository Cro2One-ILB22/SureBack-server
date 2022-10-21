<?php

return [
  'role' => [
    'super_admin' => 'SUPER_ADMIN',
    'admin' => 'ADMIN',
    'user' => 'USER',
    'guest' => 'GUEST',
    'partner' => 'PARTNER',
    'customer' => 'CUSTOMER',
  ],
  'registerable_role' => [
    'partner' => 'PARTNER',
    'customer' => 'CUSTOMER',
  ],
  'story_transaction' => [
    'debit' => 'D',
    'credit' => 'C',
  ],
  'story_status' => [
    'pending' => 0,
    'approved' => 1,
    'rejected' => 2,
  ],
  'cashback_status' => [
    'pending' => 0,
    'approved' => 1,
    'rejected' => 2,
  ],
  'withdrawal_status' => [
    'processing' => 0,
    'success' => 1,
    'failed' => 2,
  ],
];
