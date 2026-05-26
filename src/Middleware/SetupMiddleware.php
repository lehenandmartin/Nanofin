<?php

declare(strict_types=1);

namespace Nanofin\Middleware;

use Nanofin\Models\UserModel;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as Handler;

/**
 * SetupMiddleware — global first-run gate.
 *
 * Applied to EVERY route (added to the app, not a group).
 *
 * Rules:
 *  - If no admin user exists AND the request is not already targeting /setup
 *    → redirect to /setup.
 *  - If an admin user EXISTS and the request targets /setup
 *    → redirect to / (setup already done).
 *  - Otherwise → pass through.
 */
final class SetupMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly ResponseFactoryInterface $responseFactory,
        private readonly UserModel                $users,
    ) {}

    public function process(Request $request, Handler $handler): Response
    {
        $path        = $request->getUri()->getPath();
        $setupPrefix = app_url('/setup');
        $isSetup     = str_starts_with($path, $setupPrefix);
        $adminExists = $this->users->adminExists();

        // No admin yet and not already on /setup → force wizard
        if (!$adminExists && !$isSetup) {
            return $this->responseFactory->createResponse(302)
                ->withHeader('Location', app_url('/setup'));
        }

        // Admin exists and someone tries to access /setup → block
        if ($adminExists && $isSetup) {
            return $this->responseFactory->createResponse(302)
                ->withHeader('Location', app_url('/'));
        }

        return $handler->handle($request);
    }
}
