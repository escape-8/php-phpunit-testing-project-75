<?php

namespace Tests;

use GuzzleHttp\Client;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use org\bovigo\vfs\vfsStream,
    org\bovigo\vfs\vfsStreamDirectory;

use function Downloader\Downloader\downloadPage;

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
        $this->client = $this->createMock(Client::class);
        $this->response = $this->createMock(ResponseInterface::class);
        $this->streamData = $this->createMock(StreamInterface::class);
        $this->root = vfsStream::setup('home/tests');
        $this->outputPath = vfsStream::url('home/tests');
    }

    public function testDownloaderBase(): void
    {
        $expected = file_get_contents("tests/fixtures/simple-testfile-com.html");
        $this->client->method('get')->willReturn($this->response);
        $this->response->method('getBody')->willReturn($this->streamData);
        $this->streamData->method('getContents')->willReturn($expected);
        downloadPage("https://www.test.com", $this->outputPath, $this->client);
        $actual = file_get_contents($this->outputPath . '/www-test-com.html');
        $this->assertSame($expected, $actual);
    }

    public function testDownloaderException(): void
    {
        $outputPath = $this->outputPath . '/path/to/nonexistent/directory';
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("Directory \"$outputPath\" was not created");
        downloadPage("https://www.test.com", $outputPath, $this->client);
    }
}