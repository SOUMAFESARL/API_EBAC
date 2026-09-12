<?php

namespace App\Http\Controllers\Api\V1\Parametre;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Parametre\EnregistrerEvenementCalendrierRequest;
use App\Models\EvenementCalendrier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class EvenementCalendrierController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'id_calendrier' => ['sometimes', 'integer', 'exists:calendriers_academiques,id'],
            'id_module_calendrier' => ['sometimes', 'integer', 'exists:modules_calendrier,id'],
            'type' => ['sometimes', Rule::in(['examen', 'rattrapage', 'jour_ferie', 'conge', 'grandes_vacances'])],
        ]);
        $events = EvenementCalendrier::query()->with(['calendrier', 'module'])
            ->when(isset($data['id_calendrier']), fn ($q) => $q->where('id_calendrier', $data['id_calendrier']))
            ->when(isset($data['id_module_calendrier']), fn ($q) => $q->where('id_module_calendrier', $data['id_module_calendrier']))
            ->when(isset($data['type']), fn ($q) => $q->where('type', $data['type']))
            ->orderBy('date_debut')->orderBy('id')->get();

        return response()->json(['evenements' => $events]);
    }

    public function store(EnregistrerEvenementCalendrierRequest $request): JsonResponse
    {
        $event = EvenementCalendrier::query()->create($request->validated());
        return response()->json(['message' => 'Événement créé avec succès.', 'evenement' => $event->load(['calendrier', 'module'])], 201);
    }

    public function show(int $id): JsonResponse
    {
        return response()->json(['evenement' => EvenementCalendrier::query()->with(['calendrier', 'module'])->findOrFail($id)]);
    }

    public function update(EnregistrerEvenementCalendrierRequest $request, int $id): JsonResponse
    {
        $event = EvenementCalendrier::query()->findOrFail($id);
        $data = $request->validated();
        if (isset($data['type']) && in_array($data['type'], ['jour_ferie', 'conge', 'grandes_vacances'], true)) {
            $data['id_module_calendrier'] = null;
        }
        $event->update($data);
        return response()->json(['message' => 'Événement modifié avec succès.', 'evenement' => $event->fresh()->load(['calendrier', 'module'])]);
    }

    public function destroy(int $id): JsonResponse
    {
        EvenementCalendrier::query()->findOrFail($id)->delete();
        return response()->json(['message' => 'Événement supprimé avec succès.']);
    }
}
