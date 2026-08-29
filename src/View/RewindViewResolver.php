<?php

/*
 * This file is part of huseyinfiliz/rewind.
 *
 * Copyright (c) 2026 Hüseyin Filiz.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace HuseyinFiliz\Rewind\View;

use Flarum\Foundation\Paths;
use Illuminate\Contracts\View\Factory as ViewFactory;

class RewindViewResolver
{
    protected string $customStorageViewsPath;
    protected bool $namespaceRegistered = false;

    public function __construct(
        protected Paths $paths,
        protected ViewFactory $viewFactory,
    ) {
        $this->customStorageViewsPath = $this->paths->storage.'/rewind/views';
        $this->registerCustomNamespace();
    }

    public function registerCustomNamespace(): void
    {
        if ($this->namespaceRegistered) {
            return;
        }

        if (! is_dir($this->customStorageViewsPath)) {
            @mkdir($this->customStorageViewsPath, 0755, true);
        }

        if (is_dir($this->customStorageViewsPath)) {
            $this->viewFactory->addNamespace('rewind-custom', $this->customStorageViewsPath);
            $this->namespaceRegistered = true;
        }
    }

    public function resolveUserView(int $year): string
    {
        $this->registerCustomNamespace();

        if ($this->viewFactory->exists("rewind-custom::user_{$year}")) {
            return "rewind-custom::user_{$year}";
        }

        if ($this->viewFactory->exists('rewind-custom::user')) {
            return 'rewind-custom::user';
        }

        if ($this->viewFactory->exists("rewind::user_{$year}")) {
            return "rewind::user_{$year}";
        }

        return 'rewind::user';
    }

    public function resolveCommunityView(int $year): string
    {
        $this->registerCustomNamespace();

        if ($this->viewFactory->exists("rewind-custom::community_{$year}")) {
            return "rewind-custom::community_{$year}";
        }

        if ($this->viewFactory->exists('rewind-custom::community')) {
            return 'rewind-custom::community';
        }

        if ($this->viewFactory->exists("rewind::community_{$year}")) {
            return "rewind::community_{$year}";
        }

        return 'rewind::community';
    }

    public function resolveErrorView(): string
    {
        $this->registerCustomNamespace();

        if ($this->viewFactory->exists('rewind-custom::error')) {
            return 'rewind-custom::error';
        }

        return 'rewind::error';
    }

    public function render(string $view, array $data = []): string
    {
        return $this->viewFactory->make($view, $data)->render();
    }

    public function getCustomStoragePath(): string
    {
        return $this->customStorageViewsPath;
    }
}
