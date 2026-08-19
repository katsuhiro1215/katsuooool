<?php

namespace App\Services\Tools\Dns;

interface DnsResolver
{
    /**
     * @return list<string>
     */
    public function resolve(string $host): array;
}
