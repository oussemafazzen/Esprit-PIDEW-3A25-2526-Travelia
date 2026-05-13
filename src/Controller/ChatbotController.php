<?php

namespace App\Controller;

use App\Service\GroqChatService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/chatbot')]
final class ChatbotController extends AbstractController
{
    public function __construct(private readonly GroqChatService $groqChatService)
    {
    }

    /**
     * POST /api/chatbot/chat
     *
     * Body (JSON):
     * {
     *   "message": "Quel est le tarif du Plaza ?",
     *   "history": [
     *     {"role": "user",      "content": "Bonjour"},
     *     {"role": "assistant", "content": "Bonjour ! Comment puis-je vous aider ?"}
     *   ]
     * }
     *
     * Response (JSON):
     * { "reply": "The Plaza est à New York..." }
     */
    #[Route('/chat', name: 'app_chatbot_chat', methods: ['POST'])]
    public function chat(Request $request): JsonResponse
    {
        $body = json_decode($request->getContent(), true);

        $userMessage = trim($body['message'] ?? '');
        $history     = $body['history'] ?? [];

        if ($userMessage === '') {
            return $this->json(['error' => 'Message vide.'], Response::HTTP_BAD_REQUEST);
        }

        // Sanitise history: only keep role + content keys, guard against XSS
        $cleanHistory = [];
        foreach ($history as $turn) {
            $role    = in_array($turn['role'] ?? '', ['user', 'assistant'], true) ? $turn['role'] : null;
            $content = isset($turn['content']) ? substr(strip_tags((string) $turn['content']), 0, 2000) : null;
            if ($role !== null && $content !== null) {
                $cleanHistory[] = ['role' => $role, 'content' => $content];
            }
        }

        // Cap history at last 20 turns to avoid token overflow
        if (count($cleanHistory) > 20) {
            $cleanHistory = array_slice($cleanHistory, -20);
        }

        $reply = $this->groqChatService->chat($cleanHistory, $userMessage);

        return $this->json(['reply' => $reply]);
    }
}
