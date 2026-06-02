<?php

return [
    'sales' => [
        'insufficient_stock' => 'مخزون غير كافٍ لـ: :name',
        'already_cancelled'  => 'هذه عملية البيع ملغاة بالفعل.',
    ],
    'sale_returns' => [
        'only_active'      => 'يمكن إرجاع المبيعات النشطة فقط.',
        'min_one_quantity' => 'يرجى إدخال كمية إرجاع واحدة على الأقل.',
    ],
    'purchase_returns' => [
        'min_one_quantity' => 'يرجى إدخال كمية إرجاع واحدة على الأقل.',
    ],
    'quotations' => [
        'not_editable'      => 'لا يمكن تعديل هذا العرض بعد الآن.',
        'already_converted' => 'تم تحويل هذا العرض بالفعل إلى عملية بيع.',
    ],
    'expenses' => [
        'paid_not_editable'    => 'لا يمكن تعديل المصروفات المدفوعة.',
        'paid_not_deletable'   => 'لا يمكن حذف المصروفات المدفوعة.',
        'only_open_approvable' => 'يمكن اعتماد المصروفات المفتوحة فقط.',
        'paid_not_rejectable'  => 'لا يمكن رفض المصروفات المدفوعة.',
        'rejected_not_payable' => 'لا يمكن وضع علامة "مدفوعة" على المصروفات المرفوضة.',
        'already_paid'         => 'هذه المصروفات مدفوعة بالفعل.',
        'paid_not_reopenable'  => 'لا يمكن إعادة فتح المصروفات المدفوعة.',
    ],
    'expense_categories' => [
        'has_expenses' => 'لا يمكن حذف هذه الفئة لأن لها مصروفات معيّنة.',
    ],
    'loyalty' => [
        'min_redeem' => 'الحد الأدنى للاستبدال :min نقطة.',
        'not_enough' => 'لا توجد نقاط كافية.',
    ],
    'budgets' => [
        'active_not_deletable' => 'لا يمكن حذف الميزانيات النشطة. يرجى أرشفتها أولاً.',
    ],
    'payroll' => [
        'period_locked'      => 'لا يمكن تعديل فترة الرواتب هذه بعد الآن.',
        'paid_not_deletable' => 'لا يمكن حذف الإيصالات المدفوعة.',
    ],
];
