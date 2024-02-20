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
    $assets = downloadAssets(new Document($file, true), $url, $outputPath, $clientClass);
    replaceAttributes(new Document($file, true), $file, 'img', 'src', $assets);

    return "Page was successfully downloaded into $outputPath/$outputFilename\n";
}

function createNameFromUrl(string $url, string $endName, string $separatorFrom, string $separatorTo): string
{
    $data = parse_url($url)['host'] ?? $url;
    $name = implode($separatorTo, explode($separatorFrom, $data));
    return $name . $endName;
}
function downloadAssets(Document $document, string $url, string $outputPath, $client): array
{
    $assetsDirName = createNameFromUrl($url, '_files', '.', '-');
    if (!is_dir("$outputPath/$assetsDirName")) {
        if (!mkdir("$outputPath/$assetsDirName") && !is_dir("$outputPath/$assetsDirName")) {
            throw new \RuntimeException(sprintf('Directory "%s" was not created', "$outputPath/$assetsDirName"));
        }
    }
    $assetsLinks = [];
    $assetsLinks['img'] = downloadImages($document, $url, "$outputPath/$assetsDirName", $client);
    return $assetsLinks;
}

function downloadImages(Document $document, string $url, string $outputPath, $client): array
{

    $imagePaths = [];
    $assetDir = basename($outputPath);
    $imageTags = $document->find('img');
    $imageBaseName = createNameFromUrl($url, '', '.', '-');
    foreach ($imageTags as $imageTag) {
        $imageLink = $imageTag->getAttribute('src');
        $saveImageName = $imageBaseName .  createNameFromUrl($imageLink, '', '/', '-');
        $client->request('GET', $url . $imageLink, ['sink' => "$outputPath/$saveImageName"]);
        $imagePaths[] = "$assetDir/$saveImageName";
    }
    return $imagePaths;
}

function replaceAttributes(Document $document, string $file, string $tagName, string $attrName, array $values): void
{
    $tags = $document->find($tagName);
    $changeValues = $values[$tagName];
    foreach ($tags as $index => $tag) {
        $tag->setAttribute($attrName, $changeValues[$index]);
    }
    file_put_contents($file, $document->html());
}

