<?php

namespace App\Modules\Accounting\Controllers;

use App\Modules\Accounting\Models\JournalEntry;
use App\Modules\Accounting\Services\AccountingService;
use App\Modules\Branch\Services\BranchContextService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;

class JournalEntryController extends Controller
{
    public function __construct(private readonly AccountingService $accounting) {}

    public function index(Request $request): JsonResponse
    {
        $q = JournalEntry::query()
            ->with(['lines.account:id,account_code,account_name'])
            ->orderByDesc('entry_date')
            ->orderByDesc('id');

        if ($from = $request->input('from')) $q->whereDate('entry_date', '>=', $from);
        if ($to   = $request->input('to'))   $q->whereDate('entry_date', '<=', $to);
        if ($status = $request->input('status')) $q->where('status', $status);
        if ($refType = $request->input('reference_type')) $q->where('reference_type', $refType);

        // Workspace scope first — even an admin in Chattogram must not see
        // Dhaka journals. Explicit ?branch_id= further narrows.
        $q = app(BranchContextService::class)->scopeQuery($q);
        if ($branchId = $request->input('branch_id')) $q->where('branch_id', $branchId);

        $perPage = (int) $request->input('per_page', 25);
        return response()->json($q->paginate($perPage));
    }

    public function show(JournalEntry $entry): JsonResponse
    {
        return response()->json([
            'data' => $entry->load('lines.account:id,account_code,account_name,account_type', 'poster', 'creator'),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'entry_date'     => 'required|date',
            'branch_id'      => 'nullable|integer|exists:branches,id',
            'narration'      => 'nullable|string|max:500',
            'reference_type' => 'nullable|string|max:32',
            'reference_id'   => 'nullable|integer',
            'reference_number' => 'nullable|string|max:64',
            'status'         => 'nullable|in:draft,posted',
            'lines'          => 'required|array|min:2',
            'lines.*.account_id' => 'required|integer|exists:chart_of_accounts,id',
            'lines.*.debit'      => 'nullable|numeric|min:0',
            'lines.*.credit'     => 'nullable|numeric|min:0',
            'lines.*.narration'  => 'nullable|string|max:500',
        ]);

        $entry = $this->accounting->createJournalEntry($data, $data['lines']);
        return response()->json(['data' => $entry], 201);
    }

    public function reverse(JournalEntry $entry, Request $request): JsonResponse
    {
        $data = $request->validate([
            'narration' => 'nullable|string|max:500',
        ]);
        $reverse = $this->accounting->reverse($entry, $data['narration'] ?? null);
        return response()->json(['data' => $reverse]);
    }

    public function destroy(JournalEntry $entry): JsonResponse
    {
        if ($entry->status === 'posted') {
            abort(422, 'Posted entries cannot be deleted. Use reverse instead.');
        }
        $entry->delete();
        return response()->json(['data' => ['ok' => true]]);
    }
}
