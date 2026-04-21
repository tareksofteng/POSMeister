<?php

namespace App\Modules\Sales\Services;

class SaleCalculator
{
    /**
     * Calculate subtotal, VAT, and grand total from cart items.
     *
     * Formula:
     *   subtotal   = Σ qty × unit_price  (net)
     *   vat_amount = Σ qty × unit_price × (tax_rate / 100)  (per item)
     *   grand_total = subtotal − discount + vat_amount + freight
     */
    public static function calculate(array $items, float $discount = 0, float $freight = 0): array
    {
        $subtotal  = 0.0;
        $vatAmount = 0.0;

        foreach ($items as $item) {
            $lineNet    = (float) $item['quantity'] * (float) $item['unit_price'];
            $lineVat    = $lineNet * ((float) ($item['tax_rate'] ?? 0) / 100);
            $subtotal  += $lineNet;
            $vatAmount += $lineVat;
        }

        $grandTotal = $subtotal - $discount + $vatAmount + $freight;

        return [
            'subtotal'    => round($subtotal, 2),
            'vat_amount'  => round($vatAmount, 2),
            'grand_total' => round(max(0.0, $grandTotal), 2),
        ];
    }

    /**
     * Per-line calculation helper (used when building SaleItem records).
     */
    public static function lineAmounts(float $qty, float $unitPrice, float $taxRate): array
    {
        $lineTotal = round($qty * $unitPrice, 2);
        $vatAmount = round($lineTotal * ($taxRate / 100), 2);

        return [
            'line_total' => $lineTotal,
            'vat_amount' => $vatAmount,
        ];
    }
}
