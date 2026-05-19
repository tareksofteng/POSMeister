<?php

namespace App\Console\Commands;

use App\Modules\Expense\Services\RecurringExpenseService;
use Illuminate\Console\Command;

class GenerateRecurringExpensesCommand extends Command
{
    protected $signature = 'expenses:generate-recurring';
    protected $description = 'Generate due recurring expenses from their templates.';

    public function handle(RecurringExpenseService $service): int
    {
        $count = $service->generateDue();
        $this->info("Generated {$count} recurring expense(s).");
        return self::SUCCESS;
    }
}
