<?php

return [
    'required'  => 'The :attribute field is required.',
    'email'     => 'The :attribute must be a valid email address.',
    'unique'    => 'The :attribute has already been taken.',
    'min'       => [
        'string' => 'The :attribute must be at least :min characters.',
    ],
    'max'       => [
        'string' => 'The :attribute must not exceed :max characters.',
    ],
    'confirmed' => 'The :attribute confirmation does not match.',

    'attributes' => [
        'name'      => 'name',
        'email'     => 'email',
        'password'  => 'password',
        'phone'     => 'phone',
        'role'      => 'role',
        'branch_id' => 'branch',
        'code'      => 'code',
        'address'   => 'address',
    ],
];
