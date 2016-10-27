<?php

namespace Oro\Bundle\ProductBundle\Tests\Functional\Autocomplete;

use Symfony\Component\EventDispatcher\Event;

use Oro\Bundle\ProductBundle\Event\ProductDBQueryRestrictionEvent;
use Oro\Bundle\ProductBundle\Event\ProductSearchQueryRestrictionEvent;
use Oro\Bundle\ProductBundle\Entity\Product;
use Oro\Bundle\TestFrameworkBundle\Test\WebTestCase;
use Oro\Bundle\CustomerBundle\Tests\Functional\DataFixtures\LoadProductVisibilityData;

/**
 * @dbIsolation
 */
class ProductVisibilityLimitedSearchHandlerTest extends WebTestCase
{
    /**
     * @var Event
     */
    protected $firedEvent;

    protected function setUp()
    {
        $this->initClient([], [], true);
        $this->loadFixtures(
            [
                LoadProductVisibilityData::class
            ]
        );
        $this->getContainer()->get('oro_website_search.indexer')->reindex(Product::class);
        $this->getContainer()->get('oro_customer.visibility.cache.product.cache_builder')->buildCache();

        $this->client->getContainer()->set('test_service', $this);
    }

    public function testFrontendVisibility()
    {
        $url = $this->getUrl(
            'oro_frontend_autocomplete_search',
            [
                'per_page' => 10,
                'query'    => 'ZZ',
                'name'     => 'oro_product_visibility_limited'
            ]
        );

        $dispatcher = $this->client->getContainer()->get('event_dispatcher');

        /*** @var ProductSearchQueryRestrictionEvent $firedEvent */
        $this->firedEvent = null;

        $dispatcher->addListenerService(
            ProductSearchQueryRestrictionEvent::NAME,
            ['test_service', 'eventCatcher']
        );

        $this->client->request('GET', $url);

        $result = $this->client->getResponse();
        $this->assertJsonResponseStatusCodeEquals($result, 200);
        $data = json_decode($result->getContent(), true);
        $this->assertNotEmpty($data);
        $this->assertNotNull($this->firedEvent, 'Restriction event has not been fired');
        $this->assertInstanceOf(
            ProductSearchQueryRestrictionEvent::class,
            $this->firedEvent
        );

        $dispatcher->removeListener(
            ProductSearchQueryRestrictionEvent::NAME,
            [$this, 'eventCatcher']
        );
    }

    public function testBackendVisibility()
    {
        $url = $this->getUrl(
            'oro_form_autocomplete_search',
            [
                'per_page' => 10,
                'query'    => 'ZZ',
                'name'     => 'oro_product_visibility_limited'
            ]
        );

        $this->client->restart();
        $dispatcher = $this->client->getContainer()->get('event_dispatcher');

        /*** @var ProductSearchQueryRestrictionEvent $firedEvent */
        $this->firedEvent = null;

        $dispatcher->addListenerService(
            ProductDBQueryRestrictionEvent::NAME,
            ['test_service', 'eventCatcher']
        );

        $this->client->request('GET', $url);

        $result = $this->client->getResponse();
        $this->assertJsonResponseStatusCodeEquals($result, 200);
        $data = json_decode($result->getContent(), true);
        $this->assertNotEmpty($data);
        $this->assertNotNull($this->firedEvent, 'Restriction event has not been fired');
        $this->assertInstanceOf(
            ProductDBQueryRestrictionEvent::class,
            $this->firedEvent
        );

        $dispatcher->removeListener(
            ProductDBQueryRestrictionEvent::NAME,
            [$this, 'eventCatcher']
        );
    }

    /**
     * @param Event $event
     */
    public function eventCatcher(Event $event)
    {
        $this->firedEvent = $event;
    }
}
