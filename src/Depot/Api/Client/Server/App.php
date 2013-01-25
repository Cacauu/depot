<?php

namespace Depot\Api\Client\Server;

use Depot\Api\Client\HttpClient\AuthenticatedHttpClient;
use Depot\Api\Client\HttpClient\HttpClientInterface;
use Depot\Core\Domain\Model;

class App
{
    protected $httpClient;

    public function __construct(HttpClientInterface $httpClient)
    {
        $this->httpClient = $httpClient;
    }

    public function register(Model\Server\ServerInterface $server, Model\App\App $app)
    {
        return ServerHelper::tryAllServers($server, array($this, 'registerInternal'), array($app));
    }

    public function generateClientAuthorizationRequest(Model\Server\ServerInterface $server, Model\App\ClientAppInterface $clientApp, $redirectUri, $scopes, $profileTypes, $postTypes)
    {
        list ($apiRoot) = $server->servers();

        $state  = str_replace(array('/', '+', '='), '', base64_encode(openssl_random_pseudo_bytes(64)));

        $params = array(
            'client_id' => $clientApp->id(),
            'redirect_uri' => $redirectUri,
            'scope' => implode(',', $scopes),
            'state' => $state,
            'tent_profile_info_types' => implode(',', $profileTypes),
            'tent_post_types' => implode(',', $postTypes),
        );

        return new Model\App\ClientAuthorizationRequest(
            $clientApp,
            $state,
            $apiRoot.'/oauth/authorize?'.http_build_query($params)
        );
    }

    public function exchangeCode(Model\Server\ServerInterface $server, Model\App\ClientAppInterface $clientApp, $code)
    {
        list ($apiRoot) = $server->servers();

        $payload = array(
            'code' => $code,
            'token_type' => 'mac',
        );

        $response = $this->httpClient->post(
            $apiRoot.'/apps/'.$clientApp->id().'/authorizations',
            null,
            json_encode($payload)
        );

        $json = json_decode($response->body(), true);

        return new Model\App\ClientAuthorizationResponse(
            $clientApp,
            Model\Auth\AuthFactory::create(
                $json['access_token'],
                $json['mac_key'],
                $json['mac_algorithm']
            ),
            $json['token_type'],
            $json['refresh_token'],
            isset($json['tent_expires_at']) ? $json['tent_expires_at'] : null
        );
    }

    public function registerInternal(Model\Server\ServerInterface $server, $apiRoot, Model\App\App $app)
    {
        $payload = array(
            'name' => $app->name(),
            'description' => $app->description(),
            'url' => $app->url(),
            'icon' => $app->icon(),
            'redirect_uris' => $app->redirectUris(),
            'scopes' => $app->scopes(),
        );

        $response = $this->httpClient->post($apiRoot.'/apps', null, json_encode($payload));

        $json = json_decode($response->body(), true);

        $app = new Model\App\App(
            $json['name'],
            $json['description'],
            $json['url'],
            $json['icon'],
            $json['redirect_uris'],
            $json['scopes']
        );

        $auth = Model\Auth\AuthFactory::create(
            $json['mac_key_id'],
            $json['mac_key'],
            $json['mac_algorithm']
        );

        return new Model\App\AppRegistrationCreationResponse(
            $json['id'],
            $app,
            $auth,
            $json['authorizations'],
            $json['created_at']
        );
    }
}