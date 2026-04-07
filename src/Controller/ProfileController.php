<?php

namespace App\Controller;

use App\Entity\Client;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[Route('/profile')]
#[IsGranted('ROLE_USER')]
class ProfileController extends AbstractController
{
    #[Route('/', name: 'app_profile')]
    public function index(Request $request, EntityManagerInterface $entityManager, UserPasswordHasherInterface $passwordHasher): Response
    {
        /** @var Client $user */
        $user = $this->getUser();

        if ($request->isMethod('POST')) {
            $user->setNom($request->request->get('nom'));
            $user->setPrenom($request->request->get('prenom'));
            $user->setEmail($request->request->get('email'));
            $user->setTelephone($request->request->get('telephone'));
            $user->setNationalite($request->request->get('nationalite'));
            
            $dob = $request->request->get('date_naissance');
            if ($dob) {
                $user->setDateNaissance(new \DateTime($dob));
            }

            // Gestion du changement de mot de passe
            $plainPassword = $request->request->get('password');
            $confirmPassword = $request->request->get('confirm_password');

            if (!empty($plainPassword)) {
                if ($plainPassword !== $confirmPassword) {
                    $this->addFlash('error', 'Les mots de passe ne correspondent pas.');
                    return $this->redirectToRoute('app_profile');
                }

                // Vérification de la complexité (identique aux contraintes dans l'entité Client)
                if (strlen($plainPassword) < 8 || !preg_match("/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).+$/", $plainPassword)) {
                    $this->addFlash('error', 'Le mot de passe doit faire au moins 8 caractères et contenir une majuscule, une minuscule et un chiffre.');
                    return $this->redirectToRoute('app_profile');
                }

                $hashedPassword = $passwordHasher->hashPassword($user, $plainPassword);
                $user->setPassword($hashedPassword);
            }

            $entityManager->flush();

            $this->addFlash('success', 'Vos informations ont été mises à jour avec succès.');
            return $this->redirectToRoute('app_profile');
        }

        return $this->render('profile/index.html.twig', [
            'user' => $user,
        ]);
    }
}
