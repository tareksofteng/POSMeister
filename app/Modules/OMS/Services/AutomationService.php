<?php

namespace App\Modules\OMS\Services;

use App\Modules\OMS\Models\AutomationLog;
use App\Modules\OMS\Models\AutomationRule;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Rule engine. Each rule has a trigger (the "IF") and an action_type (the
 * "THEN"). Triggers are evaluated on demand (e.g. by a scheduled command
 * or via the manual "Run now" endpoint). The result is recorded in
 * automation_logs so admins can see what fired.
 *
 * Built-in trigger evaluators are registered in the constructor — adding
 * a new trigger is one method + one entry in the dispatch map.
 */
class AutomationService
{
    private array $evaluators;

    public function __construct(
        private readonly NotificationService $notifications,
    ) {
        $this->evaluators = [
            'stock.low'                => fn($cfg, $rule) => $this->evalStockLow($cfg, $rule),
            'stock.dead'               => fn($cfg, $rule) => $this->evalStockDead($cfg, $rule),
            'customer.inactive'        => fn($cfg, $rule) => $this->evalCustomerInactive($cfg, $rule),
            'payment.overdue'          => fn($cfg, $rule) => $this->evalPaymentOverdue($cfg, $rule),
            'supplier.delay'           => fn($cfg, $rule) => $this->evalSupplierDelay($cfg, $rule),
            'inventory.negative_risk'  => fn($cfg, $rule) => $this->evalNegativeInventoryRisk($cfg, $rule),
        ];
    }

    public function runRule(AutomationRule $rule): AutomationLog
    {
        $start = now();
        $matchedCount = 0;
        $status = 'no_match';
        $actionResult = null;
        $error = null;

        try {
            $evaluator = $this->evaluators[$rule->trigger] ?? null;
            if (!$evaluator) {
                throw new \RuntimeException("Unknown trigger '{$rule->trigger}'.");
            }
            $matches = $evaluator($rule->condition ?? [], $rule);
            $matchedCount = count($matches);

            if ($matchedCount > 0) {
                $actionResult = $this->dispatchAction($rule, $matches);
                $status = is_array($actionResult) && empty($actionResult['error']) ? 'matched' : 'action_failed';
            }
        } catch (Throwable $e) {
            $status = 'error';
            $error = $e->getMessage();
        }

        $rule->update([
            'last_run_at' => $start,
            'run_count'   => $rule->run_count + 1,
            'match_count' => $rule->match_count + $matchedCount,
        ]);

        return AutomationLog::create([
            'rule_id'       => $rule->id,
            'triggered_at'  => $start,
            'status'        => $status,
            'matched_count' => $matchedCount,
            'action_result' => $actionResult,
            'error'         => $error,
        ]);
    }

    public function runAllActive(): array
    {
        $rules = AutomationRule::active()->get();
        $logs  = [];
        foreach ($rules as $rule) {
            $logs[] = $this->runRule($rule);
        }
        return $logs;
    }

    // --- evaluators ----------------------------------------------------------

    private function evalStockLow(array $cfg, AutomationRule $rule): array
    {
        return DB::table('products as p')
            ->join('inventory as i', 'i.product_id', '=', 'p.id')
            ->where('p.is_active', true)
            ->whereNull('p.deleted_at')
            ->whereColumn('i.quantity', '<=', 'p.reorder_level')
            ->where('p.reorder_level', '>', 0)
            ->when($rule->branch_id, fn($q) => $q->where('i.branch_id', $rule->branch_id))
            ->limit(($cfg['limit'] ?? 200))
            ->get(['p.id', 'p.name', 'p.sku', 'i.quantity', 'p.reorder_level', 'i.branch_id'])
            ->map(fn($r) => (array) $r)->all();
    }

    private function evalStockDead(array $cfg, AutomationRule $rule): array
    {
        $deadDays = (int) ($cfg['days'] ?? 90);
        $cutoff = Carbon::today()->subDays($deadDays)->toDateString();

        return DB::table('products as p')
            ->join('inventory as i', 'i.product_id', '=', 'p.id')
            ->leftJoinSub(
                DB::table('sale_items as si')
                    ->join('sales as s', 's.id', '=', 'si.sale_id')
                    ->where('s.status', 'active')
                    ->selectRaw('si.product_id, MAX(s.sale_date) as last_sale'),
                'l', 'l.product_id', '=', 'p.id'
            )
            ->where('p.is_active', true)
            ->whereNull('p.deleted_at')
            ->where('i.quantity', '>', 0)
            ->where(function ($q) use ($cutoff) {
                $q->where('l.last_sale', '<', $cutoff)->orWhereNull('l.last_sale');
            })
            ->when($rule->branch_id, fn($q) => $q->where('i.branch_id', $rule->branch_id))
            ->groupBy('p.id', 'p.name', 'p.sku', 'i.quantity', 'i.branch_id', 'l.last_sale')
            ->limit(($cfg['limit'] ?? 200))
            ->get(['p.id', 'p.name', 'p.sku', 'i.quantity', 'i.branch_id', 'l.last_sale'])
            ->map(fn($r) => (array) $r)->all();
    }

    private function evalCustomerInactive(array $cfg, AutomationRule $rule): array
    {
        $days = (int) ($cfg['days'] ?? 90);
        $cutoff = Carbon::today()->subDays($days)->toDateString();

        return DB::table('customers as c')
            ->leftJoinSub(
                DB::table('sales')->where('status', 'active')
                    ->selectRaw('customer_id, MAX(sale_date) as last_visit')
                    ->groupBy('customer_id'),
                'sa', 'sa.customer_id', '=', 'c.id'
            )
            ->where('c.is_active', true)
            ->whereNull('c.deleted_at')
            ->where(function ($q) use ($cutoff) {
                $q->where('sa.last_visit', '<', $cutoff)->orWhereNull('sa.last_visit');
            })
            ->when($rule->branch_id, fn($q) => $q->where('c.branch_id', $rule->branch_id))
            ->limit(($cfg['limit'] ?? 200))
            ->get(['c.id', 'c.name', 'c.phone', 'c.email', 'sa.last_visit'])
            ->map(fn($r) => (array) $r)->all();
    }

    private function evalPaymentOverdue(array $cfg, AutomationRule $rule): array
    {
        $days = (int) ($cfg['days'] ?? 30);
        $cutoff = Carbon::today()->subDays($days)->toDateString();

        return DB::table('sales')
            ->where('status', 'active')
            ->where('due_amount', '>', 0)
            ->whereDate('sale_date', '<=', $cutoff)
            ->when($rule->branch_id, fn($q) => $q->where('branch_id', $rule->branch_id))
            ->limit(($cfg['limit'] ?? 200))
            ->get(['id', 'sale_number', 'sale_date', 'customer_id', 'due_amount'])
            ->map(fn($r) => (array) $r)->all();
    }

    private function evalSupplierDelay(array $cfg, AutomationRule $rule): array
    {
        $days = (int) ($cfg['days'] ?? 14);
        $cutoff = Carbon::today()->subDays($days)->toDateString();

        return DB::table('purchases')
            ->where('status', 'draft')
            ->whereNull('deleted_at')
            ->whereDate('purchase_date', '<=', $cutoff)
            ->when($rule->branch_id, fn($q) => $q->where('branch_id', $rule->branch_id))
            ->limit(($cfg['limit'] ?? 200))
            ->get(['id', 'purchase_number', 'supplier_id', 'purchase_date', 'total_amount'])
            ->map(fn($r) => (array) $r)->all();
    }

    private function evalNegativeInventoryRisk(array $cfg, AutomationRule $rule): array
    {
        return DB::table('inventory as i')
            ->join('products as p', 'p.id', '=', 'i.product_id')
            ->where('i.quantity', '<', 0)
            ->whereNull('p.deleted_at')
            ->when($rule->branch_id, fn($q) => $q->where('i.branch_id', $rule->branch_id))
            ->limit(($cfg['limit'] ?? 200))
            ->get(['p.id', 'p.name', 'p.sku', 'i.quantity', 'i.branch_id'])
            ->map(fn($r) => (array) $r)->all();
    }

    // --- action dispatch -----------------------------------------------------

    private function dispatchAction(AutomationRule $rule, array $matches): array
    {
        $cfg = $rule->action_config ?? [];

        return match ($rule->action_type) {
            'notify'           => $this->actionNotify($rule, $matches, $cfg),
            'reorder_suggest'  => ['count' => count($matches), 'note' => 'Reorder suggestion list flagged'],
            'task'             => ['count' => count($matches), 'note' => 'Task creation queued'],
            'risk_flag'        => ['count' => count($matches), 'severity' => $cfg['severity'] ?? 'warning'],
            default            => ['error' => "Unknown action_type {$rule->action_type}"],
        };
    }

    private function actionNotify(AutomationRule $rule, array $matches, array $cfg): array
    {
        $recipientType = $cfg['recipient_type'] ?? 'user';
        $recipientId   = (int) ($cfg['recipient_id'] ?? 0);
        $channel       = $cfg['channel']         ?? 'in_app';
        $templateCode  = $cfg['template_code']   ?? null;

        if ($recipientId <= 0) return ['error' => 'recipient_id missing'];

        try {
            if ($templateCode) {
                $this->notifications->queueTemplated(
                    $templateCode, $recipientType, $recipientId,
                    ['rule_name' => $rule->name, 'matched_count' => count($matches)],
                );
            } else {
                $this->notifications->queue(
                    channel: $channel,
                    recipientType: $recipientType,
                    recipientId: $recipientId,
                    body: $cfg['body'] ?? "Rule '{$rule->name}' matched " . count($matches) . ' items.',
                    subject: $cfg['subject'] ?? $rule->name,
                );
            }
            return ['count' => count($matches), 'channel' => $channel];
        } catch (Throwable $e) {
            return ['error' => $e->getMessage()];
        }
    }
}
