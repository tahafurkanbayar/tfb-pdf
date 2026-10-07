<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Düz PHP şablonları (resources/views). Şablon içinde $view değişkeni bu nesnedir.
 *
 *   $view->extend('layouts/app');            // şablonu bir layout içine sar
 *   $view->section('title', __('...'));      // layout'a değer aktar
 *   <?= $view->partial('partials/x', [...]) ?>
 *
 * Tüm dinamik çıktılar e() ile kaçırılmalıdır.
 */
final class View
{
    /** @var array<string, mixed> Tüm şablonlara verilen ortak değişkenler */
    private array $shared = [];

    /** @var array<string, string> */
    private array $sections = [];

    private ?string $layout = null;

    public function __construct(private readonly string $directory)
    {
    }

    public function share(string $key, mixed $value): void
    {
        $this->shared[$key] = $value;
    }

    public function shared(string $key, mixed $default = null): mixed
    {
        return $this->shared[$key] ?? $default;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function render(string $template, array $data = []): string
    {
        $this->layout = null;
        $content = $this->renderFile($template, $data);

        // Layout zinciri (layout da başka bir layout'u genişletebilir)
        while ($this->layout !== null) {
            $layout = $this->layout;
            $this->layout = null;
            $this->sections['content'] = $content;
            $content = $this->renderFile($layout, $data);
        }

        $this->sections = [];

        return $content;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function partial(string $template, array $data = []): string
    {
        $layout = $this->layout;
        $html = $this->renderFile($template, $data);
        $this->layout = $layout;

        return $html;
    }

    public function extend(string $layout): void
    {
        $this->layout = $layout;
    }

    public function section(string $name, string $value): void
    {
        $this->sections[$name] = $value;
    }

    public function yield(string $name, string $default = ''): string
    {
        return $this->sections[$name] ?? $default;
    }

    public function hasSection(string $name): bool
    {
        return isset($this->sections[$name]);
    }

    /**
     * Dekoratif SVG ikon (public/assets/img/icons.svg sprite). Ekran okuyuculardan gizlenir.
     */
    public function icon(string $name, string $class = ''): string
    {
        /** @var Url $url */
        $url = $this->shared['url'];

        return sprintf(
            '<svg class="bi%s" aria-hidden="true" focusable="false"><use href="%s#i-%s"></use></svg>',
            $class === '' ? '' : ' ' . e($class),
            e($url->asset('img/icons.svg')),
            e($name)
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    private function renderFile(string $template, array $data): string
    {
        if (!preg_match('#^[a-z0-9_\-/]+$#', $template) || str_contains($template, '..')) {
            throw new \InvalidArgumentException('Invalid template name: ' . $template);
        }

        $file = $this->directory . '/' . $template . '.php';
        if (!is_file($file)) {
            throw new \InvalidArgumentException('Template not found: ' . $template);
        }

        $view = $this;
        extract($this->shared + $data, EXTR_SKIP);

        ob_start();
        try {
            require $file;
        } catch (\Throwable $e) {
            ob_end_clean();
            throw $e;
        }

        return (string) ob_get_clean();
    }
}
