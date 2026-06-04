<?php

/**
 * Backend business-rule exception messages, keyed for translation.
 * Used by services like:
 *
 *   throw new \RuntimeException(__('errors.sales.insufficient_stock', ['name' => $name]));
 *
 * Loader picks the right file per App::getLocale(), which is set
 * by SetLocaleMiddleware from the request.
 */
return [
    'sales' => [
        'insufficient_stock' => 'Insufficient stock for: :name',
        'already_cancelled'  => 'This sale is already cancelled.',
    ],
    'sale_returns' => [
        'only_active'      => 'Only active sales can be returned.',
        'min_one_quantity' => 'Please enter at least one return quantity.',
    ],
    'purchase_returns' => [
        'min_one_quantity' => 'Please enter at least one return quantity.',
    ],
    'quotations' => [
        'not_editable'      => 'This quotation can no longer be edited.',
        'already_converted' => 'This quotation has already been converted into a sale.',
    ],
    'expenses' => [
        'paid_not_editable'      => 'Paid expenses can no longer be edited.',
        'paid_not_deletable'     => 'Paid expenses cannot be deleted.',
        'only_open_approvable'   => 'Only open expenses can be approved.',
        'paid_not_rejectable'    => 'Paid expenses cannot be rejected.',
        'rejected_not_payable'   => 'Rejected expenses cannot be marked as paid.',
        'already_paid'           => 'This expense is already marked as paid.',
        'paid_not_reopenable'    => 'Paid expenses cannot be reopened.',
    ],
    'expense_categories' => [
        'has_expenses' => 'This category cannot be deleted because it has expenses assigned.',
    ],
    'loyalty' => [
        'min_redeem'      => 'Minimum redemption is :min points.',
        'not_enough'      => 'Not enough loyalty points.',
    ],
    'budgets' => [
        'active_not_deletable' => 'Active budgets cannot be deleted. Archive them first.',
    ],
    'payroll' => [
        'period_locked'  => 'This payroll period can no longer be edited.',
        'paid_not_deletable' => 'Paid payslips cannot be deleted.',
    ],
    'serials' => [
        'product_not_serialized'   => 'This product is not configured for serial-number tracking.',
        'count_mismatch'           => 'You provided :have serial number(s) but :expect were expected.',
        'duplicate_in_batch'       => 'Duplicate serial numbers found in this batch.',
        'duplicate_in_system'      => 'Serial number ":sn" already exists in the system.',
        'unknown_serial'           => 'One or more selected serial numbers could not be found.',
        'not_in_stock'             => 'Serial ":sn" is not in stock and cannot be sold.',
        'not_sold'                 => 'Serial ":sn" has no recorded sale to return.',
        'not_on_sale'              => 'Serial ":sn" was not part of the original sale.',
        'wrong_branch'             => 'Serial ":sn" belongs to a different branch.',
        'no_serials_selected'      => 'Please select at least one serial number.',
        'serialization_locked'     => 'Serial-number tracking cannot be changed once a product has serial history.',
        'purchase_already_has_serials' => 'This purchase already has different serials attached. Refusing to mix them.',
    ],
];
