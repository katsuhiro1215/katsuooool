<?php

namespace App\Services\Tools\Dns;

class SystemDnsResolver implements DnsResolver
{
    public function resolve(string $host): array
    {
        $ips = [];

        $v4 = gethostbynamel($host);

        if ($v4 !== false) {
            $ips = array_merge($ips, $v4);
        }

        $v6Records = @dns_get_record($host, DNS_AAAA);

        if (is_array($v6Records)) {
            foreach ($v6Records as $record) {
                if (isset($record['ipv6'])) {
                    $ips[] = $record['ipv6'];
                }
            }
        }

        return array_values(array_unique($ips));
    }
}
