<?php

namespace App\Services;

class PatternDocumentRenderer
{
    public function build(string $html, string $css): string
    {
        return <<<HTML
<!doctype html>
<html lang="ja">
<head>
<meta charset="UTF-8" />
<style>
  html, body {
    margin: 0;
    padding: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    min-height: 100%;
    box-sizing: border-box;
  }
  {$css}
</style>
</head>
<body>
{$html}
</body>
</html>
HTML;
    }
}
