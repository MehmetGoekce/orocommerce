<?php

namespace Oro\Bundle\FrontendBundle\Tests\Functional;

use Oro\Bundle\TestFrameworkBundle\Test\WebTestCase;

class RouterTest extends WebTestCase
{
    /**
     * {@inheritdoc}
     */
    protected function setUp()
    {
        $this->initClient();
    }

    public function testRouteNames()
    {
        $generator = $this->getContainer()->get('router')->getGenerator();
        $this->assertInstanceOf('\appTestUrlGenerator', $generator);

        $declaredRoutesProperty = new \ReflectionProperty(get_class($generator), 'declaredRoutes');
        $declaredRoutesProperty->setAccessible(true);
        $declaredRoutes = $declaredRoutesProperty->getValue();

        $invalidRoutes = [];
        foreach (array_keys($declaredRoutes) as $name) {
            if (strpos($name, 'orob2b') === 0) {
                $invalidRoutes[] = $name;
            }
        }
        $this->assertEmpty(
            $invalidRoutes,
            "Invalid route names:\n" . implode("\n", $invalidRoutes)
        );
    }
}
