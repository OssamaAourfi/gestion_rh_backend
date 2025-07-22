<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\EmployeeRepository;
use App\Repository\CongeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/admin')]
#[IsGranted('ROLE_ADMIN')]
class AdminController extends AbstractController
{
    /**
     * Get all users with their roles
     */
    #[Route('/users', name: 'admin_users_list', methods: ['GET'])]
    public function listUsers(EntityManagerInterface $em): JsonResponse
    {
        $users = $em->getRepository(User::class)->findAll();
        $data = [];

        foreach ($users as $user) {
            $data[] = [
                'id' => $user->getId(),
                'email' => $user->getEmail(),
                'roles' => $user->getRoles(),
            ];
        }

        return $this->json($data);
    }

    /**
     * Assign roles to a user
     */
    #[Route('/users/{id}/roles', name: 'admin_assign_roles', methods: ['PUT'])]
    public function assignRoles(User $user, Request $request, EntityManagerInterface $em): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!isset($data['roles']) || !is_array($data['roles'])) {
            return $this->json(['message' => 'Roles array is required'], 400);
        }

        $validRoles = [User::ROLE_USER, User::ROLE_MANAGER, User::ROLE_ADMIN];
        $roles = [];

        foreach ($data['roles'] as $role) {
            if (in_array($role, $validRoles)) {
                $roles[] = $role;
            }
        }

        $user->setRoles($roles);
        $em->flush();

        return $this->json([
            'message' => 'Roles assigned successfully',
            'user' => [
                'id' => $user->getId(),
                'email' => $user->getEmail(),
                'roles' => $user->getRoles(),
            ]
        ]);
    }

    /**
     * Add a single role to a user
     */
    #[Route('/users/{id}/roles/add', name: 'admin_add_role', methods: ['POST'])]
    public function addRole(User $user, Request $request, EntityManagerInterface $em): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!isset($data['role'])) {
            return $this->json(['message' => 'Role is required'], 400);
        }

        $validRoles = [User::ROLE_USER, User::ROLE_MANAGER, User::ROLE_ADMIN];

        if (!in_array($data['role'], $validRoles)) {
            return $this->json(['message' => 'Invalid role'], 400);
        }

        $user->addRole($data['role']);
        $em->flush();

        return $this->json([
            'message' => 'Role added successfully',
            'user' => [
                'id' => $user->getId(),
                'email' => $user->getEmail(),
                'roles' => $user->getRoles(),
            ]
        ]);
    }

    /**
     * Remove a single role from a user
     */
    #[Route('/users/{id}/roles/remove', name: 'admin_remove_role', methods: ['POST'])]
    public function removeRole(User $user, Request $request, EntityManagerInterface $em): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!isset($data['role'])) {
            return $this->json(['message' => 'Role is required'], 400);
        }

        $user->removeRole($data['role']);
        $em->flush();

        return $this->json([
            'message' => 'Role removed successfully',
            'user' => [
                'id' => $user->getId(),
                'email' => $user->getEmail(),
                'roles' => $user->getRoles(),
            ]
        ]);
    }

    
}