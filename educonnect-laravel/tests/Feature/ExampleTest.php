<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * L'accueil interroge les référentiels (classes, modules, salles) :
 * la base de test est donc migrée à chaque exécution.
 */
class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_la_page_d_accueil_repond(): void
    {
        $this->get('/')->assertOk()->assertSee('EduConnect');
    }

    public function test_la_connexion_est_protegee_par_le_middleware_de_role(): void
    {
        $this->get('/admin/etudiants')->assertRedirect('/connexion');
    }
}
