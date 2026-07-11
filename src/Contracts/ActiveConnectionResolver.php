<?php

namespace LucaPellegrino\DbMyAdmin\Contracts;

interface ActiveConnectionResolver
{
    /**
     * Return a Laravel database connection config array (see config/database.php
     * connection shapes) to activate for the current request, or null to use
     * the host application's default connection.
     */
    public function resolve(): ?array;
}
