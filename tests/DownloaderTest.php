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
use function Downloader\Downloader\downloadImages;
use function Downloader\Downloader\downloadAssets;
use function Downloader\Downloader\replaceAttributes;

class DownloaderTest extends TestCase
{
    private Client $client;
    private ResponseInterface $response;
    private StreamInterface $streamData;
    private vfsStreamDirectory $root;
    private string $outputPath;

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
    }

    public function testDownloaderBase(): void
    {
        $expected = 'www-test-com.html';
        downloadPage("https://www.test.com", $this->outputPath, $this->client);
        $actual = basename($this->outputPath . '/www-test-com.html');
        $this->assertSame($expected, $actual);
    }

    public function testDownloaderException(): void
    {
        $outputPath = $this->outputPath . '/path/to/nonexistent/directory';
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("Directory \"$outputPath\" was not created");
        downloadPage("https://www.test.com", $outputPath, $this->client);
    }

    public function testDownloaderDirectoryExists(): void
    {
        $expected = file_get_contents("tests/fixtures/simple-testfile-com.html");
        downloadAssets(new Document($expected), "https://www.test.com", $this->outputPath, $this->client);
        $actual = is_dir($this->outputPath . '/www-test-com_files');
        $this->assertTrue($actual);
    }

    public function testDownloaderImage(): void
    {
        $data = file_get_contents("tests/fixtures/simple-testfile-com.html");
        $this->client->method('request')->willReturn($this->response);
        $imgLinks = downloadImages(new Document($data),
            'https://www.test.com',
            $this->outputPath . '/www-test-com_files',
            $this->client
        );
        $expected = 'www-test-com_files/www-test-com-assets-test-image.png';
        $actual = $imgLinks[0];
        $this->assertEquals($expected, $actual);
    }

    public function testDownloaderChangeHTML(): void
    {
        $file = $this->outputPath . '/www-test-com.html';
        $document = new Document("tests/fixtures/simple-testfile-com.html", true);
        $assets = ['img' => ['www-test-com_files/www-test-com-assets-test-image.png']];
        replaceAttributes($document, $file, 'img', 'src', $assets);
        $documentWithReplacement = new Document($file, true);
        $imgTags = $documentWithReplacement->find('img');
        $expectedSrc = 'www-test-com_files/www-test-com-assets-test-image.png';
        foreach ($imgTags as $imgTag) {
            $src = $imgTag->getAttribute('src');
            $this->assertEquals($expectedSrc, $src);
        }
    }
}