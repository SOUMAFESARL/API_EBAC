<?php

namespace Tests\Unit;

use App\Services\MentionBulletinService;
use PHPUnit\Framework\TestCase;

class MentionBulletinServiceTest extends TestCase
{
    public function test_bareme_et_absence_de_note(): void
    {
        foreach ([[null, 'À REPRENDRE'], [0, 'À REPRENDRE'], [9.99, 'À REPRENDRE'], [10, 'Passable'],
            [11.99, 'Passable'], [12, 'Assez bien'], [13.99, 'Assez bien'], [14, 'Bien'],
            [15.2, 'Bien'], [15.99, 'Bien'], [16, 'Très bien'], [20, 'Très bien']] as [$note, $mention]) {
            $this->assertSame($mention, MentionBulletinService::calculer($note));
        }
        $this->assertSame('À REPRENDRE', MentionBulletinService::afficher('Insuffisant', 8));
        $this->assertSame('Bien', MentionBulletinService::afficher('Bien', 14));
        $this->assertSame('À REPRENDRE', MentionBulletinService::afficher(null, null));
        $this->assertSame('À REPRENDRE', MentionBulletinService::afficher('Bien', null));
    }
}
