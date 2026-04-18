<?php

namespace App\Controller;

use App\Entity\PasswordResetToken;
use App\Repository\ClientRepository;
use App\Repository\PasswordResetTokenRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

class ResetPasswordController extends AbstractController
{
    #[Route('/forgot-password', name: 'app_forgot_password_request')]
    public function request(Request $request, ClientRepository $clientRepository, EntityManagerInterface $entityManager, MailerInterface $mailer): Response
    {
        if ($request->isMethod('POST')) {
            $email = $request->request->get('email');
            $user = $clientRepository->findOneBy(['email' => $email]);

            if ($user) {
                // Generate 6-digit code
                $code = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
                
                $resetToken = new PasswordResetToken();
                $resetToken->setUser($user);
                $resetToken->setToken($code);
                $resetToken->setExpiryDate((new \DateTime())->modify('+15 minutes'));
                
                $entityManager->persist($resetToken);
                $entityManager->flush();

                // Send Email
                $emailMessage = (new Email())
                    ->from('admintravelia@gmail.com')
                    ->to($user->getEmail())
                    ->subject('Votre code de réinitialisation Travelia')
                    ->html($this->renderView('emails/reset_password.html.twig', [
                        'code' => $code,
                        'user' => $user
                    ]));

                $mailer->send($emailMessage);

                // Store email in session for the next step
                $request->getSession()->set('reset_password_email', $email);

                return $this->redirectToRoute('app_forgot_password_verify');
            }

            $this->addFlash('error', 'Aucun utilisateur trouvé avec cet email.');
        }

        return $this->render('security/reset_password/request.html.twig');
    }

    #[Route('/forgot-password/verify', name: 'app_forgot_password_verify')]
    public function verify(Request $request, ClientRepository $clientRepository, PasswordResetTokenRepository $tokenRepository): Response
    {
        $email = $request->getSession()->get('reset_password_email');
        if (!$email) {
            return $this->redirectToRoute('app_forgot_password_request');
        }

        if ($request->isMethod('POST')) {
            $code = $request->request->get('code');
            $user = $clientRepository->findOneBy(['email' => $email]);
            
            if ($user) {
                $resetToken = $tokenRepository->findOneBy([
                    'user' => $user,
                    'token' => $code,
                    'used' => false
                ]);

                if ($resetToken && !$resetToken->isExpired()) {
                    $request->getSession()->set('reset_password_verified', true);
                    return $this->redirectToRoute('app_forgot_password_reset');
                }
            }

            $this->addFlash('error', 'Code invalide ou expiré.');
        }

        return $this->render('security/reset_password/verify.html.twig', [
            'email' => $email
        ]);
    }

    #[Route('/forgot-password/reset', name: 'app_forgot_password_reset')]
    public function reset(Request $request, ClientRepository $clientRepository, PasswordResetTokenRepository $tokenRepository, UserPasswordHasherInterface $passwordHasher, EntityManagerInterface $entityManager): Response
    {
        if (!$request->getSession()->get('reset_password_verified')) {
            return $this->redirectToRoute('app_forgot_password_request');
        }

        $email = $request->getSession()->get('reset_password_email');
        $user = $clientRepository->findOneBy(['email' => $email]);

        if ($request->isMethod('POST')) {
            $password = $request->request->get('password');
            $confirmPassword = $request->request->get('confirm_password');

            if ($password !== $confirmPassword) {
                $this->addFlash('error', 'Les mots de passe ne correspondent pas.');
            } elseif (strlen($password) < 8) {
                $this->addFlash('error', 'Le mot de passe doit faire au moins 8 caractères.');
            } else {
                // Update password
                $hashedPassword = $passwordHasher->hashPassword($user, $password);
                $user->setPassword($hashedPassword);

                // Mark token as used
                $tokens = $tokenRepository->findBy(['user' => $user, 'used' => false]);
                foreach ($tokens as $token) {
                    $token->setUsed(true);
                }

                $entityManager->flush();

                // Clean session
                $request->getSession()->remove('reset_password_email');
                $request->getSession()->remove('reset_password_verified');

                $this->addFlash('success', 'Votre mot de passe a été réinitialisé avec succès.');
                return $this->redirectToRoute('app_login');
            }
        }

        return $this->render('security/reset_password/reset.html.twig');
    }
}
