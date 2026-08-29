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
use HuseyinFiliz\Rewind\Model\CommunitySnapshot;
use HuseyinFiliz\Rewind\View\RewindViewResolver;
use Illuminate\Contracts\Container\Container;
use Laminas\Diactoros\Response\HtmlResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class ShowCommunityRewindBladeController implements RequestHandlerInterface
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

        $year = (int) ($routeParams['year'] ?? $queryParams['year'] ?? 0);
        if ($year === 0) {
            $year = (int) $this->settings->get('huseyinfiliz-rewind.active_year', (int) date('Y'));
        }

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
                $actor,
                $year
            );
        }

        if (! $actor->hasPermission('huseyinfiliz-rewind.viewForum') && ! $canModerate) {
            return $this->renderError(
                'Access Denied',
                'You do not have permission to view Rewind recaps.',
                403,
                $forumTitle,
                $baseUrl,
                $actor,
                $year
            );
        }

        if ($year < 2000 || $year > 2100) {
            return $this->renderError(
                'Invalid Year',
                'The requested year is outside the valid range (2000-2100).',
                400,
                $forumTitle,
                $baseUrl,
                $actor,
                $year
            );
        }

        /** @var CommunitySnapshot|null $snapshot */
        $snapshot = CommunitySnapshot::where('year', $year)->first();
        if (! $snapshot) {
            return $this->renderError(
                'Community Rewind Not Found',
                "The {$year} Community Rewind has not been generated yet.",
                404,
                $forumTitle,
                $baseUrl,
                $actor,
                $year
            );
        }

        $metrics = is_array($snapshot->data) ? $snapshot->data : [];
        $hiddenSlides = json_decode((string) $this->settings->get('huseyinfiliz-rewind.hidden_community_slides', '[]'), true) ?: [];
        $viewName = $this->resolver->resolveCommunityView($year);

        $html = $this->resolver->render($viewName, [
            'snapshot' => $snapshot,
            'metrics' => $metrics,
            'actor' => $actor,
            'year' => $year,
            'forumTitle' => $forumTitle,
            'baseUrl' => $baseUrl,
            'isCommunity' => true,
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
            'year' => $year,
            'isCommunity' => true,
        ]);

        return new HtmlResponse($html, $statusCode);
    }
}
