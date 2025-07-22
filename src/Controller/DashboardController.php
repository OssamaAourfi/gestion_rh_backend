<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Attribute\Route;

use App\Repository\EmployeeRepository;
use App\Repository\CongeRepository;
use Symfony\Component\HttpFoundation\JsonResponse;

#[Route('/api/dashboard')]
final class DashboardController extends AbstractController
{
    #[Route('/', name: 'dashboard_stats', methods: ['GET'])]
    public function getStats(EmployeeRepository $empRepo, CongeRepository $congeRepo): JsonResponse
    {

        if (!$this->isGranted('ROLE_ADMIN') && !$this->isGranted('ROLE_MANAGER')) {
            return $this->json(['message' => 'Accès refusé'], 403);
        }

        $totalEmployes = $empRepo->count([]);
        $allConges = $congeRepo->findAll();

        $enAttente = array_filter($allConges, fn($c) => $c->getStatus() === 'EN_ATTENTE');
        $validees = array_filter($allConges, fn($c) => $c->getStatus() === 'VALIDEE');
        $rejetees = array_filter($allConges, fn($c) => $c->getStatus() === 'REJETEE');

        return $this->json([
            'totalEmployes' => $totalEmployes,
            'totalConges' => count($allConges),
            'enAttente' => count($enAttente),
            'validees' => count($validees),
            'rejetees' => count($rejetees),
        ]);
    }
}