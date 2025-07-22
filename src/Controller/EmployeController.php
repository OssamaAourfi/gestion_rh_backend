<?php

namespace App\Controller;

use App\Entity\Employee;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Dompdf\Dompdf;
use \Twig\Environment as Twig;

final class EmployeController extends AbstractController
{
    // All authenticated users can view employees list
    #[Route('/api/employes', name: 'employe_index', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function index(EntityManagerInterface $em): JsonResponse
    {
        $employes = $em->getRepository(Employee::class)->findAll();
        $data = [];
        foreach ($employes as $emp) {
            $data[] = [
                'id' => $emp->getId(),
                'nom' => $emp->getNom(),
                'prenom' => $emp->getPrenom(),
                'email' => $emp->getEmail(),
                'poste' => $emp->getPoste(),
                'salaire' => $emp->getSalaire(),
                'dateEmbauche' => $emp->getDateEmbauche()->format('Y-m-d'),
                'isActive' => $emp->isActive(),
            ];
        }
        return $this->json($data);
    }

    // Only managers and admins can create employees
    #[Route('/api/employes', name: 'employe_create', methods: ['POST'])]
    #[IsGranted('ROLE_MANAGER')]
    public function create(EntityManagerInterface $em, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $employe = new Employee();
        $employe->setNom($data['nom'] ?? '');
        $employe->setPrenom($data['prenom'] ?? '');
        $employe->setEmail($data['email'] ?? '');
        $employe->setPoste($data['poste'] ?? '');
        $employe->setSalaire($data['salaire'] ?? 0.0);
        $employe->setIsActive($data['isActive'] ?? true);

        if (isset($data['dateEmbauche'])) {
            $date = \DateTime::createFromFormat('Y-m-d', $data['dateEmbauche']);
            if ($date) {
                $employe->setDateEmbauche($date);
            } else {
                $employe->setDateEmbauche(new \DateTime()); // fallback
            }
        } else {
            $employe->setDateEmbauche(new \DateTime()); // default to now
        }


        $em->persist($employe);
        $em->flush();

        return $this->json(['message' => 'Employé créé avec succès'], Response::HTTP_CREATED);
    }

    // Only managers and admins can update employees
    #[Route('/api/employes/{id}', name: 'employe_update', methods: ['PUT'])]
    #[IsGranted('ROLE_MANAGER')]
    public function update(EntityManagerInterface $em, Request $request, int $id): JsonResponse
    {
        $employe = $em->getRepository(Employee::class)->find($id);
        if (!$employe) {
            return $this->json(['message' => 'Employé non trouvé'], 404);
        }

        $data = json_decode($request->getContent(), true);
        $employe->setNom($data['nom'] ?? $employe->getNom());
        $employe->setEmail($data['email'] ?? $employe->getEmail());
        $employe->setPoste($data['poste'] ?? $employe->getPoste());
        $employe->setSalaire($data['salaire'] ?? $employe->getSalaire());
        $employe->setIsActive($data['isActive'] ?? $employe->isActive());

        if (isset($data['dateEmbauche'])) {
            $date = \DateTime::createFromFormat('Y-m-d', $data['dateEmbauche']);
            if ($date) {
                $employe->setDateEmbauche($date);
            }
        }

        $em->flush();

        return $this->json(['message' => 'Employé mis à jour avec succès']);
    }

    // Only admins can delete employees
    #[Route('/api/employes/{id}', name: 'employe_delete', methods: ['DELETE'])]
    #[IsGranted('ROLE_MANAGER')]
    public function delete(EntityManagerInterface $em, int $id): JsonResponse
    {
        $employee = $em->getRepository(Employee::class)->find($id);
        if (!$employee) {
            return $this->json(['message' => 'Employé non trouvé'], 404);
        }

        $em->remove($employee);
        $em->flush();

        return $this->json(['message' => 'Employé supprimé avec succès']);
    }

    // All authenticated users can view individual employee details
    #[Route('/api/employes/{id}', name: 'employe_show', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function show(EntityManagerInterface $em, int $id): JsonResponse
    {
        $employe = $em->getRepository(Employee::class)->find($id);
        if (!$employe) {
            return $this->json(['message' => 'Employé non trouvé'], 404);
        }

        return $this->json([
            'id' => $employe->getId(),
            'nom' => $employe->getNom(),
            'email' => $employe->getEmail(),
            'poste' => $employe->getPoste(),
            'salaire' => $employe->getSalaire(),
            'dateEmbauche' => $employe->getDateEmbauche()->format('Y-m-d'),
            'isActive' => $employe->isActive(),
        ]);
    }
    #[Route('/api/employes/{id}/export', name: 'employe_export_pdf', methods: ['GET'])]
    #[IsGranted('ROLE_MANAGER')]
    public function exportFiche(int $id, EntityManagerInterface $em, Twig $twig): Response
    {
        $employe = $em->getRepository(Employee::class)->find($id);
        if (!$employe) {
            return $this->json(['message' => 'Employé non trouvé'], 404);
        }

        $html = $twig->render('employe/fiche.html.twig', [
            'employe' => $employe,
        ]);

        $dompdf = new Dompdf();
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return new Response(
            $dompdf->output(),
            200,
            [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="employe_' . $employe->getId() . '.pdf"',
            ]
        );
    }
}