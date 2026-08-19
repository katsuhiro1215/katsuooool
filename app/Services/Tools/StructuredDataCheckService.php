<?php

namespace App\Services\Tools;

use DOMDocument;

class StructuredDataCheckService
{
    /**
     * @return list<array{raw: string, valid: bool, data?: mixed, error?: string, warnings: list<string>}>
     */
    public function analyze(string $html): array
    {
        $dom = new DOMDocument;
        libxml_use_internal_errors(true);
        // See OgpCheckService::analyze() for why the XML encoding hint is needed:
        // without it, DOMDocument mangles multi-byte UTF-8 content.
        $dom->loadHTML('<?xml encoding="utf-8" ?>'.$html, LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();

        $results = [];

        foreach ($dom->getElementsByTagName('script') as $script) {
            if (strtolower($script->getAttribute('type')) !== 'application/ld+json') {
                continue;
            }

            $raw = trim($script->textContent);
            $decoded = json_decode($raw, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                $results[] = ['raw' => $raw, 'valid' => false, 'error' => json_last_error_msg(), 'warnings' => []];

                continue;
            }

            $entries = array_is_list($decoded) ? $decoded : [$decoded];
            $warnings = [];

            foreach ($entries as $entry) {
                if (! is_array($entry) || ! array_key_exists('@context', $entry)) {
                    $warnings[] = '@context が見つかりません。';
                }

                if (! is_array($entry) || ! array_key_exists('@type', $entry)) {
                    $warnings[] = '@type が見つかりません。';
                }
            }

            $results[] = [
                'raw' => $raw,
                'valid' => true,
                'data' => $decoded,
                'warnings' => array_values(array_unique($warnings)),
            ];
        }

        return $results;
    }
}
