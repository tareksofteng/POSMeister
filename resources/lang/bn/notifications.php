<?php

return [
    'serials' => [
        'duplicate' => [
            'title'   => 'ডুপ্লিকেট সিরিয়াল entry প্রচেষ্টা',
            'message' => 'সিরিয়াল নম্বর ":sn" আগে থেকেই registered। entry reject হয়েছে।',
        ],
        'lowStock' => [
            'title'   => 'লো স্টক — :name (অবশিষ্ট :available)',
            'message' => ':name (:sku) এর serialized stock এই branch এ :available unit (reorder level: :threshold)। নতুন purchase plan করুন।',
        ],
        'damagedReturn' => [
            'title'   => 'ক্ষতিগ্রস্ত সিরিয়াল ফেরত',
            'message' => 'সিরিয়াল :sn ক্রেতা ফেরত দিয়েছেন এবং damaged চিহ্নিত হয়েছে। ডিভাইস inspect করে disposal বা repair decide করুন।',
        ],
    ],
];
