<?php

declare(strict_types=1);

namespace Tests\Application\ResponseEmitter;

use App\Application\ResponseEmitter\ResponseEmitter;
use Slim\Psr7\Response;
use Tests\TestCase;

class ResponseEmitterTest extends TestCase
{
    public function testDecorateAddsCacheHeaders(): void
    {
        $emitter = new ResponseEmitter();
        $decorated = $emitter->decorate(new Response());

        $this->assertStringContainsString('no-store', $decorated->getHeaderLine('Cache-Control'));
        $this->assertStringContainsString('post-check=0', $decorated->getHeaderLine('Cache-Control'));
        $this->assertSame('no-cache', $decorated->getHeaderLine('Pragma'));
    }

    public function testEmitWritesBodyAndClearsStaleBuffer(): void
    {
        $response = new Response();
        $response->getBody()->write('{"status":"ok"}');

        $emitter = new ResponseEmitter();

        ob_start();
        echo 'stale';
        $emitter->emit($response);
        $output = (string) ob_get_clean();

        $this->assertSame('{"status":"ok"}', $output);
    }

    public function testEmitWithoutPriorOutputBufferContents(): void
    {
        $response = new Response();
        $response->getBody()->write('x');
        $emitter = new ResponseEmitter();

        ob_start();
        $emitter->emit($response);
        $output = (string) ob_get_clean();

        $this->assertSame('x', $output);
    }
}
