<?php

namespace App\Modules\HRM\Services;

use App\Modules\HRM\Models\Employee;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class EmployeeService
{
    public function paginate(array $filters = []): LengthAwarePaginator
    {
        $q = Employee::query()
            ->with([
                'department:id,name',
                'designation:id,title,department_id',
                'branch:id,name',
                'shift:id,name,start_time,end_time',
            ]);

        $this->applyBranchScope($q);

        if (!empty($filters['search'])) {
            $term = '%' . $filters['search'] . '%';
            $q->where(function ($w) use ($term) {
                $w->where('first_name', 'like', $term)
                  ->orWhere('last_name', 'like', $term)
                  ->orWhere('employee_id', 'like', $term)
                  ->orWhere('email', 'like', $term)
                  ->orWhere('phone', 'like', $term);
            });
        }

        if (!empty($filters['department_id'])) {
            $q->where('department_id', $filters['department_id']);
        }

        if (!empty($filters['designation_id'])) {
            $q->where('designation_id', $filters['designation_id']);
        }

        if (!empty($filters['branch_id']) && $this->isAdmin()) {
            $q->where('branch_id', $filters['branch_id']);
        }

        if (!empty($filters['status'])) {
            $q->where('status', $filters['status']);
        }

        if (!empty($filters['employment_type'])) {
            $q->where('employment_type', $filters['employment_type']);
        }

        return $q->orderByDesc('id')->paginate($filters['per_page'] ?? 20);
    }

    public function find(int $id): Employee
    {
        $q = Employee::with([
            'department', 'designation', 'branch', 'shift', 'creator:id,name',
        ]);

        $this->applyBranchScope($q);

        return $q->findOrFail($id);
    }

    public function store(array $data, ?UploadedFile $photo = null): Employee
    {
        $data['employee_id'] = $this->generateEmployeeId();

        if (empty($data['branch_id'])) {
            $data['branch_id'] = Auth::user()->branch_id;
        }

        if ($photo) {
            $data['photo'] = $this->savePhoto($photo);
        }

        $employee = Employee::create($data);

        return $this->find($employee->id);
    }

    public function update(Employee $employee, array $data, ?UploadedFile $photo = null): Employee
    {
        // employee_id stays immutable once issued; strip it from update payload
        unset($data['employee_id']);

        // Non-admin users cannot move an employee to a different branch
        if (!$this->isAdmin()) {
            unset($data['branch_id']);
        }

        if ($photo) {
            if ($employee->photo) {
                Storage::disk('public')->delete($employee->photo);
            }
            $data['photo'] = $this->savePhoto($photo);
        }

        $employee->update($data);

        return $this->find($employee->id);
    }

    public function setStatus(Employee $employee, string $status): Employee
    {
        $employee->update(['status' => $status]);
        return $employee->fresh();
    }

    public function delete(Employee $employee): void
    {
        $employee->delete();
    }

    public function uploadPhoto(Employee $employee, UploadedFile $photo): string
    {
        if ($employee->photo) {
            Storage::disk('public')->delete($employee->photo);
        }
        $path = $this->savePhoto($photo);
        $employee->update(['photo' => $path]);
        return $path;
    }

    public function deletePhoto(Employee $employee): void
    {
        if ($employee->photo) {
            Storage::disk('public')->delete($employee->photo);
            $employee->update(['photo' => null]);
        }
    }

    public function stats(): array
    {
        $q = Employee::query();
        $this->applyBranchScope($q);

        $base = (clone $q)->selectRaw('status, count(*) as c')->groupBy('status')->pluck('c', 'status');

        return [
            'total'      => (int) $base->sum(),
            'active'     => (int) ($base['active']     ?? 0),
            'inactive'   => (int) ($base['inactive']   ?? 0),
            'terminated' => (int) ($base['terminated'] ?? 0),
            'resigned'   => (int) ($base['resigned']   ?? 0),
        ];
    }

    private function savePhoto(UploadedFile $photo): string
    {
        return $photo->store('employees', 'public');
    }

    private function generateEmployeeId(): string
    {
        $year   = now()->format('Y');
        $prefix = "EMP-{$year}-";

        $last = Employee::withTrashed()
            ->where('employee_id', 'like', $prefix . '%')
            ->max('employee_id');

        $next = $last ? ((int) substr($last, -5)) + 1 : 1;

        return $prefix . str_pad($next, 5, '0', STR_PAD_LEFT);
    }

    private function applyBranchScope($query): void
    {
        $user = Auth::user();
        if (!$user || $this->isAdmin()) {
            return;
        }
        if ($user->branch_id) {
            $query->where('branch_id', $user->branch_id);
        }
    }

    private function isAdmin(): bool
    {
        return Auth::user()?->role === 'admin';
    }
}
