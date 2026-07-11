<?php

namespace LucaPellegrino\DbMyAdmin\Support;

use LucaPellegrino\DbMyAdmin\Contracts\ActiveConnectionResolver;

class NullConnectionResolver implements ActiveConnectionResolver
{
    public function resolve(): ?array
    {
        return null;
    }
}
