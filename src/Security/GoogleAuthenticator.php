<?php

namespace App\Security;

use App\Entity\Client;
use Doctrine\ORM\EntityManagerInterface;
use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use KnpU\OAuth2ClientBundle\Security\Authenticator\OAuth2Authenticator;
use League\OAuth2\Client\Provider\GoogleUser;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\Util\TargetPathTrait;

class GoogleAuthenticator extends OAuth2Authenticator
{
    use TargetPathTrait;

    public function __construct(
        private ClientRegistry $clientRegistry,
        private EntityManagerInterface $entityManager,
        private RouterInterface $router
    ) {
    }

    public function supports(Request $request): ?bool
    {
        // continue ONLY if the current ROUTE matches the check ROUTE
        return $request->attributes->get('_route') === 'connect_google_check';
    }

    public function authenticate(Request $request): Passport
    {
        $client = $this->clientRegistry->getClient('google');
        $accessToken = $this->fetchAccessToken($client);

        return new SelfValidatingPassport(
            new UserBadge($accessToken->getToken(), function () use ($accessToken, $client) {
                /** @var GoogleUser $googleUser */
                $googleUser = $client->fetchUserFromToken($accessToken);

                $email = $googleUser->getEmail();

                // 1) Have they logged in with Google before? Easy!
                $existingUser = $this->entityManager->getRepository(Client::class)->findOneBy([
                    'google_id' => $googleUser->getId(),
                ]);

                if ($existingUser) {
                    return $existingUser;
                }

                // 2) Do we have a matching user by email?
                $existingUser = $this->entityManager->getRepository(Client::class)->findOneBy([
                    'email' => $email,
                ]);

                if ($existingUser) {
                    // Update their google_id and return them
                    $existingUser->setGoogleId($googleUser->getId());
                    // Also confirm email since it's from Google
                    $existingUser->setEmailConfirmed(true);
                    $this->entityManager->flush();
                    return $existingUser;
                }

                // 3) Maybe you just want to "register" them now
                $user = new Client();
                $user->setEmail($email);
                $user->setGoogleId($googleUser->getId());
                $user->setNom($googleUser->getLastName() ?: 'Utilisateur');
                $user->setPrenom($googleUser->getFirstName() ?: 'Google');
                $user->setRole('USER');
                $user->setStatut('ACTIF');
                $user->setEmailConfirmed(true);
                // Set a dummy password because it's required
                $user->setPassword(bin2hex(random_bytes(16)));
                
                $this->entityManager->persist($user);
                $this->entityManager->flush();

                return $user;
            })
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        if ($targetPath = $this->getTargetPath($request->getSession(), $firewallName)) {
            return new RedirectResponse($targetPath);
        }

        $roles = $token->getRoleNames();
        if (in_array('ROLE_ADMIN', $roles, true)) {
            return new RedirectResponse($this->router->generate('app_admin_dashboard'));
        }

        return new RedirectResponse($this->router->generate('app_client_dashboard'));
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        $message = strtr($exception->getMessageKey(), $exception->getMessageData());

        return new Response($message, Response::HTTP_FORBIDDEN);
    }
}
