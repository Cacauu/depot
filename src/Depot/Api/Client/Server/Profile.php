<?php

namespace Depot\Api\Client\Server;

use Depot\Api\Client\HttpClient\HttpClientInterface;
use Depot\Core\Domain\Model;
use Symfony\Component\DomCrawler\Crawler;

class Profile
{
    protected $httpClient;

    public function __construct(HttpClientInterface $httpClient)
    {
        $this->httpClient = $httpClient;
    }

    public function getProfile(Model\Server\ServerInterface $server)
    {
        return $this->tryAllServers($server, array($this, 'getProfileInternal'));
    }

    public function getProfileType(Model\Server\ServerInterface $server, $type)
    {
        return $this->tryAllServers($server, array($this, 'getProfileTypeInternal'), array($type));
    }

    public function putProfileType(Model\Server\ServerInterface $server, Model\Entity\ProfileTypeInterface $profileType)
    {
        return $this->tryAllServers($server, array($this, 'putProfileTypeInternal'), array($profileType));
    }

    protected function tryAllServers(Model\Server\ServerInterface $server, $callback, $additionalArgs = null)
    {
        foreach ($server->servers() as $target) {
            $args = array($server, $target);

            if (null !== $additionalArgs) {
                $args = array_merge($args, $additionalArgs);
            }

            return call_user_func_array($callback, $args);
        }

        throw new \RuntimeException("Unable to send to any server");
    }

    public function getProfileInternal(Model\Server\ServerInterface $server, $apiRoot)
    {
        $response = $this->httpClient->get($apiRoot.'/profile');

        $profile = static::createProfileFromJson(json_decode($response->body(), true));

        $newTypes = array();
        foreach ($profile->types() as $type) {
            $newTypes[$type] = true;
            $server->entity()->profile()->set($profile->find($type));
        }

        foreach ($server->entity()->profile()->types() as $type) {
            if (! isset($newTypes[$type])) {
                $server->entity()->profile()->remove($type);
            }
        }

        return $profile;
    }

    public function getProfileTypeInternal(Model\Server\ServerInterface $server, $apiRoot, $type)
    {
        $response = $this->httpClient->get($apiRoot.'/profile/'.rawurlencode($type));

        $profileType = new Model\Entity\ProfileType($type, json_decode($response->body(), true));

        $server->entity()->profile()->set($profileType);

        return $profileType;
    }

    public function putProfileTypeInternal(Model\Server\ServerInterface $server, $apiRoot, Model\Entity\ProfileTypeInterface $profileType)
    {
        $response = $this->httpClient->put(
            $apiRoot.'/profile/'.rawurlencode($profileType->uri()),
            null,
            json_encode($profileType->content())
        );

        $profileType = new Model\Entity\ProfileType($type, json_decode($response->body(), true));

        $server->entity()->profile()->set($profileType);

        return $profileType;
    }

    public static function createProfileFromJson($json)
    {
        if ( ! isset($json['https://tent.io/types/info/core/v0.1.0']['entity'])) {
            return null;
        }

        if ( ! isset($json['https://tent.io/types/info/core/v0.1.0']['servers'])) {
            return null;
        }

        $profile = new Model\Entity\Profile;
        foreach ($json as $type => $content) {
            $profile->set(new Model\Entity\ProfileType($type, $content));
        }

        return $profile;
    }
}
