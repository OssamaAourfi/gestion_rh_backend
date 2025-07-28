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
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Dompdf\Options;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Psr\Log\LoggerInterface;

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
                'photo' => $emp->getPhoto(),
            ];
        }
        return $this->json($data);
    }

    #[Route('/api/employes', name: 'employe_create', methods: ['POST'])]
    // #[IsGranted('ROLE_MANAGER')]
    public function create(EntityManagerInterface $em, Request $request): JsonResponse
    {
        $employe = new Employee();

        $employe->setNom($request->get('nom'));
        $employe->setPrenom($request->get('prenom'));
        $employe->setEmail($request->get('email'));
        $employe->setPoste($request->get('poste'));
        $employe->setSalaire(floatval($request->get('salaire')));
        $employe->setIsActive($request->get('isActive') === 'true');

        $dateStr = $request->get('dateEmbauche');
        $date = $dateStr ? \DateTime::createFromFormat('Y-m-d', $dateStr) : new \DateTime();
        $employe->setDateEmbauche($date ?: new \DateTime());

        // 📎 Upload CV
        /** @var UploadedFile $cv */
        $cv = $request->files->get('cv');
        if ($cv) {
            $cvFilename = uniqid() . '.' . $cv->guessExtension();
            $cv->move($this->getParameter('kernel.project_dir') . '/public/uploads/cvs', $cvFilename);
            $employe->setCv($cvFilename);
        }

        // 🖼️ Upload Photo
        /** @var UploadedFile $photo */
        $photo = $request->files->get('photo');
        if ($photo) {
            $photoFilename = uniqid() . '.' . $photo->guessExtension();
            $photo->move($this->getParameter('kernel.project_dir') . '/public/uploads/photos', $photoFilename);
            $employe->setPhoto($photoFilename);
        }

        $em->persist($employe);
        $em->flush();

        return $this->json(['message' => 'Employé créé avec succès'], Response::HTTP_CREATED);
    }

    // FIXED UPDATE METHOD - Changed to POST and fixed file handling
    #[Route('/api/employes/{id}', name: 'employe_update', methods: ['POST', 'PUT'])]
    #[IsGranted('ROLE_ADMIN')]
    public function update(EntityManagerInterface $em, Request $request, int $id): JsonResponse
    {
        try {
            $employe = $em->getRepository(Employee::class)->find($id);
            if (!$employe) {
                return $this->json(['message' => 'Employé non trouvé'], 404);
            }

            // Update basic fields with validation
            $prenom = $request->get('prenom');
            $nom = $request->get('nom');
            $email = $request->get('email');
            $poste = $request->get('poste');
            $salaire = $request->get('salaire');

            if ($prenom) $employe->setPrenom($prenom);
            if ($nom) $employe->setNom($nom);
            if ($email) $employe->setEmail($email);
            if ($poste) $employe->setPoste($poste);
            if ($salaire) $employe->setSalaire(floatval($salaire));

            // Handle isActive
            $isActive = $request->get('isActive');
            if ($isActive !== null) {
                $employe->setIsActive($isActive === '1' || $isActive === 'true');
            }

            // Handle date
            if ($request->get('dateEmbauche')) {
                $date = \DateTime::createFromFormat('Y-m-d', $request->get('dateEmbauche'));
                if ($date) {
                    $employe->setDateEmbauche($date);
                }
            }

            // FIXED: Handle photo upload with proper directory structure
            $photo = $request->files->get('photo');
            if ($photo) {
                $photoDir = $this->getParameter('kernel.project_dir') . '/public/uploads/photos';

                // Create directory if it doesn't exist
                if (!is_dir($photoDir)) {
                    mkdir($photoDir, 0755, true);
                }

                $filename = uniqid() . '.' . $photo->guessExtension();
                $photo->move($photoDir, $filename);

                // Store only the filename, not the full path
                $employe->setPhoto($filename);
            }

            // FIXED: Handle CV upload with proper directory structure
            $cv = $request->files->get('cv');
            if ($cv) {
                $cvDir = $this->getParameter('kernel.project_dir') . '/public/uploads/cvs';

                // Create directory if it doesn't exist
                if (!is_dir($cvDir)) {
                    mkdir($cvDir, 0755, true);
                }

                $cvFilename = uniqid() . '.' . $cv->guessExtension();
                $cv->move($cvDir, $cvFilename);

                // Store only the filename, not the full path
                $employe->setCv($cvFilename);
            }

            // Save to database
            $em->flush();

            return $this->json([
                'message' => 'Employé mis à jour avec succès',
                'employee' => [
                    'id' => $employe->getId(),
                    'nom' => $employe->getNom(),
                    'prenom' => $employe->getPrenom(),
                    'email' => $employe->getEmail(),
                    'poste' => $employe->getPoste(),
                    'salaire' => $employe->getSalaire(),
                    'photo' => $employe->getPhoto(),
                    'cv' => $employe->getCv()
                ]
            ]);
        } catch (\Exception $e) {
            error_log('Employee update error: ' . $e->getMessage());
            return $this->json(['message' => 'Erreur lors de la mise à jour: ' . $e->getMessage()], 500);
        }
    }

    // Only admins can delete employees
    #[Route('/api/employes/{id}', name: 'employe_delete', methods: ['DELETE'])]
    #[IsGranted('ROLE_ADMIN')]
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
    #[Route('/api/employes/{id}', name: 'employe_show', methods: ['GET'],requirements: ['id' => '\d+'])]
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
            'prenom' => $employe->getPrenom(),
            'email' => $employe->getEmail(),
            'poste' => $employe->getPoste(),
            'salaire' => $employe->getSalaire(),
            'dateEmbauche' => $employe->getDateEmbauche()->format('Y-m-d'),
            'isActive' => $employe->isActive(),
            'photo' => $employe->getPhoto(),
            'cv' => $employe->getCv(),
        ]);
    }

    #[Route('/api/employes/{id}/export', name: 'employe_export_pdf', methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN')]
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

    #[Route('/api/employes/export-all', name: 'export_all_employes_pdf', methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN')]
    public function exportAllEmployees(EntityManagerInterface $em, Twig $twig, LoggerInterface $logger): Response
    {
        try {
            $logger->info('Starting PDF export for all employees');

            // Get all employees
            $employes = $em->getRepository(Employee::class)->findAll();

            if (empty($employes)) {
                $logger->warning('No employees found for PDF export');
                return new JsonResponse(['error' => 'Aucun employé trouvé.'], 404);
            }

            $logger->info('Found ' . count($employes) . ' employees for export');

            // Check if template exists
            $templatePath = 'employe/export_all.html.twig';
            if (!$twig->getLoader()->exists($templatePath)) {
                $logger->error('Template not found: ' . $templatePath);
                return new JsonResponse(['error' => 'Template non trouvé.'], 500);
            }

            // Render HTML template
            $html = $twig->render($templatePath, [
                'employes' => $employes,
            ]);

            $logger->info('HTML template rendered successfully');

            // Configure Dompdf
            $options = new Options();
            $options->set('defaultFont', 'Arial');
            $options->set('isRemoteEnabled', true);
            $options->set('isHtml5ParserEnabled', true);
            $options->set('debugKeepTemp', false);

            $dompdf = new Dompdf($options);
            $dompdf->loadHtml($html);
            $dompdf->setPaper('A4', 'portrait');

            // Render PDF
            $dompdf->render();
            $output = $dompdf->output();

            $logger->info('PDF generated successfully');

            return new Response(
                $output,
                200,
                [
                    'Content-Type' => 'application/pdf',
                    'Content-Disposition' => 'attachment; filename="liste_employes.pdf"',
                    'Content-Length' => strlen($output),
                    'Cache-Control' => 'private, max-age=0, must-revalidate',
                    'Pragma' => 'public',
                ]
            );
        } catch (\Exception $e) {
            $logger->error('Error during PDF generation: ' . $e->getMessage());
            $logger->error('Stack trace: ' . $e->getTraceAsString());

            return new JsonResponse([
                'error' => 'Erreur lors de la génération du PDF.',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}