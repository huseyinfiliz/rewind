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
use HuseyinFiliz\Rewind\RewindYear;
use HuseyinFiliz\Rewind\Template\RewindTemplateManager;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class ListTemplatesController implements RequestHandlerInterface
{
    public function __construct(
        protected RewindTemplateManager $templateManager,
        protected RewindYear $years,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $actor = RequestUtil::getActor($request);
        if (! $actor->isAdmin()) {
            return new JsonResponse(['error' => 'Permission denied.'], 403);
        }

        $activeYear = $this->years->active();
        $templates = $this->templateManager->listTemplates();

        return new JsonResponse([
            'templates' => $templates,
            'activeYear' => $activeYear,
            'availableTypes' => RewindTemplateManager::ALLOWED_TYPES,
        ]);
    }
}
