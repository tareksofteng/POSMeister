<?php

namespace App\Modules\SystemOps\Controllers;

use App\Modules\SystemOps\Services\DeploymentService;
use App\Modules\SystemOps\Services\EnvironmentValidationService;
use App\Modules\SystemOps\Services\QueueDiagnosticsService;
use App\Modules\SystemOps\Services\SchedulerDiagnosticsService;
use App\Modules\SystemOps\Services\SystemInformationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

class SystemOpsController extends Controller
{
    public function __construct(
        private readonly SystemInformationService    $info,
        private readonly EnvironmentValidationService $env,
        private readonly QueueDiagnosticsService     $queue,
        private readonly SchedulerDiagnosticsService $scheduler,
        private readonly DeploymentService           $deployment,
    ) {}

    public function dashboard(): JsonResponse
    {
        return response()->json(['data' => $this->info->dashboard()]);
    }

    public function environment(): JsonResponse
    {
        return response()->json(['data' => $this->env->run()]);
    }

    public function queue(): JsonResponse
    {
        return response()->json(['data' => [
            'snapshot' => $this->queue->snapshot(),
            'pending'  => $this->queue->pendingJobs(),
            'failed'   => $this->queue->failedJobs(),
        ]]);
    }

    public function scheduler(): JsonResponse
    {
        return response()->json(['data' => $this->scheduler->snapshot()]);
    }

    public function deployment(): JsonResponse
    {
        return response()->json(['data' => $this->deployment->info()]);
    }

    public function version(): JsonResponse
    {
        return response()->json(['data' => [
            'version' => $this->deployment->buildVersion(),
            'php'     => PHP_VERSION,
            'laravel' => app()->version(),
        ]]);
    }

    public function pwa(): JsonResponse
    {
        return response()->json(['data' => [
            'enabled'        => true,
            'manifest'       => '/manifest.webmanifest',
            'service_worker' => '/sw.js',
            'scope'          => '/',
            'updated_at'     => now()->toIso8601String(),
        ]]);
    }
}
