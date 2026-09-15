<?php

namespace App\Http\Controllers\Api\V1\Parametre;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Parametre\CreerModuleCalendrierRequest;
use App\Http\Requests\Api\V1\Parametre\ModifierModuleCalendrierRequest;
use App\Models\ModuleCalendrier;
use App\Services\CalendrierAcademiqueService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ModuleCalendrierController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate(['id_calendrier' => ['sometimes', 'integer', 'exists:calendriers_academiques,id']]);
        $modules = ModuleCalendrier::query()
            ->with(['calendrier', 'evenements'])
            ->when(isset($data['id_calendrier']), fn ($query) => $query->where('id_calendrier', $data['id_calendrier']))
            ->orderBy('ordre')->orderBy('id')->get();

        return response()->json(['modules' => $modules]);
    }

    public function store(CreerModuleCalendrierRequest $request): JsonResponse
    {
        $module = ModuleCalendrier::query()->create($request->validated());

        return response()->json([
            'message' => 'Module créé avec succès.',
            'module' => $module->load(['calendrier', 'evenements']),
        ], 201);
    }

    public function show(int $id): JsonResponse
    {
        return response()->json([
            'module' => ModuleCalendrier::query()->with(['calendrier', 'evenements'])->findOrFail($id),
        ]);
    }

    public function update(ModifierModuleCalendrierRequest $request, int $id): JsonResponse
    {
        $module = ModuleCalendrier::query()->findOrFail($id);
        $module->update($request->validated());

        return response()->json([
            'message' => 'Module modifié avec succès.',
            'module' => $module->fresh()->load(['calendrier', 'evenements']),
        ]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        DB::transaction(function () use ($request, $id) {
            $module = ModuleCalendrier::query()->lockForUpdate()->findOrFail($id);
            app(CalendrierAcademiqueService::class)->supprimerModule($module, $request->user()->id);
        });

        return response()->json(['message' => 'Module supprimé avec succès.']);
    }
}
