<?php

namespace Downloader\Downloader;

use DiDom\Document;

function downloadPage(string $url, string $outputPath, $clientClass): string
{
    $content = $clientClass->get($url)->getBody()->getContents();
    $outputFilename = createNameFromUrl($url, '.html', '.', '-');
    if (!is_dir($outputPath)) {
        if (!mkdir($outputPath) && !is_dir($outputPath)) {
            throw new \RuntimeException(sprintf('Directory "%s" was not created', $outputPath));
        }
    }
    $file = "$outputPath/$outputFilename";
    file_put_contents($file, $content);
    $resourceTags = ['img' => 'src'];
    $assets = downloadAssets(new Document($file, true), $resourceTags, $url, $outputPath, $clientClass);
    replaceAttributes(new Document($file, true), $file, $resourceTags, $assets);

    return "Page was successfully downloaded into $outputPath/$outputFilename\n";
}

function createNameFromUrl(string $url, string $endName, string $separatorFrom, string $separatorTo): string
{
    $data = parse_url($url)['host'] ?? $url;
    $name = implode($separatorTo, explode($separatorFrom, $data));
    return $name . $endName;
}
function downloadAssets(Document $document, array $resourceTags, string $url, string $outputPath, $client): array
{
    $assetsDirName = createNameFromUrl($url, '_files', '.', '-');
    if (!is_dir("$outputPath/$assetsDirName")) {
        if (!mkdir("$outputPath/$assetsDirName") && !is_dir("$outputPath/$assetsDirName")) {
            throw new \RuntimeException(sprintf('Directory "%s" was not created', "$outputPath/$assetsDirName"));
        }
    }

    $assetsLinks = [];
    $baseName = createNameFromUrl($url, '', '.', '-');
    foreach ($resourceTags as $tagName => $resourceAttr) {
        $tags = $document->find($tagName);
        foreach ($tags as $tag) {
            $path = $tag->getAttribute($resourceAttr);
            $savingFileName = $baseName . createNameFromUrl($path, '', '/', '-');
            $client->request('GET', $url . $path, ['sink' => "$outputPath/$assetsDirName/$savingFileName"]);
            $assetsLinks[$tagName][] = "$assetsDirName/$savingFileName";
        }
    }
    return $assetsLinks;
}

function replaceAttributes(Document $document, string $file, array $resourceTags, array $values): void
{
    foreach ($resourceTags as $tagName => $resourceAttr) {
        $tags = $document->find($tagName);
        $replaceValues = $values[$tagName];
        foreach ($tags as $index => $tag) {
            $tag->setAttribute($resourceAttr, $replaceValues[$index]);
        }
    }
    file_put_contents($file, $document->html());
}

