<?php

namespace App\Modules\Accounting\Services;

use App\Modules\Accounting\Models\JournalEntry;
use App\Modules\Expense\Models\Expense;
use App\Modules\HRM\Models\Payslip;
use App\Modules\Purchase\Models\Purchase;
use App\Modules\Purchase\Models\SupplierPayment;
use App\Modules\Sales\Models\CustomerPayment;
use App\Modules\Sales\Models\Sale;

/**
 * Maps domain events (sale activated, expense paid, payslip paid, ...) into
 * balanced journal entries. Each mapper is idempotent: re-running it for the
 * same source row will not create a second entry.
 *
 * Mappers return null when the source row is not yet in a postable state.
 */
class JournalPostingService
{
    public function __construct(private readonly AccountingService $accounting) {}

    /**
     * SALE → revenue + VAT on credit, cash / bank / receivable on debit.
     *
     *   DR  Kasse / Bank / Forderungen   (total_paid + due_amount)
     *   CR  Umsatzerlöse                 (subtotal - discount + freight)
     *   CR  Umsatzsteuer                 (vat_amount)
     */
    public function postSale(Sale $sale): ?JournalEntry
    {
        if ($sale->status !== 'active') return null;

        $existing = $this->findExisting('sale', $sale->id);
        if ($existing) return $existing;

        $netRevenue = (float) $sale->subtotal - (float) $sale->discount_amount + (float) $sale->freight_amount;
        $vat        = (float) $sale->vat_amount;
        $cash       = (float) $sale->cash_paid;
        $card       = (float) $sale->card_paid;
        $due        = (float) $sale->due_amount;

        $lines = [];
        if ($cash > 0) $lines[] = ['account_code' => '1000', 'debit' => $cash, 'narration' => 'POS Bar'];
        if ($card > 0) $lines[] = ['account_code' => '1100', 'debit' => $card, 'narration' => 'POS Karte'];
        if ($due  > 0) $lines[] = ['account_code' => '1200', 'debit' => $due,  'narration' => 'Offener Betrag'];

        if ($netRevenue > 0) $lines[] = ['account_code' => '4000', 'credit' => $netRevenue];
        if ($vat        > 0) $lines[] = ['account_code' => '2100', 'credit' => $vat, 'narration' => 'Umsatzsteuer'];

        if (count($lines) < 2) return null;

        return $this->accounting->createJournalEntry([
            'entry_date'       => $sale->sale_date,
            'branch_id'        => $sale->branch_id,
            'reference_type'   => 'sale',
            'reference_id'     => $sale->id,
            'reference_number' => $sale->sale_number,
            'narration'        => 'Verkauf ' . $sale->sale_number,
        ], $lines);
    }

    /**
     * PURCHASE → inventory + VAT input on debit, payables on credit.
     *
     *   DR  Vorräte         (subtotal - discount + freight)
     *   DR  Vorsteuer       (vat_amount)
     *   CR  Verbindlichkeiten   (total_amount)
     */
    public function postPurchase(Purchase $purchase): ?JournalEntry
    {
        if ($purchase->status !== 'received') return null;

        $existing = $this->findExisting('purchase', $purchase->id);
        if ($existing) return $existing;

        $goods = (float) $purchase->subtotal - (float) $purchase->discount_amount + (float) $purchase->freight_amount;
        $vat   = (float) $purchase->vat_amount;
        $total = (float) $purchase->total_amount;

        $lines = [];
        if ($goods > 0) $lines[] = ['account_code' => '1300', 'debit' => $goods];
        if ($vat   > 0) $lines[] = ['account_code' => '1400', 'debit' => $vat, 'narration' => 'Vorsteuer'];
        if ($total > 0) $lines[] = ['account_code' => '2000', 'credit' => $total];

        if (count($lines) < 2) return null;

        return $this->accounting->createJournalEntry([
            'entry_date'       => $purchase->purchase_date,
            'branch_id'        => $purchase->branch_id,
            'reference_type'   => 'purchase',
            'reference_id'     => $purchase->id,
            'reference_number' => $purchase->purchase_number,
            'narration'        => 'Wareneingang ' . $purchase->purchase_number,
        ], $lines);
    }

    /**
     * EXPENSE (paid) → expense account on debit, cash or bank on credit.
     *
     *   DR  Aufwandskonto (5xxx)
     *   CR  Kasse / Bank
     */
    public function postExpense(Expense $expense): ?JournalEntry
    {
        if ($expense->status !== 'paid') return null;

        $existing = $this->findExisting('expense', $expense->id);
        if ($existing) return $existing;

        $amount = (float) $expense->amount;
        if ($amount <= 0) return null;

        $expenseCode = $this->expenseCodeForCategory($expense->expense_category_id);
        $payCode     = $this->cashOrBankCode($expense->payment_method);

        return $this->accounting->createJournalEntry([
            'entry_date'       => $expense->expense_date,
            'branch_id'        => $expense->branch_id,
            'reference_type'   => 'expense',
            'reference_id'     => $expense->id,
            'reference_number' => $expense->expense_number,
            'narration'        => 'Ausgabe ' . $expense->expense_number . ' — ' . $expense->title,
        ], [
            ['account_code' => $expenseCode, 'debit'  => $amount, 'narration' => $expense->title],
            ['account_code' => $payCode,     'credit' => $amount],
        ]);
    }

    /**
     * PAYSLIP (paid) → salary expense on debit, cash or bank on credit.
     * Net salary is booked; tax/deduction breakdown can be added later.
     *
     *   DR  Personalaufwand
     *   CR  Kasse / Bank
     */
    public function postPayslip(Payslip $payslip): ?JournalEntry
    {
        if ($payslip->status !== 'paid') return null;

        $existing = $this->findExisting('payslip', $payslip->id);
        if ($existing) return $existing;

        $amount = (float) $payslip->net_salary;
        if ($amount <= 0) return null;

        $payCode = $this->cashOrBankCode($payslip->payment_method);

        return $this->accounting->createJournalEntry([
            'entry_date'       => $payslip->payment_date ?? $payslip->period_end,
            'branch_id'        => $payslip->branch_id,
            'reference_type'   => 'payslip',
            'reference_id'     => $payslip->id,
            'reference_number' => $payslip->payslip_number,
            'narration'        => 'Gehaltszahlung ' . $payslip->payslip_number,
        ], [
            ['account_code' => '5100',    'debit'  => $amount],
            ['account_code' => $payCode,  'credit' => $amount],
        ]);
    }

    /**
     * CUSTOMER PAYMENT → cash or bank on debit, AR on credit.
     */
    public function postCustomerPayment(CustomerPayment $payment): ?JournalEntry
    {
        $existing = $this->findExisting('customer_payment', $payment->id);
        if ($existing) return $existing;

        $amount = (float) $payment->amount;
        if ($amount <= 0) return null;

        $payCode = $this->cashOrBankCode($payment->payment_method);

        return $this->accounting->createJournalEntry([
            'entry_date'       => $payment->payment_date,
            'branch_id'        => $payment->branch_id,
            'reference_type'   => 'customer_payment',
            'reference_id'     => $payment->id,
            'reference_number' => $payment->reference,
            'narration'        => 'Kundenzahlung',
        ], [
            ['account_code' => $payCode, 'debit'  => $amount],
            ['account_code' => '1200',   'credit' => $amount, 'narration' => 'Forderungsausgleich'],
        ]);
    }

    /**
     * SUPPLIER PAYMENT → AP on debit, cash or bank on credit.
     */
    public function postSupplierPayment(SupplierPayment $payment): ?JournalEntry
    {
        $existing = $this->findExisting('supplier_payment', $payment->id);
        if ($existing) return $existing;

        $amount = (float) $payment->amount;
        if ($amount <= 0) return null;

        $payCode = $this->cashOrBankCode($payment->payment_method);

        return $this->accounting->createJournalEntry([
            'entry_date'       => $payment->payment_date,
            'branch_id'        => $payment->branch_id,
            'reference_type'   => 'supplier_payment',
            'reference_id'     => $payment->id,
            'reference_number' => $payment->reference,
            'narration'        => 'Lieferantenzahlung',
        ], [
            ['account_code' => '2000',   'debit'  => $amount, 'narration' => 'Verbindlichkeit ausgleichen'],
            ['account_code' => $payCode, 'credit' => $amount],
        ]);
    }

    // --- helpers -------------------------------------------------------------

    public function findExisting(string $referenceType, int $referenceId): ?JournalEntry
    {
        return JournalEntry::where('reference_type', $referenceType)
            ->where('reference_id', $referenceId)
            ->whereIn('status', ['posted', 'draft'])
            ->first();
    }

    private function cashOrBankCode(?string $method): string
    {
        $m = strtolower((string) $method);
        if (in_array($m, ['cash', 'bar', 'kasse'], true)) return '1000';
        return '1100';
    }

    /**
     * Hook for per-category expense account mapping. For now everything that
     * isn't a well-known category lands in the catch-all 5400. Future work:
     * add an optional `coa_account_id` column to expense_categories.
     */
    private function expenseCodeForCategory(?int $categoryId): string
    {
        return '5400';
    }
}
