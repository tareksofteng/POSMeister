<?php

return [
    'required'  => 'حقل :attribute مطلوب.',
    'email'     => 'يجب أن يكون :attribute عنوان بريد إلكتروني صالحاً.',
    'unique'    => 'قيمة :attribute مستخدمة بالفعل.',
    'min'       => [
        'string' => 'يجب أن يحتوي :attribute على :min أحرف على الأقل.',
    ],
    'max'       => [
        'string' => 'يجب ألا يتجاوز :attribute :max حرفاً.',
    ],
    'confirmed' => 'تأكيد :attribute غير متطابق.',

    'attributes' => [
        'name'      => 'الاسم',
        'email'     => 'البريد الإلكتروني',
        'password'  => 'كلمة المرور',
        'phone'     => 'الهاتف',
        'role'      => 'الدور',
        'branch_id' => 'الفرع',
        'code'      => 'الرمز',
        'address'   => 'العنوان',
    ],
];
