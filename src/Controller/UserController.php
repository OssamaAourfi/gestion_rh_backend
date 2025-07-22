<?php

namespace App\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Util\Json;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

final class UserController extends AbstractController
{
    #[Route('/api/register', name: 'app_register', methods: ['POST'])]
    public function register(Request $request, UserPasswordHasherInterface $passwordhasher,EntityManagerInterface $em): JsonResponse
    {
        $data = json_decode($request->getContent(),true);
         $email = $data['email']?? null;
         $password = $data['password'] ?? null;
         if(!$email || !$password ){
            return $this->json(['message'=> 'Email or Password are required'],400);
         }
         $user = new User();
         $user->setEmail($email);
         $user->setRoles(['ROLE_USER']);
         $hashedPassword = $passwordhasher->hashPassword($user,$password);
         $user->setPassword($hashedPassword);
         $em->persist($user);
         $em->flush();
        return $this->json(['message'=>'User registred succefully']);
    }
}
