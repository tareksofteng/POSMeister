<?php

namespace App\Modules\HRM\Controllers;

use App\Modules\HRM\Models\Employee;
use App\Modules\HRM\Requests\StoreEmployeeRequest;
use App\Modules\HRM\Requests\UpdateEmployeeRequest;
use App\Modules\HRM\Resources\EmployeeResource;
use App\Modules\HRM\Services\EmployeeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;

class EmployeeController extends Controller
{
    public function __construct(private readonly EmployeeService $service) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        return EmployeeResource::collection(
            $this->service->paginate($request->only([
                'search', 'department_id', 'designation_id', 'branch_id',
                'status', 'employment_type', 'per_page',
            ]))
        );
    }

    public function stats(): JsonResponse
    {
        return response()->json(['data' => $this->service->stats()]);
    }

    public function show(Employee $employee): EmployeeResource
    {
        return new EmployeeResource($this->service->find($employee->id));
    }

    public function store(StoreEmployeeRequest $request): EmployeeResource
    {
        $photo = $request->file('photo');
        $data  = $request->safe()->except('photo');

        $employee = $this->service->store($data, $photo);

        return new EmployeeResource($employee);
    }

    public function update(UpdateEmployeeRequest $request, Employee $employee): EmployeeResource
    {
        $photo = $request->file('photo');
        $data  = $request->safe()->except('photo');

        $updated = $this->service->update($employee, $data, $photo);

        return new EmployeeResource($updated);
    }

    public function setStatus(Request $request, Employee $employee): EmployeeResource
    {
        $request->validate([
            'status' => 'required|in:active,inactive,terminated,resigned',
        ]);

        return new EmployeeResource(
            $this->service->setStatus($employee, $request->string('status')->value())
        );
    }

    public function destroy(Employee $employee): JsonResponse
    {
        $this->service->delete($employee);
        return response()->json(null, 204);
    }

    public function uploadPhoto(Request $request, Employee $employee): JsonResponse
    {
        $request->validate([
            'photo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);

        $path = $this->service->uploadPhoto($employee, $request->file('photo'));

        return response()->json([
            'photo'     => $path,
            'photo_url' => \Storage::url($path),
        ]);
    }

    public function deletePhoto(Employee $employee): JsonResponse
    {
        $this->service->deletePhoto($employee);
        return response()->json(null, 204);
    }
}
