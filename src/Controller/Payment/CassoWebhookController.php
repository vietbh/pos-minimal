<?php
declare(strict_types=1);
namespace App\Controller\Payment;
use App\Application\Payment\Casso\CassoWebhookHandler;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class CassoWebhookController extends AbstractController
{
    #[Route('/webhooks/casso', name:'webhook_casso', methods:['POST'], format:'json')]
    public function __invoke(Request $request, CassoWebhookHandler $handler): JsonResponse
    {
        $expected = (string)($_ENV['CASSO_WEBHOOK_TOKEN'] ?? getenv('CASSO_WEBHOOK_TOKEN') ?: '');
        $provided = (string)$request->headers->get('X-Casso-Token', $request->headers->get('Authorization', ''));
        if ($expected === '' || !hash_equals($expected, $provided)) {
            return $this->json(['errorCode'=>'WEBHOOK_UNAUTHORIZED','message'=>'Invalid webhook token.'], Response::HTTP_UNAUTHORIZED);
        }

        try {
            $payload=$request->toArray();
            $status=$handler->handle($payload);
            return $this->json(['ok'=>true,'status'=>$status]);
        } catch (\JsonException|\InvalidArgumentException $e) {
            return $this->json(['errorCode'=>'VALIDATION_ERROR','message'=>$e->getMessage()],422);
        } catch (\DomainException $e) {
            return $this->json(['errorCode'=>'CONFIGURATION_ERROR','message'=>$e->getMessage()],422);
        } catch (\Throwable) {
            return $this->json(['errorCode'=>'INTERNAL_ERROR','message'=>'Unable to process webhook.'],500);
        }
    }
}
