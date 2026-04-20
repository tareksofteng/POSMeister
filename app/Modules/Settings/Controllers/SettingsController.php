<?php

namespace App\Modules\Settings\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Settings\Requests\UpdateSettingsRequest;
use App\Modules\Settings\Resources\SettingsResource;
use App\Modules\Settings\Services\SettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    public function __construct(private readonly SettingsService $service) {}

    /** GET /api/settings — all authenticated users */
    public function show(): SettingsResource
    {
        return new SettingsResource($this->service->get());
    }

    /** PUT /api/settings — admin only */
    public function update(UpdateSettingsRequest $request): SettingsResource
    {
        return new SettingsResource($this->service->update($request->validated()));
    }

    /** POST /api/settings/logo — admin only */
    public function uploadLogo(Request $request): SettingsResource
    {
        $request->validate([
            'logo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp,svg', 'max:2048'],
        ]);

        return new SettingsResource($this->service->uploadLogo($request->file('logo')));
    }

    /** DELETE /api/settings/logo — admin only */
    public function deleteLogo(): JsonResponse
    {
        $this->service->deleteLogo();
        return response()->json(null, 204);
    }
}
