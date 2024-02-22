<?php

namespace Tests;

use DiDom\Document;
use GuzzleHttp\Client;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use org\bovigo\vfs\vfsStream,
    org\bovigo\vfs\vfsStreamDirectory;

use function Downloader\Downloader\downloadPage;
use function Downloader\Downloader\downloadAssets;
use function Downloader\Downloader\replaceAttributes;

class DownloaderTest extends TestCase
{
    private Client $client;
    private ResponseInterface $response;
    private StreamInterface $streamData;
    private vfsStreamDirectory $root;
    private string $outputPath;
    private array $expectedData;
    private array $resourceTags;

    /**
     * @throws Exception
     */
    public function setUp(): void
    {
        $expected = file_get_contents("tests/fixtures/simple-testfile-com.html");
        $this->client = $this->createMock(Client::class);
        $this->response = $this->createMock(ResponseInterface::class);
        $this->streamData = $this->createMock(StreamInterface::class);
        $this->client->method('get')->willReturn($this->response);
        $this->response->method('getBody')->willReturn($this->streamData);
        $this->streamData->method('getContents')->willReturn($expected);
        $this->root = vfsStream::setup('home/tests');
        $this->outputPath = vfsStream::url('home/tests');

        $this->expectedData = [
            'img' => ['www-test-com-courses_files/www-test-com-assets-test-image.png'],
            'link' => [
                'https://cdn2.test.com/assets/menu.css',
                'www-test-com-courses_files/www-test-com-assets-application.css',
                'www-test-com-courses_files/www-test-com-courses.html',
            ],
            'script' => [
                'https://js.stripe.com/v3/',
                'www-test-com-courses_files/www-test-com-packs-js-runtime.js',
            ],
        ];
        $this->resourceTags = [
            'img' => 'src',
            'link' => 'href',
            'script' => 'src',
        ];
    }

    public function testDownloaderCreatePageFile(): void
    {
        downloadPage("https://www.test.com", $this->outputPath, $this->client);
        $actual = file_exists($this->outputPath . '/www-test-com.html');
        $this->assertTrue($actual);
    }

    public function testDownloaderCreatePageFileWithPath(): void
    {
        downloadPage("https://www.test.com/courses", $this->outputPath, $this->client);
        $actual = file_exists($this->outputPath . '/www-test-com-courses.html');
        $this->assertTrue($actual);
    }

    public function testDownloaderException(): void
    {
        $outputPath = $this->outputPath . '/path/to/nonexistent/directory';
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("Directory \"$outputPath\" was not created");
        downloadPage("https://www.test.com/courses", $outputPath, $this->client);
    }

    public function testDownloaderCreateAssetsDirectory(): void
    {
        $file = file_get_contents("tests/fixtures/simple-testfile-com.html");
        downloadAssets(new Document($file), $this->resourceTags, "https://www.test.com/courses", $this->outputPath, $this->client);
        $actual = is_dir($this->outputPath . '/www-test-com-courses_files');
        $this->assertTrue($actual);
    }

    public function testDownloaderAssetsTagImg(): void
    {
        $expected = $this->expectedData['img'];
        $document = new Document("tests/fixtures/simple-testfile-com.html", true);
        $actual = downloadAssets($document, $this->resourceTags, "https://www.test.com/courses", $this->outputPath, $this->client);
        foreach ($actual['img'] as $index => $link) {
            $this->assertEquals($expected[$index], $link);
        }
    }

    public function testDownloaderAssetsTagLink(): void
    {
        $expected = $this->expectedData['link'];
        $document = new Document("tests/fixtures/simple-testfile-com.html", true);
        $actual = downloadAssets($document, $this->resourceTags, "https://www.test.com/courses", $this->outputPath, $this->client);
        foreach ($actual['link'] as $index => $link) {
            $this->assertEquals($expected[$index], $link);
        }
    }

    public function testDownloaderAssetsTagScript(): void
    {
        $expected = $this->expectedData['script'];
        $document = new Document("tests/fixtures/simple-testfile-com.html", true);
        $actual = downloadAssets($document, $this->resourceTags, "https://www.test.com/courses", $this->outputPath, $this->client);
        foreach ($actual['script'] as $index => $link) {
            $this->assertEquals($expected[$index], $link);
        }
    }

    public function testDownloaderChangeImgTagSrcPath(): void
    {
        $file = $this->outputPath . '/www-test-com-courses.html';
        $document = new Document("tests/fixtures/simple-testfile-com.html", true);
        replaceAttributes($document, $file, $this->resourceTags, $this->expectedData);
        $documentWithReplacement = new Document($file, true);
        $imgTags = $documentWithReplacement->find('img');
        foreach ($imgTags as $index => $imgTag) {
            $src = $imgTag->getAttribute('src');
            $this->assertEquals($this->expectedData['img'][$index], $src);
        }
    }

    public function testDownloaderChangeLinkTagHrefPath(): void
    {
        $file = $this->outputPath . '/www-test-com-courses.html';
        $document = new Document("tests/fixtures/simple-testfile-com.html", true);
        replaceAttributes($document, $file, $this->resourceTags, $this->expectedData);
        $documentWithReplacement = new Document($file, true);
        $linkTags = $documentWithReplacement->find('link');
        foreach ($linkTags as $index => $linkTag) {
            $href = $linkTag->getAttribute('href');
            $this->assertEquals($this->expectedData['link'][$index], $href);
        }
    }

    public function testDownloaderChangeScriptTagSrcPath(): void
    {
        $file = $this->outputPath . '/www-test-com-courses.html';
        $document = new Document("tests/fixtures/simple-testfile-com.html", true);
        replaceAttributes($document, $file, $this->resourceTags, $this->expectedData);
        $documentWithReplacement = new Document($file, true);
        $linkTags = $documentWithReplacement->find('script');
        foreach ($linkTags as $index => $linkTag) {
            $href = $linkTag->getAttribute('src');
            $this->assertEquals($this->expectedData['script'][$index], $href);
        }
    }
}
