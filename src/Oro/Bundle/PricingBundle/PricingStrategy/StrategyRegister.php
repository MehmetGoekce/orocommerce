<?php

namespace Oro\Bundle\PricingBundle\PricingStrategy;

use Oro\Bundle\ConfigBundle\Config\ConfigManager;

class StrategyRegister
{
    /**
     * @var ConfigManager
     */
    protected $configManager;

    /**
     * @var PriceCombiningStrategyInterface[]
     */
    protected $strategies = [];

    /**
     * @param ConfigManager $configManager
     */
    public function __construct(ConfigManager $configManager)
    {
        $this->configManager = $configManager;
    }

    /**
     * @return PriceCombiningStrategyInterface
     * @throws \InvalidArgumentException
     */
    public function getCurrentStrategy()
    {
        //TODO: BB-8601 get real strategy name from config manager
        $currentAlias = $this->configManager->get('oro_pricing.strategy');
        if (!$currentAlias) {
            $currentAlias = 'merge_by_priority';
        }

        return $this->get($currentAlias);
    }

    /**
     * @param string $alias
     * @param PriceCombiningStrategyInterface $strategy
     */
    public function add($alias, PriceCombiningStrategyInterface $strategy)
    {
        $this->strategies[$alias] = $strategy;
    }

    /**
     * @param $alias
     * @return PriceCombiningStrategyInterface
     * @throws \InvalidArgumentException
     */
    public function get($alias)
    {
        if (!isset($this->strategies[$alias])) {
            throw new \InvalidArgumentException(sprintf('Pricing strategy named "%s" does not exist.', $alias));
        }

        return $this->strategies[$alias];
    }

    public function getStrategies()
    {
        return $this->strategies;
    }
}
