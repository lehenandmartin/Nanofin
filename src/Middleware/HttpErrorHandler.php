<?php

declare(strict_types=1);

namespace Nanofin\Middleware;

use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Slim\Exception\HttpForbiddenException;
use Slim\Exception\HttpNotFoundException;
use Slim\Exception\HttpException;
use Slim\Handlers\ErrorHandler;
use Slim\Interfaces\CallableResolverInterface;
use Slim\Views\Twig;

/**
 * HttpErrorHandler — renders Twig error templates for HTTP errors.
 *
 * Maps:
 *   404 HttpNotFoundException  → errors/404.twig
 *   403 HttpForbiddenException → errors/403.twig
 *   5xx / other                → errors/500.twig
 *
 * Falls back to a plain HTML response if Twig is unavailable
 * (e.g. when the database itself is unreachable).
 */
final class HttpErrorHandler extends ErrorHandler
{
    public function __construct(
        CallableResolverInterface         $callableResolver,
        ResponseFactoryInterface          $responseFactory,
        private readonly ContainerInterface $container,
    ) {
        parent::__construct($callableResolver, $responseFactory);
    }

    protected function respond(): Response
    {
        $status = 500;

        if ($this->exception instanceof HttpNotFoundException) {
            $status = 404;
        } elseif ($this->exception instanceof HttpForbiddenException) {
            $status = 403;
        } elseif ($this->exception instanceof HttpException) {
            $status = (int) $this->exception->getCode();
        }

        $template = match ($status) {
            403     => 'errors/403.twig',
            404     => 'errors/404.twig',
            default => 'errors/500.twig',
        };

        $response = $this->responseFactory->createResponse($status);

        try {
            /** @var Twig $twig */
            $twig = $this->container->get(Twig::class);
            return $twig->render($response, $template);
        } catch (\Throwable) {
            // Last-resort fallback if Twig/DB is unavailable
            $response->getBody()->write(
                '<html><head><title>Error ' . $status . '</title></head>'
                . '<body><h1>Error ' . $status . '</h1></body></html>'
            );
            return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
        }
    }
}
