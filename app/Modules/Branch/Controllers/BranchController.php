<?php

namespace App\Modules\Branch\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Branch\Models\Branch;
use App\Modules\Branch\Requests\StoreBranchRequest;
use App\Modules\Branch\Requests\UpdateBranchRequest;
use App\Modules\Branch\Resources\BranchResource;
use App\Modules\Branch\Services\BranchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class BranchController extends Controller
{
    public function __construct(private BranchService $service) {}

    /**
     * GET /api/branches
     * Query params: search, is_active, per_page
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $branches = $this->service->paginate($request->all());

        return BranchResource::collection($branches);
    }

    /**
     * GET /api/branches/all — lightweight list for dropdowns
     */
    public function all(): JsonResponse
    {
        return response()->json([
            'data' => $this->service->allActive(),
        ]);
    }

    /**
     * POST /api/branches
     */
    public function store(StoreBranchRequest $request): JsonResponse
    {
        $branch = $this->service->create($request->validated());

        return (new BranchResource($branch))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * GET /api/branches/{branch}
     */
    public function show(Branch $branch): BranchResource
    {
        return new BranchResource($branch->loadCount('users'));
    }

    /**
     * PUT /api/branches/{branch}
     */
    public function update(UpdateBranchRequest $request, Branch $branch): BranchResource
    {
        $branch = $this->service->update($branch, $request->validated());

        return new BranchResource($branch);
    }

    /**
     * DELETE /api/branches/{branch}
     */
    public function destroy(Branch $branch): JsonResponse
    {
        try {
            $this->service->delete($branch);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['message' => 'Branch deleted successfully.']);
    }
}
