<?php

namespace LucaPellegrino\DbMyAdmin;

use Closure;
use Filament\Contracts\Plugin;
use Filament\Panel;
use LucaPellegrino\DbMyAdmin\Resources\DatabaseTableResource;

class DbMyAdminPlugin implements Plugin
{
    protected ?string  $navigationGroup       = null;
    protected ?string  $navigationIcon        = null;
    protected ?Closure $authorizationCallback = null;

    public static function make(): static
    {
        return new static();
    }

    public function getId(): string
    {
        return 'dbmyadmin';
    }

    public function register(Panel $panel): void
    {
        $panel->resources([
            DatabaseTableResource::class,
        ]);
    }

    public function boot(Panel $panel): void
    {
        //
    }

    public function navigationGroup(string $group): static
    {
        $this->navigationGroup = $group;
        return $this;
    }

    public function navigationIcon(string $icon): static
    {
        $this->navigationIcon = $icon;
        return $this;
    }

    public function authorize(Closure $callback): static
    {
        $this->authorizationCallback = $callback;
        return $this;
    }

    public function getNavigationGroup(): ?string
    {
        return $this->navigationGroup;
    }

    public function getNavigationIcon(): ?string
    {
        return $this->navigationIcon;
    }

    public function isAuthorized(): bool
    {
        if ($this->authorizationCallback === null) {
            return true;
        }

        return (bool) call_user_func($this->authorizationCallback);
    }
}
