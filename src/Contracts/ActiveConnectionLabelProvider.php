<?php

namespace LucaPellegrino\DbMyAdmin\Contracts;

interface ActiveConnectionLabelProvider
{
    /**
     * A short human-readable label for the currently active connection
     * (e.g. "Produzione (MySQL @ db.example.com)"), or null when the app's
     * default connection is in use (the only state possible without an
     * extension installed).
     */
    public function label(): ?string;
}
