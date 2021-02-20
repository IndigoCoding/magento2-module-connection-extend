<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Indigo\ConnectionExtend\Config\Menu;

/**
 * Menu configuration files handler
 * @api
 * @since 100.0.2
 */
class Dom extends \Indigo\ConnectionExtend\Config\Dom
{
    /**
     * Getter for node by path
     *
     * @param string $nodePath
     * @return \DOMElement|null
     * @throws \Magento\Framework\Exception\LocalizedException an exception is possible if original document contains
     * multiple fixed nodes
     */
    protected function _getMatchedNode($nodePath)
    {
        if (!preg_match('/^\/config(\/menu)?$/i', $nodePath)) {
            return null;
        }
        return parent::_getMatchedNode($nodePath);
    }
}
