<?php

namespace App\Services;

class MentionBulletinService
{
    public static function calculer(?float $note): ?string
    {
        return match (true) {
            $note === null => null,
            $note >= 16 => 'Très bien',
            $note >= 14 => 'Bien',
            $note >= 12 => 'Assez bien',
            $note >= 10 => 'Passable',
            default => 'Insuffisant',
        };
    }
}
