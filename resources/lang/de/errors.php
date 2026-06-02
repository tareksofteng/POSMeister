<?php

return [
    'sales' => [
        'insufficient_stock' => 'Unzureichender Bestand für: :name',
        'already_cancelled'  => 'Dieser Verkauf ist bereits storniert.',
    ],
    'sale_returns' => [
        'only_active'      => 'Nur aktive Verkäufe können zurückgegeben werden.',
        'min_one_quantity' => 'Bitte mindestens eine Rückgabemenge eingeben.',
    ],
    'purchase_returns' => [
        'min_one_quantity' => 'Bitte mindestens eine Rückgabemenge eingeben.',
    ],
    'quotations' => [
        'not_editable'      => 'Dieses Angebot kann nicht mehr bearbeitet werden.',
        'already_converted' => 'Dieses Angebot wurde bereits in einen Verkauf umgewandelt.',
    ],
    'expenses' => [
        'paid_not_editable'    => 'Bezahlte Ausgaben können nicht mehr bearbeitet werden.',
        'paid_not_deletable'   => 'Bezahlte Ausgaben können nicht gelöscht werden.',
        'only_open_approvable' => 'Nur offene Ausgaben können genehmigt werden.',
        'paid_not_rejectable'  => 'Bezahlte Ausgaben können nicht abgelehnt werden.',
        'rejected_not_payable' => 'Abgelehnte Ausgaben können nicht als bezahlt markiert werden.',
        'already_paid'         => 'Diese Ausgabe ist bereits als bezahlt markiert.',
        'paid_not_reopenable'  => 'Bezahlte Ausgaben können nicht erneut geöffnet werden.',
    ],
    'expense_categories' => [
        'has_expenses' => 'Kategorie kann nicht gelöscht werden, da ihr Ausgaben zugeordnet sind.',
    ],
    'loyalty' => [
        'min_redeem' => 'Mindesteinlösung sind :min Punkte.',
        'not_enough' => 'Nicht genügend Punkte.',
    ],
    'budgets' => [
        'active_not_deletable' => 'Aktive Budgets können nicht gelöscht werden. Bitte zuerst archivieren.',
    ],
    'payroll' => [
        'period_locked'      => 'Diese Lohnperiode kann nicht mehr bearbeitet werden.',
        'paid_not_deletable' => 'Bezahlte Abrechnungen können nicht gelöscht werden.',
    ],
];
