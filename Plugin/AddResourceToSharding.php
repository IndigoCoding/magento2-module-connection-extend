<?php

namespace Indigo\ConnectionExtend\Plugin;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\DeploymentConfig;
use Magento\Framework\Config\ConfigOptionsListConstants;

class AddResourceToSharding {
    /**
     * @var DeploymentConfig
     */
    private $deploymentConfig;

    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    public function __construct
    (
        DeploymentConfig $deploymentConfig,
        ScopeConfigInterface $scopeConfig
    )
    {
        $this->deploymentConfig = $deploymentConfig;
        $this->scopeConfig = $scopeConfig;
    }

    public function afterGetResources(\Magento\Framework\Setup\Declaration\Schema\Sharding $subject, $resources){
        $resourceList = $this->scopeConfig->getValue(\Indigo\ConnectionExtend\Config\Dom::configPath);
        foreach(explode(',', $resourceList) as $resource){
            if($this->canUseResource($resource)){
                $resources[] = $resource;
            }
        }
        return $resources;
    }

    /**
     * Check whether our resource is valid one.
     *
     * @param  string $scopeName
     * @return bool
     */
    public function canUseResource($scopeName)
    {
        $connections = $this->deploymentConfig
            ->get(ConfigOptionsListConstants::CONFIG_PATH_DB_CONNECTIONS);
        return isset($connections[$scopeName]);
    }
}
