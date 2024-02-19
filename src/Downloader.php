<?php

namespace Downloader\Downloader;

function downloadPage(string $url, string $outputPath, $clientClass): string
{
    $content = $clientClass->get($url)->getBody()->getContents();
    $parsedUrl = parse_url($url);
    $outputFilename = implode('-', explode('.', $parsedUrl['host'])) . '.html';
    if (!is_dir($outputPath)) {
        $dir = mkdir($outputPath);
        if (!$dir && !is_dir($outputPath)) {
            throw new \RuntimeException(sprintf('Directory "%s" was not created', $outputPath));
        }
    }
    file_put_contents("$outputPath/$outputFilename", $content);

    return "Page was successfully downloaded into $outputPath/$outputFilename\n";
}
