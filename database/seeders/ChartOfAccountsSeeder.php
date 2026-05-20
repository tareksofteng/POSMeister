<?php

namespace Database\Seeders;

use App\Modules\Accounting\Models\ChartOfAccount;
use Illuminate\Database\Seeder;

/**
 * Seeds a German-style starter chart of accounts. Numbering loosely follows
 * the SKR03/SKR04 spirit (asset 1xxx, liability 2xxx, equity 3xxx, revenue 4xxx,
 * expense 5xxx) but kept short and pragmatic for SME POS use.
 *
 * Codes marked is_system=true are referenced from auto-posting logic and
 * must remain stable.
 */
class ChartOfAccountsSeeder extends Seeder
{
    public function run(): void
    {
        $accounts = [
            // Assets
            ['1000', 'Kasse',                        'asset',     'debit',  true],
            ['1100', 'Bank',                         'asset',     'debit',  true],
            ['1200', 'Forderungen aus L.u.L.',       'asset',     'debit',  true],
            ['1300', 'Vorräte',                      'asset',     'debit',  true],
            ['1400', 'Vorsteuer',                    'asset',     'debit',  true],

            // Liabilities
            ['2000', 'Verbindlichkeiten aus L.u.L.', 'liability', 'credit', true],
            ['2100', 'Umsatzsteuer',                 'liability', 'credit', true],
            ['2200', 'Lohnverbindlichkeiten',        'liability', 'credit', false],

            // Equity
            ['3000', 'Eigenkapital',                 'equity',    'credit', false],
            ['3100', 'Gewinnvortrag',                'equity',    'credit', false],

            // Revenue
            ['4000', 'Umsatzerlöse',                 'revenue',   'credit', true],
            ['4100', 'Dienstleistungserlöse',        'revenue',   'credit', false],
            ['4900', 'Sonstige Erträge',             'revenue',   'credit', false],

            // Expenses
            ['5000', 'Wareneinsatz',                 'expense',   'debit',  true],
            ['5100', 'Personalaufwand',              'expense',   'debit',  true],
            ['5200', 'Mietaufwand',                  'expense',   'debit',  false],
            ['5300', 'Energie- und Nebenkosten',     'expense',   'debit',  false],
            ['5400', 'Sonstige betriebliche Aufwendungen', 'expense', 'debit', true],
            ['5500', 'Bürobedarf',                   'expense',   'debit',  false],
            ['5600', 'Marketing & Werbung',          'expense',   'debit',  false],
            ['5700', 'Reisekosten',                  'expense',   'debit',  false],
            ['5800', 'Kommunikation & IT',           'expense',   'debit',  false],
        ];

        foreach ($accounts as [$code, $name, $type, $balance, $isSystem]) {
            ChartOfAccount::updateOrCreate(
                ['account_code' => $code],
                [
                    'account_name'       => $name,
                    'account_type'       => $type,
                    'normal_balance'     => $balance,
                    'is_system'          => $isSystem,
                    'is_active'          => true,
                    'allow_manual_entry' => true,
                ],
            );
        }

        $this->command->info('Chart of accounts seeded (' . count($accounts) . ' accounts).');
    }
}
