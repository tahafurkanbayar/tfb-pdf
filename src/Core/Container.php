<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Minimal servis kabı: elle tanımlanan, ilk kullanımda oluşturulan tekil servisler.
 * Otomatik bağımlılık çözümleme (autowiring) bilinçli olarak yok; bağımlılıklar
 * bootstrap/services.php içinde açıkça görülür.
 */
final class Container
{
    /** @var array<string, \Closure(self): mixed> */
    private array $factories = [];

    /** @var array<string, mixed> */
    private array $instances = [];

    /**
     * @param \Closure(self): mixed $factory
     */
    public function set(string $id, \Closure $factory): void
    {
        $this->factories[$id] = $factory;
        unset($this->instances[$id]);
    }

    public function instance(string $id, mixed $value): void
    {
        $this->instances[$id] = $value;
    }

    /**
     * @template T of object
     * @param class-string<T>|string $id
     * @return ($id is class-string<T> ? T : mixed)
     */
    public function get(string $id): mixed
    {
        if (array_key_exists($id, $this->instances)) {
            return $this->instances[$id];
        }
        if (!isset($this->factories[$id])) {
            throw new \LogicException('Service not defined: ' . $id);
        }

        return $this->instances[$id] = ($this->factories[$id])($this);
    }

    /**
     * Oluşturulmuş örneği atar; tanım kalır ve bir sonraki get() yeniden oluşturur.
     * İsteğe bağlı (request-scoped) servisler için.
     */
    public function reset(string $id): void
    {
        if (isset($this->factories[$id])) {
            unset($this->instances[$id]);
        }
    }

    public function has(string $id): bool
    {
        return isset($this->factories[$id]) || array_key_exists($id, $this->instances);
    }
}
