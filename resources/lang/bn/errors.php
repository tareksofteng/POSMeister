<?php

return [
    'sales' => [
        'insufficient_stock' => 'অপর্যাপ্ত স্টক: :name',
        'already_cancelled'  => 'এই বিক্রয় ইতোমধ্যে বাতিল করা হয়েছে।',
    ],
    'sale_returns' => [
        'only_active'      => 'শুধু সক্রিয় বিক্রয় ফেরত দেওয়া যাবে।',
        'min_one_quantity' => 'অন্তত একটি ফেরত পরিমাণ লিখুন।',
    ],
    'purchase_returns' => [
        'min_one_quantity' => 'অন্তত একটি ফেরত পরিমাণ লিখুন।',
    ],
    'quotations' => [
        'not_editable'      => 'এই উদ্ধৃতি আর সম্পাদনযোগ্য নয়।',
        'already_converted' => 'এই উদ্ধৃতি ইতোমধ্যে বিক্রয়ে রূপান্তরিত হয়েছে।',
    ],
    'expenses' => [
        'paid_not_editable'    => 'পরিশোধিত খরচ সম্পাদনা করা যাবে না।',
        'paid_not_deletable'   => 'পরিশোধিত খরচ মুছে ফেলা যাবে না।',
        'only_open_approvable' => 'শুধুমাত্র মুক্ত খরচ অনুমোদিত হতে পারে।',
        'paid_not_rejectable'  => 'পরিশোধিত খরচ প্রত্যাখ্যান করা যাবে না।',
        'rejected_not_payable' => 'প্রত্যাখ্যাত খরচ পরিশোধিত হিসাবে চিহ্নিত করা যাবে না।',
        'already_paid'         => 'এই খরচ ইতোমধ্যে পরিশোধিত হিসাবে চিহ্নিত।',
        'paid_not_reopenable'  => 'পরিশোধিত খরচ পুনরায় খোলা যাবে না।',
    ],
    'expense_categories' => [
        'has_expenses' => 'এই বিভাগ মুছে ফেলা যাবে না কারণ এতে খরচ নির্ধারিত আছে।',
    ],
    'loyalty' => [
        'min_redeem' => 'সর্বনিম্ন মুক্তিকরণ :min পয়েন্ট।',
        'not_enough' => 'যথেষ্ট পয়েন্ট নেই।',
    ],
    'budgets' => [
        'active_not_deletable' => 'সক্রিয় বাজেট মুছে ফেলা যাবে না। প্রথমে আর্কাইভ করুন।',
    ],
    'payroll' => [
        'period_locked'      => 'এই বেতন সময় আর সম্পাদনযোগ্য নয়।',
        'paid_not_deletable' => 'পরিশোধিত পেস্লিপ মুছে ফেলা যাবে না।',
    ],
];
