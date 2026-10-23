<?php

namespace App\Services;

class MentionBulletinService
{
    public static function afficher(?string $mention, ?float $note): ?string
    {
        return $note === null || $mention === 'Insuffisant' ? 'À REPRENDRE' : ($mention ?? self::calculer($note));
    }

    public static function calculer(?float $note): ?string
    {
        return match (true) {
            $note === null => 'À REPRENDRE',
            $note >= 16 => 'Très bien',
            $note >= 14 => 'Bien',
            $note >= 12 => 'Assez bien',
            $note >= 10 => 'Passable',
            default => 'À REPRENDRE',
        };
    }
}
