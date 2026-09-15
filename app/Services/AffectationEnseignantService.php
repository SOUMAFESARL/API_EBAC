<?php

namespace App\Services;

use App\Models\AffectationEnseignant;
use App\Models\Cours;
use App\Models\Matiere;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AffectationEnseignantService
{
    /** Appelé dans une transaction ; le verrou de la matière protège toutes ses affectations. */
    public function creer(array $data, int $userId): AffectationEnseignant
    {
        Matiere::lockForUpdate()->findOrFail($data['id_matiere']);
        $teacher = User::with('role')->findOrFail($data['enseignant_id']);
        if (! $teacher->is_active || $teacher->statut !== 'Actif') {
            throw ValidationException::withMessages(['enseignant_id' => ['Sélectionnez un enseignant actif.']]);
        }
        if ($data['portee'] === 'cours' && Cours::whereKey($data['id_cours'])->whereHas('module', fn ($q) => $q->where('id_matiere', $data['id_matiere']))->doesntExist()) {
            throw ValidationException::withMessages(['id_cours' => ['Le cours doit appartenir à la matière sélectionnée.']]);
        }
        $conflict = AffectationEnseignant::where('id_matiere', $data['id_matiere'])
            ->where(fn ($q) => $q->whereNull('date_fin')->orWhere('date_fin', '>', $data['date_debut']))
            ->when($data['portee'] === 'cours', fn ($q) => $q->where(fn ($s) => $s->where('portee', 'matiere')->orWhere('id_cours', $data['id_cours'])))
            ->exists();
        if ($conflict) {
            throw ValidationException::withMessages(['id_matiere' => ['Cet enseignement possède déjà une affectation sur cette période.']]);
        }
        $affectation = AffectationEnseignant::create([...$data, 'id_cours' => $data['portee'] === 'matiere' ? null : $data['id_cours'], 'created_by' => $userId]);
        if ($data['portee'] === 'matiere') {
            Matiere::whereKey($data['id_matiere'])->update(['enseignant_id' => $data['enseignant_id'], 'updated_by' => $userId]);
        }

        return $affectation;
    }

    public function terminer(AffectationEnseignant $item, string $end, ?string $motif, int $userId): void
    {
        if ($item->date_fin !== null) {
            throw ValidationException::withMessages(['date_fin' => ['Cette affectation est déjà terminée.']]);
        }
        if ($end < $item->date_debut->toDateString()) {
            throw ValidationException::withMessages(['date_fin' => ['La fin doit être postérieure ou égale à la prise d’effet.']]);
        }
        $lastGrade = DB::table('lignes_bulletins')->where('id_matiere', $item->id_matiere)
            ->whereNotNull('note')->whereDate('updated_at', '>=', $item->date_debut->toDateString())->max('updated_at');
        if ($lastGrade && $end < substr($lastGrade, 0, 10)) {
            throw ValidationException::withMessages(['date_fin' => ['La date de fin ne peut pas être antérieure aux notes déjà saisies.']]);
        }
        $item->update(['date_fin' => $end, 'motif_fin' => $motif, 'updated_by' => $userId]);
        if ($item->portee === 'matiere') {
            Matiere::whereKey($item->id_matiere)->where('enseignant_id', $item->enseignant_id)
                ->update(['enseignant_id' => null, 'updated_by' => $userId]);
        }
    }

    public function synchroniserMatiere(Matiere $matiere, ?int $enseignantId, int $userId): void
    {
        $today = now()->toDateString();
        $actuelles = AffectationEnseignant::where('id_matiere', $matiere->id)->where('portee', 'matiere')
            ->where(fn ($q) => $q->whereNull('date_fin')->orWhere('date_fin', '>', $today))->get();
        if ($actuelles->count() === 1 && $actuelles->first()->enseignant_id === $enseignantId) {
            return;
        }
        foreach ($actuelles as $affectation) {
            $this->terminer($affectation, $today, 'Modification de l’enseignant depuis la matière.', $userId);
        }
        if ($enseignantId !== null) {
            $this->creer(['enseignant_id' => $enseignantId, 'id_matiere' => $matiere->id,
                'portee' => 'matiere', 'date_debut' => $today], $userId);
        }
    }
}
