<?php

/*
 * This file is part of the API Platform project.
 *
 * (c) Kévin Dunglas <dunglas@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace ApiPlatform\Symfony\Bundle\Test;

use ApiPlatform\Core\Api\IriConverterInterface as LegacyIriConverterInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\BrowserKit\AbstractBrowser;
use Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException;
use Symfony\Component\HttpClient\HttpClientTrait;

/**
 * Base class for functional API tests.
 *
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
abstract class ApiTestCase extends KernelTestCase
{
    use ApiTestAssertionsTrait;

    protected function tearDown(): void
    {
        parent::tearDown();

        self::getClient(null);
    }

    /**
     * Creates a Client.
     *
     * @param array $kernelOptions  Options to pass to the createKernel method
     * @param array $defaultOptions Default options for the requests
     */
    protected static function createClient(array $kernelOptions = [], array $defaultOptions = []): Client
    {
        $kernel = static::bootKernel($kernelOptions);

        try {
            /**
             * @var Client
             */
            $client = $kernel->getContainer()->get('test.api_platform.client');
        } catch (ServiceNotFoundException $e) {
            if (!class_exists(AbstractBrowser::class) || !trait_exists(HttpClientTrait::class)) {
                throw new \LogicException('You cannot create the client used in functional tests if the BrowserKit and HttpClient components are not available. Try running "composer require --dev symfony/browser-kit symfony/http-client".');
            }

            throw new \LogicException('You cannot create the client used in functional tests if the "framework.test" config is not set to true.');
        }

        $client->setDefaultOptions($defaultOptions);

        self::getHttpClient($client);
        self::getClient($client->getKernelBrowser());

        return $client;
    }

    /**
     * Finds the IRI of a resource item matching the resource class and the specified criteria.
     */
    protected function findIriBy(string $resourceClass, array $criteria): ?string
    {
        $container = method_exists(static::class, 'getContainer') ? static::getContainer() : static::$container; // @phpstan-ignore-line

        if (!isset(static::$container) && !method_exists(static::class, 'getContainer')) {
            throw new \RuntimeException(sprintf('The container is not available. You must call "bootKernel()" or "createClient()" before calling "%s".', __METHOD__));
        }

        if (
            (
                !$container->has('doctrine')
                || null === $objectManager = $container->get('doctrine')->getManagerForClass($resourceClass)
            )
            && (
                !$container->has('doctrine_mongodb')
                || null === $objectManager = $container->get('doctrine_mongodb')->getManagerForClass($resourceClass)
            )
        ) {
            throw new \RuntimeException(sprintf('"%s" only supports classes managed by Doctrine ORM or Doctrine MongoDB ODM. Override this method to implement your own retrieval logic if you don\'t use those libraries.', __METHOD__));
        }

        $item = $objectManager->getRepository($resourceClass)->findOneBy($criteria);
        if (null === $item) {
            return null;
        }

        $iriConverter = $container->get('api_platform.iri_converter');

        return $iriConverter instanceof LegacyIriConverterInterface ? $iriConverter->getIriFromItem($item) : $iriConverter->getIriFromResource($item);
    }
}

class_alias(ApiTestCase::class, \ApiPlatform\Core\Bridge\Symfony\Bundle\Test\ApiTestCase::class);
