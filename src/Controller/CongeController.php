<?php

namespace App\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use App\Entity\Conge;
use Dompdf\Options;
use Dompdf\Dompdf;

#[Route('/api/conges')]
final class CongeController extends AbstractController
{
    #[Route('', name: 'conge_create', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function create(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        if (!isset($data['startDate'], $data['endDate'], $data['reason'])) {
            return $this->json(['message' => 'Champs manquants'], 400);
        }
        $startDate = \DateTime::createFromFormat('Y-m-d', $data['startDate']);
        $endDate = \DateTime::createFromFormat('Y-m-d', $data['endDate']);

        if (!$startDate || !$endDate) {
            return $this->json(['message' => 'Dates invalides'], 400);
        }
        $conge = new Conge();
        $conge->setStartDate($startDate);
        $conge->setEndDate($endDate);
        $conge->setReason($data['reason']);
        $conge->setUser($this->getUser());
        // status par défaut déjà dans le constructeur
        $conge->setUpdatedAt(new \DateTime());

        $em->persist($conge);
        $em->flush();

        return $this->json(['message' => 'Demande de congé envoyée'], Response::HTTP_CREATED);
    }
    #[Route('', name: 'conge_index', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function index(EntityManagerInterface $em): JsonResponse
    {
        $user = $this->getUser();
        $conges = $em->getRepository(Conge::class)->findBy(['user' => $user]);

        $data = [];
        foreach ($conges as $conge) {
            $data[] = [
                'id' => $conge->getId(),
                'startDate' => $conge->getStartDate()->format('Y-m-d'),
                'endDate' => $conge->getEndDate()->format('Y-m-d'),
                'reason' => $conge->getReason(),
                'status' => $conge->getStatus(),
                'createdAt' => $conge->getCreatedAt()->format('Y-m-d H:i:s'),

            ];
        }
        return $this->json($data);
    }
    #[Route('/{id}/valider', name: 'conger_valider', methods: ['PUT'])]
    #[IsGranted('ROLE_MANAGER')]
    public function valider(int $id, EntityManagerInterface $em): JsonResponse
    {
        $conge = $em->getRepository(Conge::class)->find($id);
        if (!$conge) {
            return $this->json(['message' => 'Demande de congé non trouver'], 404);
        }
        $conge->setStatus(Conge::STATUS_VALIDEE);
        $conge->setUpdatedAt(new \DateTime());
        $em->flush();

        return $this->json(['message' => 'Demande validée']);
    }

    #[Route('/{id}/rejeter', name: 'conge_rejeter', methods: ['PUT'])]
    #[IsGranted('ROLE_MANAGER')]
    public function rejeter(int $id, EntityManagerInterface $em): JsonResponse
    {
        $conge = $em->getRepository(Conge::class)->find($id);
        if (!$conge) {
            return $this->json(['message' => 'Demande non trouvée'], 404);
        }

        $conge->setStatus(Conge::STATUS_REJETEE);
        $conge->setUpdatedAt(new \DateTime());
        $em->flush();

        return $this->json(['message' => 'Demande rejetée']);
    }
    #[Route('/admin/conges', name: 'admin_conges_list', methods: ['GET'])]
    #[IsGranted('ROLE_MANAGER')]
    public function listAll(EntityManagerInterface $em, Request $request): JsonResponse
    {

        if (!$this->isGranted('ROLE_MANAGER') && !$this->isGranted('ROLE_ADMIN')) {
            return $this->json(['message' => 'Accès refusé'], 403);
        }

        $status = $request->query->get('status');
        $userId = $request->query->get('user');
        $criteria = [];

        if ($status) {
            $criteria['status'] = strtoupper($status); // EN_ATTENTE, VALIDEE, ...
        }

        if ($userId) {
            $user = $em->getRepository(\App\Entity\User::class)->find($userId);
            if ($user) {
                $criteria['user'] = $user;
            }
        }

        $conges = $em->getRepository(Conge::class)->findAll();
        $data = [];
        foreach ($conges as $conge) {
            $data[] = [
                'id' => $conge->getId(),
                'startDate' => $conge->getStartDate()->format('Y-m-d'),
                'endDate' => $conge->getEndDate()->format('Y-m-d'),
                'reason' => $conge->getReason(),
                'status' => $conge->getStatus(),
                'createdAt' => $conge->getCreatedAt()->format('Y-m-d H:i'),
                'updatedAt' => $conge->getUpdatedAt()->format('Y-m-d H:i'),
                'user' => [
                    'id' => $conge->getUser()->getId(),
                    'email' => $conge->getUser()->getEmail(),
                ]
            ];
        }
        return $this->json($data);
    }

    #[Route('/{id}/export', name: 'conge_export_pdf', methods: ['GET'])]
    #[IsGranted('ROLE_MANAGER')]
    public function exportPdf(int $id, EntityManagerInterface $em,  \Twig\Environment $twig): Response
    {
        $conge = $em->getRepository(Conge::class)->find($id);
        if (!$conge) {
            return $this->json(['message' => 'Demande non trouvée'], 404);
        }

        $html = $twig->render('conge/fiche.html.twig', [
            'conge' => $conge,
        ]);

        // Set DomPDF options
        $dompdf = new Dompdf();
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return new Response(
            $dompdf->output(),
            200,
            [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="conge_' . $conge->getId() . '.pdf"',
            ]
        );
    }

    #[Route('/admin/conges/export', name: 'admin_conges_export', methods: ['GET'])]
    #[IsGranted('ROLE_MANAGER')]
    public function exportListe(EntityManagerInterface $em, \Twig\Environment $twig): Response
    {
        $conges = $em->getRepository(Conge::class)->findAll();

        $html = $twig->render('conge/liste.html.twig', [
            'conges' => $conges,
        ]);

        $dompdf = new Dompdf();
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();

        return new Response(
            $dompdf->output(),
            200,
            [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="liste_conges.pdf"',
            ]
        );
    }
}