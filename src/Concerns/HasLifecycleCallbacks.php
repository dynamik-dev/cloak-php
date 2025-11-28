<?php

declare(strict_types=1);

namespace DynamikDev\Cloak\Concerns;

/**
 * Provides lifecycle callback functionality for the Cloak class.
 */
trait HasLifecycleCallbacks
{
    /** @var array<int, callable(string): string> */
    protected array $beforeCloakCallbacks = [];

    /** @var array<int, callable(string, string): void> */
    protected array $afterCloakCallbacks = [];

    /** @var array<int, callable(string): string> */
    protected array $beforeUncloakCallbacks = [];

    /** @var array<int, callable(string): string> */
    protected array $afterUncloakCallbacks = [];

    /**
     * Register a callback to execute before cloaking.
     * Can be used to pre-process or normalize input text.
     *
     * @param callable(string): string $callback
     * @return $this
     */
    public function beforeCloak(callable $callback): self
    {
        $this->beforeCloakCallbacks[] = $callback;

        return $this;
    }

    /**
     * Register a callback to execute after cloaking.
     * Useful for logging, metrics, or auditing.
     *
     * @param callable(string, string): void $callback Receives original and cloaked text
     * @return $this
     */
    public function afterCloak(callable $callback): self
    {
        $this->afterCloakCallbacks[] = $callback;

        return $this;
    }

    /**
     * Register a callback to execute before uncloaking.
     * Can be used for validation or authorization checks.
     *
     * @param callable(string): string $callback
     * @return $this
     */
    public function beforeUncloak(callable $callback): self
    {
        $this->beforeUncloakCallbacks[] = $callback;

        return $this;
    }

    /**
     * Register a callback to execute after uncloaking.
     * Can be used for post-processing or audit trails.
     *
     * @param callable(string): string $callback
     * @return $this
     */
    public function afterUncloak(callable $callback): self
    {
        $this->afterUncloakCallbacks[] = $callback;

        return $this;
    }

    /**
     * Execute all beforeCloak callbacks in sequence.
     */
    protected function executeBeforeCloakCallbacks(string $text): string
    {
        foreach ($this->beforeCloakCallbacks as $callback) {
            $text = $callback($text);
        }

        return $text;
    }

    /**
     * Execute all afterCloak callbacks in sequence.
     */
    protected function executeAfterCloakCallbacks(string $originalText, string $cloakedText): void
    {
        foreach ($this->afterCloakCallbacks as $callback) {
            $callback($originalText, $cloakedText);
        }
    }

    /**
     * Execute all beforeUncloak callbacks in sequence.
     */
    protected function executeBeforeUncloakCallbacks(string $text): string
    {
        foreach ($this->beforeUncloakCallbacks as $callback) {
            $text = $callback($text);
        }

        return $text;
    }

    /**
     * Execute all afterUncloak callbacks in sequence.
     */
    protected function executeAfterUncloakCallbacks(string $text): string
    {
        foreach ($this->afterUncloakCallbacks as $callback) {
            $text = $callback($text);
        }

        return $text;
    }
}
