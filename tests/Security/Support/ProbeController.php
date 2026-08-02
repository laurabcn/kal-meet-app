<?php

declare(strict_types=1);

namespace Tests\Security\Support;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

/**
 * L'escenari 12 del spec: una ruta afegida DESPRÉS de l'autenticació, sense
 * tocar `config/packages/security.yaml`, ha de néixer protegida.
 *
 * És tot el sentit d'haver triat firewall en comptes d'un listener (§9): el bug
 * que el spec arregla és "algú no es va recordar de protegir una ruta", i un
 * mecanisme opt-in el reprodueix la propera vegada. Per això aquest controller
 * no diu res d'autenticació enlloc — la gràcia és justament que no calgui.
 *
 * Només existeix a l'entorn de test (config/routes/test/probe.yaml).
 */
#[AsController]
final class ProbeController
{
    /** @throws \InvalidArgumentException */
    #[Route('/test-probe', name: 'test_probe', methods: ['GET'])]
    public function __invoke(): JsonResponse
    {
        return new JsonResponse(['reached' => true]);
    }
}
