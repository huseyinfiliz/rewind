<?php

/*
 * This file is part of huseyinfiliz/rewind.
 *
 * Copyright (c) 2026 Hüseyin Filiz.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace HuseyinFiliz\Rewind\Http\Controller\Admin;

use Flarum\Http\RequestUtil;
use HuseyinFiliz\Rewind\Template\Exception\TemplateNotFoundException;
use HuseyinFiliz\Rewind\Template\RewindTemplateManager;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class DeleteTemplateController implements RequestHandlerInterface
{
    public function __construct(
        protected RewindTemplateManager $templateManager,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $actor = RequestUtil::getActor($request);
        if (! $actor->isAdmin()) {
            return new JsonResponse(['error' => 'Permission denied.'], 403);
        }

        $routeParams = (array) $request->getAttribute('routeParameters', []);
        $id = (string) ($routeParams['id'] ?? '');

        try {
            $this->templateManager->deleteTemplate($id);

            return new JsonResponse([
                'success' => true,
            ]);
        } catch (TemplateNotFoundException $e) {
            return new JsonResponse([
                'error' => $e->getMessage(),
            ], 404);
        } catch (\Throwable $e) {
            return new JsonResponse([
                'error' => 'Failed to delete template: '.$e->getMessage(),
            ], 500);
        }
    }
}
