<?php

namespace App\Modules\SystemOps\Controllers;

use App\Modules\Platform\Services\SystemAuditService;
use App\Modules\SystemOps\Services\BackupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class BackupController extends Controller
{
    public function __construct(
        private readonly BackupService       $backup,
        private readonly SystemAuditService  $audit,
    ) {}

    public function status(): JsonResponse
    {
        return response()->json(['data' => [
            'summary' => $this->backup->summary(),
            'runs'    => $this->backup->list(50),
        ]]);
    }

    public function run(Request $request): JsonResponse
    {
        $this->audit->log('backup.requested', null, null, null, ['type' => 'database'], 'Manual database backup requested.');
        $run = $this->backup->runDatabase(
            actorId: $request->user()?->id,
            note: $request->input('note'),
        );

        return response()->json([
            'data' => [
                'id'           => $run->id,
                'type'         => $run->type,
                'status'       => $run->status,
                'file_path'    => $run->file_path,
                'size_bytes'   => $run->size_bytes,
                'checksum'     => $run->checksum_sha256,
                'started_at'   => $run->started_at,
                'finished_at'  => $run->finished_at,
                'error'        => $run->error,
            ],
        ], $run->status === 'success' ? 200 : 500);
    }

    public function prune(): JsonResponse
    {
        $deleted = $this->backup->prune(keep: 14);
        $this->audit->log('backup.pruned', null, null, null, ['deleted' => $deleted], "Pruned {$deleted} old backups.");
        return response()->json(['data' => ['deleted' => $deleted]]);
    }
}
