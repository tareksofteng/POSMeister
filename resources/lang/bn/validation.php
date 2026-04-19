<?php

return [
    'required'  => ':attribute ক্ষেত্রটি প্রয়োজনীয়।',
    'email'     => ':attribute অবশ্যই একটি বৈধ ইমেইল ঠিকানা হতে হবে।',
    'unique'    => ':attribute ইতিমধ্যে ব্যবহৃত হয়েছে।',
    'min'       => [
        'string' => ':attribute অবশ্যই কমপক্ষে :min অক্ষরের হতে হবে।',
    ],
    'max'       => [
        'string' => ':attribute :max অক্ষরের বেশি হতে পারবে না।',
    ],
    'confirmed' => ':attribute নিশ্চিতকরণ মেলে না।',

    'attributes' => [
        'name'      => 'নাম',
        'email'     => 'ইমেইল',
        'password'  => 'পাসওয়ার্ড',
        'phone'     => 'ফোন',
        'role'      => 'ভূমিকা',
        'branch_id' => 'শাখা',
        'code'      => 'কোড',
        'address'   => 'ঠিকানা',
    ],
];
