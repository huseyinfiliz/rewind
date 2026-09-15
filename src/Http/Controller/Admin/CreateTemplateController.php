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
use HuseyinFiliz\Rewind\Template\Exception\InvalidTemplateException;
use HuseyinFiliz\Rewind\Template\Exception\TemplateAlreadyExistsException;
use HuseyinFiliz\Rewind\Template\RewindTemplateManager;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class CreateTemplateController implements RequestHandlerInterface
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

        $body = $request->getParsedBody();
        if (empty($body)) {
            $raw = (string) $request->getBody();
            $body = json_decode($raw, true) ?: [];
        }

        $type = (string) ($body['type'] ?? '');
        $year = isset($body['year']) && $body['year'] !== '' ? (int) $body['year'] : null;

        try {
            $template = $this->templateManager->createTemplate($type, $year);

            return new JsonResponse([
                'template' => $template,
            ], 201);
        } catch (InvalidTemplateException $e) {
            return new JsonResponse([
                'error' => $e->getMessage(),
            ], 400);
        } catch (TemplateAlreadyExistsException $e) {
            return new JsonResponse([
                'error' => $e->getMessage(),
            ], 409);
        } catch (\Throwable $e) {
            return new JsonResponse([
                'error' => 'Failed to create template: '.$e->getMessage(),
            ], 500);
        }
    }
}
