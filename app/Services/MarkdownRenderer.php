<?php

namespace App\Services;

use League\CommonMark\CommonMarkConverter;

class MarkdownRenderer
{
    public function toHtml(string $markdown): string
    {
        $converter = new CommonMarkConverter([
            'html_input' => 'escape',
        ]);

        return (string) $converter->convert($markdown);
    }
}
