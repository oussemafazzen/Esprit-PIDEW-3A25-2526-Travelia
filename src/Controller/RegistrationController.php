<?php

namespace App\Controller;

use App\Entity\Client;
use App\Entity\FaceData;
use App\Form\RegistrationType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Intl\Countries;

class RegistrationController extends AbstractController
{
    #[Route('/register', name: 'app_register')]
    public function register(Request $request, UserPasswordHasherInterface $userPasswordHasher, EntityManagerInterface $entityManager): Response
    {
        $user = new Client();
        $form = $this->createForm(RegistrationType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Encode the password
            $user->setPassword(
                $userPasswordHasher->hashPassword(
                    $user,
                    $form->get('password')->getData()
                )
            );

            // Convert ISO country code to full name
            $countryCode = $user->getNationalite();
            if ($countryCode && strlen($countryCode) === 2) {
                $user->setNationalite(Countries::getName($countryCode, 'fr'));
            }

            // Default role is USER, status is ACTIF (set in Entity)

            $entityManager->persist($user);
            $entityManager->flush();

            // Save face encoding if provided during registration
            $faceEncoding = $request->request->get('face_encoding');
            if ($faceEncoding) {
                $faceData = new FaceData();
                $faceData->setUser($user);
                $faceData->setFaceEncoding($faceEncoding);
                $faceData->setFaceToken(bin2hex(random_bytes(16)));
                $entityManager->persist($faceData);
                $entityManager->flush();
            }

            // Redirect to login after successful registration
            return $this->redirectToRoute('app_login');
        }

        return $this->render('registration/register.html.twig', [
            'registrationForm' => $form->createView(),
        ]);
    }
}
