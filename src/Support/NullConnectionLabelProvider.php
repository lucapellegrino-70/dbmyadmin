<?php

namespace LucaPellegrino\DbMyAdmin\Support;

use LucaPellegrino\DbMyAdmin\Contracts\ActiveConnectionLabelProvider;

class NullConnectionLabelProvider implements ActiveConnectionLabelProvider
{
    public function label(): ?string
    {
        return null;
    }
}
