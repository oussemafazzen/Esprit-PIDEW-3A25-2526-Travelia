<?php

namespace App\Controller;

use App\Entity\Client;
use App\Form\RegistrationType;
use App\Repository\ClientRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Intl\Countries;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use App\Entity\PasswordResetToken;
use App\Repository\PasswordResetTokenRepository;

class RegistrationController extends AbstractController
{
    #[Route('/register', name: 'app_register')]
    public function register(Request $request, UserPasswordHasherInterface $userPasswordHasher, EntityManagerInterface $entityManager, MailerInterface $mailer): Response
    {
        $user = new Client();
        $form = $this->createForm(RegistrationType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Combine phone prefix and number
            $phonePrefix = $form->get('phone_prefix')->getData();
            $phoneNumber = $form->get('telephone')->getData();
            $user->setTelephone($phonePrefix . ' ' . $phoneNumber);

            // Encode the password
            /** @var string $plainPassword */
            $plainPassword = $form->get('password')->getData();
            $user->setPassword(
                $userPasswordHasher->hashPassword(
                    $user,
                    $plainPassword
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

            // Generate 6-digit verification code using existing table
            $code = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            $verificationToken = new PasswordResetToken();
            $verificationToken->setUser($user);
            $verificationToken->setToken($code);
            $verificationToken->setExpiryDate((new \DateTime())->modify('+24 hours'));
            $entityManager->persist($verificationToken);
            $entityManager->flush();

            // Send Email
            $emailMessage = (new Email())
                ->from('admintravelia@gmail.com')
                ->to((string)$user->getEmail())
                ->subject('Vérifiez votre compte Travelia')
                ->html($this->renderView('emails/registration_verify.html.twig', [
                    'code' => $code,
                    'user' => $user
                ]));

            $mailer->send($emailMessage);

            // Save email in session for verification page
            $request->getSession()->set('registration_email', $user->getEmail());

            // Save face encoding if provided
            /** @var string|null $faceEncoding */
            $faceEncoding = $request->request->get('face_encoding');
            if ($faceEncoding) {
                $faceData = new \App\Entity\FaceData();
                $faceData->setUser($user);
                $faceData->setFaceEncoding($faceEncoding);
                $faceData->setFaceToken(bin2hex(random_bytes(16)));
                $entityManager->persist($faceData);
                $entityManager->flush();
            }

            // Redirect to verification page
            return $this->redirectToRoute('app_register_verify');
        }

        return $this->render('registration/register.html.twig', [
            'registrationForm' => $form->createView(),
        ]);
    }

    #[Route('/register/verify', name: 'app_register_verify')]
    public function verifyEmail(Request $request, ClientRepository $clientRepository, PasswordResetTokenRepository $tokenRepository, EntityManagerInterface $entityManager): Response
    {
        $email = $request->getSession()->get('registration_email');
        if (!$email) {
            return $this->redirectToRoute('app_register');
        }

        if ($request->isMethod('POST')) {
            $code = $request->request->get('code');
            $user = $clientRepository->findOneBy(['email' => $email]);

            if ($user) {
                $token = $tokenRepository->findOneBy([
                    'user' => $user,
                    'token' => $code,
                    'used' => false
                ]);

                if ($token && !$token->isExpired()) {
                    $user->setEmailConfirmed(true);
                    $token->setUsed(true);
                    $entityManager->flush();

                    $request->getSession()->remove('registration_email');
                    $this->addFlash('success', 'Votre compte a été vérifié avec succès. Vous pouvez maintenant vous connecter.');
                    return $this->redirectToRoute('app_login');
                }
            }

            $this->addFlash('error', 'Code de vérification invalide ou expiré.');
        }

        return $this->render('registration/verify.html.twig', [
            'email' => $email
        ]);
    }
}
