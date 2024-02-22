<?php

namespace Downloader\Downloader;

use DiDom\Document;
use DiDom\Exceptions\InvalidSelectorException;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use http\Exception\RuntimeException;
use Monolog\Level;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;


/**
 * @throws GuzzleException
 */
function downloadPage(string $url, string $outputPath, $clientClass): void
{
    createDirectory($outputPath);
    $logFileName = basename($outputPath) . '.log';
    $log = new Logger($logFileName);
    $log->pushHandler(new StreamHandler("$outputPath/$logFileName", Level::Debug));

    $log->info('Save logs in', ["$outputPath/$logFileName"]);
    $log->info('Download content from', [$url]);

    $client = new $clientClass();
    $content= $client->get($url)->getBody()->getContents();
    $outputFilename = createNameFromUrl($url, '.html');
    $file = "$outputPath/$outputFilename";
    file_put_contents($file, $content);
    $log->info('Page create', [$file]);

    $resourceTags = [
        'img' => 'src',
        'link' => 'href',
        'script' => 'src',
        ];
    $log->info('Download assets from tags', $resourceTags);
    $assets = downloadAssets(new Document($file, true), $resourceTags, $url, $outputPath, $client);
    $log->info('Download Assets successful in', [$outputPath]);

    $log->info('Change URL assets from', [$file]);
    replaceAttributes(new Document($file, true), $file, $resourceTags, $assets);
    $log->info('Change URL Assets successful in', [$file]);

    echo "Page was successfully downloaded into $outputPath/$outputFilename\n";
}

function createNameFromUrl(string $url, string $endName = ''): string
{
    $data = [];
    $parsedUrl = parse_url($url);
    if (array_key_exists('host', $parsedUrl)) {
        $data[] = str_replace('.', '-', $parsedUrl['host']);
    }
    if (array_key_exists('path', $parsedUrl)) {
        if ($parsedUrl['path'] === '/') {
            $data[] = '';
        } else {
            $data[] = str_replace('/', '-', $parsedUrl['path']);
        }
    }
    $name = implode('', $data);
    return $name . $endName;
}

/**
 * @throws GuzzleException
 * @throws InvalidSelectorException
 */
function downloadAssets(Document $document, array $resourceTags, string $url, string $outputPath, $client): array
{
    $assetsDirName = createNameFromUrl($url, '_files');
    createDirectory("$outputPath/$assetsDirName");

    $assetsLinks = [];
    $hostUrl = parse_url($url);
    $hostUrlString = $hostUrl['scheme'] . '://' . $hostUrl['host'];

    foreach ($resourceTags as $tagName => $resourceAttr) {
        $tags = $document->find($tagName);
        if ($tags) {
            foreach ($tags as $tag) {
                $pathResource = $tag->getAttribute($resourceAttr);
                $saveDirectory = "$outputPath/$assetsDirName";
                if ($pathResource && isUrl($pathResource)) {
                    if (isInternalUrl($pathResource, $hostUrlString)) {
                        $savingFileName = createNameFromUrl($pathResource);
                        downloadFile($pathResource, "$saveDirectory/$savingFileName", $client);
                        $assetsLinks[$tagName][] = "$assetsDirName/$savingFileName";
                    } else {
                        $assetsLinks[$tagName][] = $pathResource;
                    }
                } else {
                    $link = $hostUrlString . $pathResource;
                    if ($link === $url) {
                        $savingFileName = createNameFromUrl($link, '.html');
                    } else {
                        $savingFileName = createNameFromUrl($link);
                    }
                    downloadFile($link, "$saveDirectory/$savingFileName", $client);
                    $assetsLinks[$tagName][] = "$assetsDirName/$savingFileName";
                }
            }
        }
    }
    return $assetsLinks;
}

function replaceAttributes(Document $document, string $file, array $resourceTags, array $values): void
{
    foreach ($resourceTags as $tagName => $resourceAttr) {
        $tags = $document->find($tagName);
        if ($tags) {
            $replaceValues = $values[$tagName];
            foreach ($tags as $index => $tag) {
                $tag->setAttribute($resourceAttr, $replaceValues[$index]);
            }
        }
    }
    file_put_contents($file, $document->html());
}

function isUrl(string $resource): bool
{
    return array_key_exists('host', parse_url($resource));
}

function createDirectory(string $path): void
{
    if (!is_dir($path)) {
        if (!mkdir($path) && !is_dir($path)) {
            throw new \RuntimeException(sprintf('Directory "%s" was not created', $path));
        }
    }
}

/**
 * @throws GuzzleException
 */
function downloadFile(string $downloadLink, string $saveTo, $client): void
{
    $client->request('GET', $downloadLink, ['sink' => $saveTo]);
}

function isInternalUrl(string $compareUrl, string $baseUrl): bool
{
    $linkHost = parse_url($compareUrl, PHP_URL_HOST);
    $baseHost = parse_url($baseUrl, PHP_URL_HOST);

    return $linkHost === $baseHost;
}
