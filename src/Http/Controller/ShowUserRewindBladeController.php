<?php

/*
 * This file is part of huseyinfiliz/rewind.
 *
 * Copyright (c) 2026 Hüseyin Filiz.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace HuseyinFiliz\Rewind\Http\Controller;

use Flarum\Frontend\Frontend;
use Flarum\Http\RequestUtil;
use Flarum\Http\UrlGenerator;
use Flarum\Settings\SettingsRepositoryInterface;
use Flarum\User\User;
use HuseyinFiliz\Rewind\Model\RewindSnapshot;
use HuseyinFiliz\Rewind\View\RewindViewResolver;
use Illuminate\Contracts\Container\Container;
use Laminas\Diactoros\Response\HtmlResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class ShowUserRewindBladeController implements RequestHandlerInterface
{
    public function __construct(
        protected RewindViewResolver $resolver,
        protected SettingsRepositoryInterface $settings,
        protected Container $container,
        protected UrlGenerator $url,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $routeParams = (array) $request->getAttribute('routeParameters', []);
        $queryParams = (array) $request->getQueryParams();

        $id = (int) ($routeParams['id'] ?? $queryParams['id'] ?? 0);
        $year = (int) ($routeParams['year'] ?? $queryParams['year'] ?? 0);

        $yearModes = json_decode((string) $this->settings->get('huseyinfiliz-rewind.year_render_modes', '{}'), true) ?: [];
        $renderMode = $yearModes[(string) $year] ?? 'slideshow';

        if ($renderMode === 'slideshow' && $this->container->has('flarum.frontend.forum')) {
            /** @var Frontend $frontend */
            $frontend = $this->container->make('flarum.frontend.forum');

            return new HtmlResponse($frontend->document($request)->render());
        }

        $actor = RequestUtil::getActor($request);

        $enabled = (bool) $this->settings->get('huseyinfiliz-rewind.enabled', false);
        $canModerate = $actor->hasPermission('huseyinfiliz-rewind.moderate');
        $forumTitle = (string) $this->settings->get('forum_title', 'Flarum');
        $baseUrl = rtrim($this->url->to('forum')->base(), '/');

        if (! $enabled && ! $canModerate) {
            return $this->renderError(
                'Rewind is currently disabled.',
                'The forum administrator has temporarily disabled annual recaps.',
                403,
                $forumTitle,
                $baseUrl,
                $actor
            );
        }

        if (! $actor->hasPermission('huseyinfiliz-rewind.viewForum') && ! $canModerate) {
            return $this->renderError(
                'Access Denied',
                'You do not have permission to view Rewind recaps.',
                403,
                $forumTitle,
                $baseUrl,
                $actor
            );
        }

        if ($id <= 0 || $year < 2000 || $year > 2100) {
            return $this->renderError(
                'Invalid Request',
                'The requested user ID or year is invalid.',
                400,
                $forumTitle,
                $baseUrl,
                $actor
            );
        }

        /** @var User|null $user */
        $user = User::find($id);
        if (! $user) {
            return $this->renderError(
                'User Not Found',
                "We could not find a user with ID #{$id}.",
                404,
                $forumTitle,
                $baseUrl,
                $actor
            );
        }

        /** @var RewindSnapshot|null $snapshot */
        $snapshot = RewindSnapshot::where('user_id', $id)->where('year', $year)->first();
        if (! $snapshot) {
            return $this->renderError(
                'Rewind Not Found',
                "No {$year} Rewind snapshot has been generated for @{$user->username} yet.",
                404,
                $forumTitle,
                $baseUrl,
                $actor,
                $user,
                $year
            );
        }

        $isOwner = $actor->id === $user->id;
        if (! $snapshot->is_public && ! $isOwner && ! $canModerate) {
            return $this->renderError(
                'Private Rewind',
                "@{$user->username}'s {$year} Rewind is set to private.",
                403,
                $forumTitle,
                $baseUrl,
                $actor,
                $user,
                $year
            );
        }

        $metrics = is_array($snapshot->data) ? $snapshot->data : [];
        $hiddenSlides = json_decode((string) $this->settings->get('huseyinfiliz-rewind.hidden_user_slides', '[]'), true) ?: [];
        $viewName = $this->resolver->resolveUserView($year);

        $html = $this->resolver->render($viewName, [
            'snapshot' => $snapshot,
            'metrics' => $metrics,
            'user' => $user,
            'actor' => $actor,
            'year' => $year,
            'forumTitle' => $forumTitle,
            'baseUrl' => $baseUrl,
            'isCommunity' => false,
            'isOwner' => $isOwner,
            'canModerate' => $canModerate,
            'hiddenSlides' => $hiddenSlides,
            'viewName' => $viewName,
        ]);

        return new HtmlResponse($html, 200);
    }

    protected function renderError(
        string $title,
        string $message,
        int $statusCode,
        string $forumTitle,
        string $baseUrl,
        User $actor,
        ?User $user = null,
        ?int $year = null
    ): HtmlResponse {
        $errorView = $this->resolver->resolveErrorView();
        $html = $this->resolver->render($errorView, [
            'errorTitle' => $title,
            'errorMessage' => $message,
            'statusCode' => $statusCode,
            'forumTitle' => $forumTitle,
            'baseUrl' => $baseUrl,
            'actor' => $actor,
            'user' => $user,
            'year' => $year,
            'isCommunity' => false,
        ]);

        return new HtmlResponse($html, $statusCode);
    }
}
