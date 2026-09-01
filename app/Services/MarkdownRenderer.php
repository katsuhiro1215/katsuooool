<?php

namespace App\Services;

use League\CommonMark\CommonMarkConverter;

class MarkdownRenderer
{
    public function toHtml(string $markdown): string
    {
        $converter = new CommonMarkConverter([
            'html_input' => 'escape',
            'allow_unsafe_links' => false,
            'renderer' => [
                // Markdown仕様では改行1回(ソフトブレイク)は無視されるが、
                // 記事執筆者はMarkdown記法を意識せず改行するため<br>として扱う
                'soft_break' => "<br />\n",
            ],
        ]);

        return (string) $converter->convert($markdown);
    }
}
