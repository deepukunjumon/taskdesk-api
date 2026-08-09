<?php

namespace App\Http\Controllers\Api;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateSlaSettingRequest;
use App\Http\Resources\SlaSettingResource;
use App\Models\SlaSetting;
use Illuminate\Http\JsonResponse;

/**
 * Simple lookup/settings CRUD — see DepartmentController for rationale.
 */
class SlaSettingController extends Controller
{
    public function index(): JsonResponse
    {
        return SlaSettingResource::collection(SlaSetting::all())->response();
    }

    public function update(UpdateSlaSettingRequest $request, SlaSetting $slaSetting): JsonResponse
    {
        abort_unless($request->user()->hasRole([Role::SuperAdmin->value, Role::Admin->value]), 403);

        $slaSetting->update($request->validated());

        return (new SlaSettingResource($slaSetting))->response();
    }
}
