<?php

namespace App\Services;

use App\Models\AffectationEnseignant;
use App\Models\Cours;
use App\Models\Etudiant;
use App\Models\QuotaStockage;
use App\Models\SupportPedagogique;
use App\Models\User;
use App\Models\VersionSupport;

class BibliothequeService
{
    public const FORMATS = ['PDF', 'DOCX', 'ODT', 'PPTX', 'ODP', 'XLSX', 'MP3', 'MP4', 'JPG', 'PNG', 'ZIP'];

    public const TAILLE_MAX_KO = 50 * 1024;

    public function estEnseignant(User $utilisateur): bool
    {
        return $utilisateur->role?->code === 'ENSEIGNANT';
    }

    /** @return list<int> cours affectés aujourd'hui à l'enseignant */
    public function coursAffectes(User $enseignant): array
    {
        $aujourdhui = now()->toDateString();
        $affectations = AffectationEnseignant::query()
            ->where('enseignant_id', $enseignant->id)
            ->where('date_debut', '<=', $aujourdhui)
            ->where(fn ($q) => $q->whereNull('date_fin')->orWhere('date_fin', '>', $aujourdhui))
            ->get();

        $matieres = $affectations->where('portee', 'matiere')->pluck('id_matiere')->unique()->all();
        $ids = $affectations->where('portee', '!=', 'matiere')->pluck('id_cours')->filter()->all();

        if ($matieres !== []) {
            $ids = [...$ids, ...Cours::query()->whereHas('module', fn ($q) => $q->whereIn('id_matiere', $matieres))->pluck('id')->all()];
        }

        return array_values(array_unique(array_map('intval', $ids)));
    }

    /** L'enseignant écrit sur ses cours affectés ; la gestion, avec le droit. */
    public function peutEcrire(User $utilisateur, int $coursId, string $action): bool
    {
        if ($this->estEnseignant($utilisateur)) {
            return in_array($coursId, $this->coursAffectes($utilisateur), true);
        }

        return $utilisateur->detientUnDroit("ressources.bibliotheque.{$action}");
    }

    public function peutLire(User $utilisateur, SupportPedagogique $support): bool
    {
        if ($this->estEnseignant($utilisateur)) {
            return in_array($support->id_cours, $this->coursAffectes($utilisateur), true);
        }

        if ($utilisateur->role?->code === 'ETUDIANT') {
            $rang = Etudiant::query()->where('user_id', $utilisateur->id)->first()?->inscriptions()->with('promotion.niveau')
                ->latest('date_inscription')->latest('id')->first()?->promotion?->niveau?->rang;
            $rangMatiere = $support->cours?->module?->matiere?->niveau?->rang;

            return $rang !== null && $rangMatiere !== null && $rangMatiere <= $rang;
        }

        return $utilisateur->detientUnDroit('ressources.bibliotheque.consulter');
    }

    public function utiliseOctets(User $enseignant): int
    {
        return (int) VersionSupport::query()->where('depose_par', $enseignant->id)->sum('taille_octets');
    }

    public function quotaMo(User $enseignant): int
    {
        return QuotaStockage::query()->where('enseignant_id', $enseignant->id)->value('quota_mo') ?? QuotaStockage::DEFAUT_MO;
    }

    /** Seul l'espace d'un enseignant est plafonné. */
    public function quotaDepasse(User $deposant, int $octets): bool
    {
        return $this->estEnseignant($deposant)
            && $this->utiliseOctets($deposant) + $octets > $this->quotaMo($deposant) * 1024 * 1024;
    }

    public function quota(User $enseignant): array
    {
        $versions = VersionSupport::query()->where('depose_par', $enseignant->id);

        return [
            'enseignant_id' => $enseignant->id,
            'enseignant' => $this->personne($enseignant),
            'quota_mo' => $this->quotaMo($enseignant),
            'utilise_mo' => round($this->utiliseOctets($enseignant) / 1048576, 1),
            'supports' => (clone $versions)->distinct('id_support')->count('id_support'),
            'versions' => $versions->count(),
        ];
    }

    public function support(SupportPedagogique $support): array
    {
        $support->loadMissing('versions.auteur');

        return [
            'id' => $support->id,
            'nom' => $support->nom,
            'type' => $support->type,
            'versions' => $support->versions->map(fn (VersionSupport $v) => [
                'numero' => $v->numero,
                'taille_mo' => max(0.1, round($v->taille_octets / 1048576, 1)),
                'format' => $v->format,
                'depose_le' => $v->created_at?->toDateString(),
                'note' => $v->note ?? '',
                'depose_par' => $this->personne($v->auteur),
            ])->values()->all(),
        ];
    }

    public function personne(?User $utilisateur): ?array
    {
        return $utilisateur ? ['id' => $utilisateur->id, 'nom' => $utilisateur->nom, 'prenoms' => $utilisateur->prenoms] : null;
    }
}
