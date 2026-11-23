<?php

namespace Tests\Unit;

use App\Models\InternshipOffer;
use Tests\TestCase;

class InternshipOfferModelTest extends TestCase
{
    private function offre(int $places, int $acceptees): InternshipOffer
    {
        $offre = new InternshipOffer(['positions' => $places]);
        $offre->setAttribute('accepted_applications_count', $acceptees);

        return $offre;
    }

    public function test_places_disponibles_cas_nominal(): void
    {
        // Arrange + Act
        $offre = $this->offre(5, 2);

        // Assert
        $this->assertSame(3, $offre->available_positions);
    }

    public function test_places_disponibles_offre_complete(): void
    {
        $this->assertSame(0, $this->offre(2, 2)->available_positions);
    }

    public function test_places_disponibles_jamais_negatives(): void
    {
        $this->assertSame(0, $this->offre(1, 3)->available_positions);
    }

    public function test_nombre_de_places_converti_en_entier(): void
    {
        $offre = new InternshipOffer(['positions' => '7']);

        $this->assertSame(7, $offre->positions);
    }

    public function test_competences_requises_converties_en_tableau(): void
    {
        $offre = new InternshipOffer(['required_skills' => ['PHP', 'Laravel']]);

        $this->assertSame(['PHP', 'Laravel'], $offre->required_skills);
    }
}
