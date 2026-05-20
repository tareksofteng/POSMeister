<?php

namespace App\Modules\Accounting\Controllers;

use App\Modules\Accounting\Services\AccountingReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AccountingReportController extends Controller
{
    public function __construct(private readonly AccountingReportService $reports) {}

    public function dashboard(Request $request): JsonResponse
    {
        $data = $request->validate([
            'as_of'     => 'nullable|date',
            'branch_id' => 'nullable|integer|exists:branches,id',
        ]);
        return response()->json([
            'data' => $this->reports->dashboard($data['as_of'] ?? null, $data['branch_id'] ?? null),
        ]);
    }

    public function ledger(Request $request, int $accountId): JsonResponse|StreamedResponse
    {
        $data = $request->validate([
            'from'      => 'required|date',
            'to'        => 'required|date|after_or_equal:from',
            'branch_id' => 'nullable|integer|exists:branches,id',
            'format'    => 'nullable|in:json,csv',
        ]);

        $report = $this->reports->ledger($accountId, $data['from'], $data['to'], $data['branch_id'] ?? null);

        if (($data['format'] ?? 'json') === 'csv') {
            return $this->ledgerCsv($report);
        }
        return response()->json(['data' => $report]);
    }

    public function trialBalance(Request $request): JsonResponse|StreamedResponse
    {
        $data = $request->validate([
            'as_of'     => 'required|date',
            'branch_id' => 'nullable|integer|exists:branches,id',
            'format'    => 'nullable|in:json,csv',
        ]);

        $report = $this->reports->trialBalance($data['as_of'], $data['branch_id'] ?? null);

        if (($data['format'] ?? 'json') === 'csv') {
            return $this->trialBalanceCsv($report);
        }
        return response()->json(['data' => $report]);
    }

    public function profitLoss(Request $request): JsonResponse|StreamedResponse
    {
        $data = $request->validate([
            'from'      => 'required|date',
            'to'        => 'required|date|after_or_equal:from',
            'branch_id' => 'nullable|integer|exists:branches,id',
            'format'    => 'nullable|in:json,csv',
        ]);

        $report = $this->reports->profitLoss($data['from'], $data['to'], $data['branch_id'] ?? null);

        if (($data['format'] ?? 'json') === 'csv') {
            return $this->profitLossCsv($report);
        }
        return response()->json(['data' => $report]);
    }

    public function balanceSheet(Request $request): JsonResponse|StreamedResponse
    {
        $data = $request->validate([
            'as_of'     => 'required|date',
            'branch_id' => 'nullable|integer|exists:branches,id',
            'format'    => 'nullable|in:json,csv',
        ]);

        $report = $this->reports->balanceSheet($data['as_of'], $data['branch_id'] ?? null);

        if (($data['format'] ?? 'json') === 'csv') {
            return $this->balanceSheetCsv($report);
        }
        return response()->json(['data' => $report]);
    }

    public function cashbook(Request $request, int $accountId): JsonResponse
    {
        $data = $request->validate([
            'from'      => 'required|date',
            'to'        => 'required|date|after_or_equal:from',
            'branch_id' => 'nullable|integer|exists:branches,id',
        ]);

        return response()->json([
            'data' => $this->reports->cashbook($accountId, $data['from'], $data['to'], $data['branch_id'] ?? null),
        ]);
    }

    // --- CSV streamers (UTF-8 BOM + semicolons + dd.mm.yyyy + German decimals) ---

    private function ledgerCsv(array $report): StreamedResponse
    {
        $filename = "ledger_{$report['account']['code']}_{$report['period']['from']}_{$report['period']['to']}.csv";
        return $this->stream($filename, function () use ($report) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Datum', 'Beleg', 'Buchungstext', 'Soll', 'Haben', 'Saldo'], ';');
            foreach ($report['lines'] as $l) {
                fputcsv($out, [
                    $this->de($l['entry_date']),
                    $l['entry_number'],
                    $l['narration'] ?? '',
                    $this->money($l['debit']),
                    $this->money($l['credit']),
                    $this->money($l['running_balance']),
                ], ';');
            }
            fputcsv($out, ['', '', 'Summen', $this->money($report['debit_total']), $this->money($report['credit_total']), $this->money($report['closing'])], ';');
            fclose($out);
        });
    }

    private function trialBalanceCsv(array $report): StreamedResponse
    {
        $filename = "trial_balance_{$report['as_of']}.csv";
        return $this->stream($filename, function () use ($report) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Kontonr.', 'Konto', 'Soll', 'Haben'], ';');
            foreach ($report['rows'] as $r) {
                fputcsv($out, [
                    $r['account_code'],
                    $r['account_name'],
                    $this->money($r['debit_side']),
                    $this->money($r['credit_side']),
                ], ';');
            }
            fputcsv($out, ['', 'Summen', $this->money($report['total_debit']), $this->money($report['total_credit'])], ';');
            fclose($out);
        });
    }

    private function profitLossCsv(array $report): StreamedResponse
    {
        $filename = "profit_loss_{$report['period']['from']}_{$report['period']['to']}.csv";
        return $this->stream($filename, function () use ($report) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Kategorie', 'Konto', 'Betrag'], ';');
            foreach ($report['revenue'] as $r) {
                fputcsv($out, ['Erlöse', $r['account_code'] . ' ' . $r['account_name'], $this->money($r['amount'])], ';');
            }
            fputcsv($out, ['', 'Erlöse Summe', $this->money($report['revenue_total'])], ';');
            foreach ($report['expense'] as $r) {
                fputcsv($out, ['Aufwand', $r['account_code'] . ' ' . $r['account_name'], $this->money($r['amount'])], ';');
            }
            fputcsv($out, ['', 'Aufwand Summe', $this->money($report['expense_total'])], ';');
            fputcsv($out, ['', 'Ergebnis', $this->money($report['net_profit'])], ';');
            fclose($out);
        });
    }

    private function balanceSheetCsv(array $report): StreamedResponse
    {
        $filename = "balance_sheet_{$report['as_of']}.csv";
        return $this->stream($filename, function () use ($report) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Kategorie', 'Konto', 'Betrag'], ';');
            foreach (['assets' => 'Aktiva', 'liabilities' => 'Passiva (Verbindlichkeiten)', 'equity' => 'Eigenkapital'] as $k => $label) {
                foreach ($report[$k] as $r) {
                    fputcsv($out, [$label, $r['account_code'] . ' ' . $r['account_name'], $this->money($r['amount'])], ';');
                }
            }
            fputcsv($out, ['', 'Aktiva Summe',  $this->money($report['asset_total'])], ';');
            fputcsv($out, ['', 'Passiva Summe', $this->money($report['liability_total'])], ';');
            fputcsv($out, ['', 'Eigenkapital Summe', $this->money($report['equity_total'])], ';');
            fputcsv($out, ['', 'Jahresergebnis', $this->money($report['ytd_profit'])], ';');
            fclose($out);
        });
    }

    private function stream(string $filename, callable $callback): StreamedResponse
    {
        return response()->streamDownload($callback, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function de(string $date): string
    {
        return \Illuminate\Support\Carbon::parse($date)->format('d.m.Y');
    }

    private function money(float $amount): string
    {
        return number_format($amount, 2, ',', '.');
    }
}
