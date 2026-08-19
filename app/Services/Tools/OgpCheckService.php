<?php

namespace App\Services\Tools;

use DOMDocument;

class OgpCheckService
{
    private const RECOMMENDED = ['og:title', 'og:description', 'og:image', 'og:url'];

    /**
     * @return array{tags: array<string, string>, missing_recommended: list<string>}
     */
    public function analyze(string $html): array
    {
        $dom = new DOMDocument;
        libxml_use_internal_errors(true);
        // DOMDocument::loadHTML() assumes ISO-8859-1 unless told otherwise,
        // which mangles multi-byte UTF-8 content (e.g. Japanese) that lacks
        // a <meta charset> declaration. Prepending an XML encoding hint
        // forces libxml to parse the string as UTF-8 without adding a
        // stray text node for it.
        $dom->loadHTML('<?xml encoding="utf-8" ?>'.$html, LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();

        $tags = [];

        foreach ($dom->getElementsByTagName('meta') as $meta) {
            $property = $meta->getAttribute('property') ?: $meta->getAttribute('name');

            if ($property === '' || (! str_starts_with($property, 'og:') && ! str_starts_with($property, 'twitter:'))) {
                continue;
            }

            $tags[$property] = $meta->getAttribute('content');
        }

        $missing = array_values(array_filter(
            self::RECOMMENDED,
            fn (string $key): bool => ! array_key_exists($key, $tags),
        ));

        return ['tags' => $tags, 'missing_recommended' => $missing];
    }
}
