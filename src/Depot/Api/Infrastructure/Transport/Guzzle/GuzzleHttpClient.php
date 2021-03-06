<?php

namespace Depot\Api\Infrastructure\Transport\Guzzle;

use Depot\Api\Client\HttpClient\HttpClientInterface;
use Depot\Core\Model\Auth\AuthInterface;
use Guzzle\Http\Client;
use Guzzle\Http\ClientInterface;

class GuzzleHttpClient implements HttpClientInterface
{
    protected $client;

    public function __construct(ClientInterface $client = null)
    {
        $this->client = $client ?: new Client;
    }

    public function head($uri)
    {
        return new GuzzleHttpResponse(
            $this->client->head($uri)->send()
        );
    }

    public function get($uri, $headers = null)
    {
        $headers = $this->massageHeaders($headers);

        return new GuzzleHttpResponse(
            $this->client->get($uri, $headers)->send()
        );
    }

    public function post($uri, $headers = null, $payload = null)
    {
        $headers = $this->massageHeaders($headers);

        return new GuzzleHttpResponse(
            $this->client->post($uri, $headers, $payload)->send()
        );
    }

    public function put($uri, $headers = null, $payload = null)
    {
        $headers = $this->massageHeaders($headers);

        return new GuzzleHttpResponse(
            $this->client->put($uri, $headers, $payload)->send()
        );
    }

    public function delete($uri, $headers = null)
    {
        $headers = $this->massageHeaders($headers);
    }

    protected function massageHeaders($headers = null)
    {
        if (null === $headers) {
            $headers = array();
        }

        if (! isset($headers['Content-Type'])) {
            $headers['Content-Type'] = 'application/vnd.tent.v0+json';
        }

        if (! isset($headers['Accept'])) {
            $headers['Accept'] = 'application/vnd.tent.v0+json';
        }

        return $headers;
    }
}
