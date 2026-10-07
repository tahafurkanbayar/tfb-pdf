<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Core\Config;
use App\Core\Container;
use App\Core\Url;
use App\Core\View;
use App\Http\Response;
use App\Security\OwnerContext;

/**
 * Controller'lar incedir: girdiyi okur, servisi çağırır, yanıt döner. İş mantığı servislerdedir.
 */
abstract class Controller
{
    public function __construct(protected readonly Container $container)
    {
    }

    /**
     * @template T of object
     * @param class-string<T> $class
     * @return T
     */
    protected function service(string $class): object
    {
        return $this->container->get($class);
    }

    /**
     * @param array<string, mixed> $data
     */
    protected function view(string $template, array $data = [], int $status = 200): Response
    {
        return Response::html($this->service(View::class)->render($template, $data), $status);
    }

    /**
     * @param array<string, mixed> $data
     */
    protected function json(array $data = [], int $status = 200): Response
    {
        return Response::json(['ok' => true] + $data, $status);
    }

    protected function url(): Url
    {
        return $this->service(Url::class);
    }

    protected function config(): Config
    {
        return $this->service(Config::class);
    }

    protected function owner(): OwnerContext
    {
        return $this->service(OwnerContext::class);
    }
}
