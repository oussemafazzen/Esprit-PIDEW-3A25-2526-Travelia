<?php

namespace App\Controller;

use App\Repository\ClientRepository;
use App\Repository\FaceDataRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Http\Event\InteractiveLoginEvent;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class FaceAuthController extends AbstractController
{
    #[Route('/api/face/identify', name: 'api_face_identify', methods: ['POST'])]
    public function identify(
        Request $request,
        FaceDataRepository $faceDataRepository,
        TokenStorageInterface $tokenStorage,
        EventDispatcherInterface $eventDispatcher
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);
        $descriptor = $data['descriptor'] ?? null;

        if (!$descriptor) {
            return new JsonResponse(['error' => 'Descripteur facial requis'], 400);
        }

        $allFaceData = $faceDataRepository->findAll();
        $bestMatch = null;
        $minDistance = 0.6; // Threshold

        foreach ($allFaceData as $faceData) {
            $storedDescriptor = json_decode($faceData->getFaceEncoding(), true);
            if (!$storedDescriptor) continue;

            $distance = $this->euclideanDistance($descriptor, $storedDescriptor);
            if ($distance < $minDistance) {
                $minDistance = $distance;
                $bestMatch = $faceData->getUser();
            }
        }

        if ($bestMatch) {
            // Log the user in manually
            $token = new UsernamePasswordToken($bestMatch, 'main', $bestMatch->getRoles());
            $tokenStorage->setToken($token);

            $event = new InteractiveLoginEvent($request, $token);
            $eventDispatcher->dispatch($event, 'security.interactive_login');

            // Determine redirect based on role
            $redirect = in_array('ROLE_ADMIN', $bestMatch->getRoles())
                ? $this->generateUrl('app_admin_dashboard')
                : $this->generateUrl('app_home');

            return new JsonResponse([
                'success' => true,
                'redirect' => $redirect
            ]);
        }

        return new JsonResponse(['error' => 'Visage non reconnu. Veuillez réessayer ou utiliser votre mot de passe.'], 404);
    }

    /**
     * @param list<float|int> $a
     * @param list<float|int> $b
     */
    private function euclideanDistance(array $a, array $b): float
    {
        $sum = 0;
        $count = min(count($a), count($b));
        for ($i = 0; $i < $count; $i++) {
            $sum += pow($a[$i] - $b[$i], 2);
        }
        return sqrt($sum);
    }
}
