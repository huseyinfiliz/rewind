<?php

/*
 * This file is part of huseyinfiliz/rewind.
 *
 * Copyright (c) 2026 Hüseyin Filiz.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace HuseyinFiliz\Rewind\Template;

use Carbon\Carbon;
use Flarum\Foundation\Paths;
use HuseyinFiliz\Rewind\Template\Exception\InvalidTemplateException;
use HuseyinFiliz\Rewind\Template\Exception\TemplateAlreadyExistsException;
use HuseyinFiliz\Rewind\Template\Exception\TemplateNotFoundException;
use Illuminate\Contracts\View\Factory as ViewFactory;

class RewindTemplateManager
{
    public const ALLOWED_TYPES = ['user', 'community', 'error'];

    protected string $storageViewsPath;
    protected string $builtinViewsPath;

    public function __construct(
        protected Paths $paths,
        protected ?ViewFactory $viewFactory = null,
    ) {
        $this->storageViewsPath = rtrim($this->paths->storage, '/\\').'/rewind/views';
        $this->builtinViewsPath = dirname(__DIR__, 2).'/resources/views';
    }

    public function getStorageViewsPath(): string
    {
        return $this->storageViewsPath;
    }

    public function getBuiltinViewsPath(): string
    {
        return $this->builtinViewsPath;
    }

    /**
     * @throws InvalidTemplateException
     */
    public function buildId(string $type, ?int $year = null): string
    {
        $type = strtolower(trim($type));

        if (! in_array($type, self::ALLOWED_TYPES, true)) {
            throw new InvalidTemplateException("Invalid template type '{$type}'. Allowed types: ".implode(', ', self::ALLOWED_TYPES));
        }

        if ($type === 'error') {
            if ($year !== null && $year !== 0) {
                throw new InvalidTemplateException('The error template cannot be year-specific.');
            }

            return 'error';
        }

        if ($year !== null && $year !== 0) {
            if ($year < 2000 || $year > 2100) {
                throw new InvalidTemplateException('Year must be a whole number between 2000 and 2100.');
            }

            return "{$type}_{$year}";
        }

        return $type;
    }

    /**
     * Parse and validate template ID into metadata.
     *
     * @return array{id: string, type: string, year: ?int, filename: string, title: string, builtinPath: string, customPath: string}|null
     */
    public function parseId(string $id): ?array
    {
        $id = trim($id);

        if ($id === 'error') {
            return [
                'id' => 'error',
                'type' => 'error',
                'year' => null,
                'filename' => 'error.blade.php',
                'title' => 'Error Page (Default)',
                'builtinPath' => $this->builtinViewsPath.'/error.blade.php',
                'customPath' => $this->storageViewsPath.'/error.blade.php',
            ];
        }

        if (preg_match('/^(user|community)(?:_([2-9][0-9]{3}))?$/', $id, $matches)) {
            $type = $matches[1];
            $year = isset($matches[2]) ? (int) $matches[2] : null;

            if ($year !== null && ($year < 2000 || $year > 2100)) {
                return null;
            }

            $typeLabel = ucfirst($type);
            $title = $year ? "{$typeLabel} ({$year})" : "{$typeLabel} (Default Fallback)";
            $filename = "{$id}.blade.php";

            return [
                'id' => $id,
                'type' => $type,
                'year' => $year,
                'filename' => $filename,
                'title' => $title,
                'builtinPath' => $this->builtinViewsPath."/{$type}.blade.php",
                'customPath' => $this->storageViewsPath."/{$filename}",
            ];
        }

        return null;
    }

    /**
     * List all existing custom templates in storage/rewind/views/.
     *
     * @return list<array{id: string, type: string, year: ?int, filename: string, title: string, size: int, modifiedAt: string, isCustom: bool}>
     */
    public function listTemplates(): array
    {
        $this->ensureStorageDirExists();

        if (! is_dir($this->storageViewsPath)) {
            return [];
        }

        $files = scandir($this->storageViewsPath);
        if ($files === false) {
            return [];
        }

        $templates = [];

        foreach ($files as $file) {
            if (! str_ends_with($file, '.blade.php')) {
                continue;
            }

            $id = substr($file, 0, -strlen('.blade.php'));
            $parsed = $this->parseId($id);

            if (! $parsed) {
                continue;
            }

            $filePath = $parsed['customPath'];
            if (! is_file($filePath)) {
                continue;
            }

            $size = filesize($filePath) ?: 0;
            $mtime = filemtime($filePath) ?: time();

            $templates[] = [
                'id' => $parsed['id'],
                'type' => $parsed['type'],
                'year' => $parsed['year'],
                'filename' => $parsed['filename'],
                'title' => $parsed['title'],
                'size' => $size,
                'modifiedAt' => Carbon::createFromTimestamp($mtime)->toIso8601String(),
                'isCustom' => true,
            ];
        }

        // Sort: user first (default, then year desc), community next (default, then year desc), error last
        usort($templates, function (array $a, array $b) {
            $typeOrder = ['user' => 1, 'community' => 2, 'error' => 3];
            $orderA = $typeOrder[$a['type']] ?? 99;
            $orderB = $typeOrder[$b['type']] ?? 99;

            if ($orderA !== $orderB) {
                return $orderA <=> $orderB;
            }

            $yearA = $a['year'] ?? 9999;
            $yearB = $b['year'] ?? 9999;

            return $yearB <=> $yearA;
        });

        return $templates;
    }

    /**
     * @return array{id: string, type: string, year: ?int, filename: string, title: string, size: int, modifiedAt: string, isCustom: bool, content?: string}
     *
     * @throws TemplateNotFoundException
     */
    public function getTemplate(string $id, bool $includeContent = true): array
    {
        $parsed = $this->parseId($id);

        if (! $parsed) {
            throw new TemplateNotFoundException("Template '{$id}' is not a valid template identifier.");
        }

        $filePath = $parsed['customPath'];

        if (! is_file($filePath)) {
            throw new TemplateNotFoundException("Custom template '{$id}' does not exist in storage.");
        }

        $size = filesize($filePath) ?: 0;
        $mtime = filemtime($filePath) ?: time();

        $data = [
            'id' => $parsed['id'],
            'type' => $parsed['type'],
            'year' => $parsed['year'],
            'filename' => $parsed['filename'],
            'title' => $parsed['title'],
            'size' => $size,
            'modifiedAt' => Carbon::createFromTimestamp($mtime)->toIso8601String(),
            'isCustom' => true,
        ];

        if ($includeContent) {
            $data['content'] = (string) file_get_contents($filePath);
        }

        return $data;
    }

    /**
     * Create a new custom template by copying the built-in default.
     *
     * @return array{id: string, type: string, year: ?int, filename: string, title: string, size: int, modifiedAt: string, isCustom: bool, content?: string}
     *
     * @throws InvalidTemplateException
     * @throws TemplateAlreadyExistsException
     */
    public function createTemplate(string $type, ?int $year = null): array
    {
        $id = $this->buildId($type, $year);
        $parsed = $this->parseId($id);

        if (! $parsed) {
            throw new InvalidTemplateException("Could not resolve template '{$id}'.");
        }

        $this->ensureStorageDirExists();

        $destPath = $parsed['customPath'];
        if (file_exists($destPath)) {
            throw new TemplateAlreadyExistsException("Template '{$id}' already exists at storage/rewind/views/{$parsed['filename']}.");
        }

        $builtinPath = $parsed['builtinPath'];
        if (! file_exists($builtinPath)) {
            throw new \RuntimeException("Built-in template '{$builtinPath}' not found.");
        }

        $content = file_get_contents($builtinPath);
        if ($content === false) {
            throw new \RuntimeException("Failed to read built-in template '{$builtinPath}'.");
        }

        if (file_put_contents($destPath, $content, LOCK_EX) === false) {
            throw new \RuntimeException("Failed to write template file to '{$destPath}'.");
        }

        $this->clearCompiledViewCache();

        return $this->getTemplate($id, true);
    }

    /**
     * Save updated content to an existing custom template.
     *
     * @return array{id: string, type: string, year: ?int, filename: string, title: string, size: int, modifiedAt: string, isCustom: bool, content?: string}
     *
     * @throws TemplateNotFoundException
     */
    public function updateTemplate(string $id, string $content): array
    {
        $parsed = $this->parseId($id);

        if (! $parsed) {
            throw new TemplateNotFoundException("Template '{$id}' is not a valid template identifier.");
        }

        $destPath = $parsed['customPath'];
        if (! file_exists($destPath)) {
            throw new TemplateNotFoundException("Custom template '{$id}' does not exist. Create it first before editing.");
        }

        if (file_put_contents($destPath, $content, LOCK_EX) === false) {
            throw new \RuntimeException("Failed to save template file '{$destPath}'.");
        }

        $this->clearCompiledViewCache();

        return $this->getTemplate($id, true);
    }

    /**
     * Delete an existing custom template.
     *
     * @throws TemplateNotFoundException
     */
    public function deleteTemplate(string $id): void
    {
        $parsed = $this->parseId($id);

        if (! $parsed) {
            throw new TemplateNotFoundException("Template '{$id}' is not a valid template identifier.");
        }

        $destPath = $parsed['customPath'];
        if (! file_exists($destPath)) {
            throw new TemplateNotFoundException("Custom template '{$id}' does not exist.");
        }

        if (! @unlink($destPath)) {
            throw new \RuntimeException("Failed to delete template file '{$destPath}'.");
        }

        $this->clearCompiledViewCache();
    }

    public function ensureStorageDirExists(): void
    {
        if (! is_dir($this->storageViewsPath)) {
            @mkdir($this->storageViewsPath, 0755, true);
        }
    }

    /**
     * Clears compiled Blade view cache files so changes are immediately visible.
     */
    public function clearCompiledViewCache(): void
    {
        if ($this->viewFactory && method_exists($this->viewFactory, 'flushFinderCache')) {
            $this->viewFactory->flushFinderCache();
        }

        $compiledViewsDir = rtrim($this->paths->storage, '/\\').'/views';

        if (! is_dir($compiledViewsDir)) {
            return;
        }

        $files = glob($compiledViewsDir.'/*.php');
        if (is_array($files)) {
            foreach ($files as $file) {
                if (is_file($file)) {
                    @unlink($file);
                }
            }
        }
    }
}
