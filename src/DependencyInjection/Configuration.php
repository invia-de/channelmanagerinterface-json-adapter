<?php declare(strict_types=1);

namespace Invia\CMI\JsonAdapterBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * {@inheritdoc}
 */
class Configuration implements ConfigurationInterface
{
    /**
     * {@inheritdoc}
     */
    public function getConfigTreeBuilder() : TreeBuilder
    {
        return new TreeBuilder();
    }
}
