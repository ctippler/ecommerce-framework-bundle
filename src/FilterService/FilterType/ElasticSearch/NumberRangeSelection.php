<?php
declare(strict_types=1);

/**
 * Pimcore
 *
 * This source file is available under two different licenses:
 * - GNU General Public License version 3 (GPLv3)
 * - Pimcore Commercial License (PCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (http://www.pimcore.org)
 *  @license    http://www.pimcore.org/license     GPLv3 and PCL
 */

namespace Pimcore\Bundle\EcommerceFrameworkBundle\FilterService\FilterType\ElasticSearch;

use Pimcore\Bundle\EcommerceFrameworkBundle\FilterService\FilterType\AbstractFilterType;
use Pimcore\Bundle\EcommerceFrameworkBundle\IndexService\ProductList\ProductListInterface;
use Pimcore\Bundle\EcommerceFrameworkBundle\Model\AbstractFilterDefinitionType;
use Pimcore\Model\DataObject\Fieldcollection\Data\FilterNumberRangeSelection;

/**
 * @deprecated This class will be moved to the SearchIndex namespace in version 2.0.0.
 */
class NumberRangeSelection extends \Pimcore\Bundle\EcommerceFrameworkBundle\FilterService\FilterType\NumberRangeSelection
{
    public function prepareGroupByValues(AbstractFilterDefinitionType $filterDefinition, ProductListInterface $productList): void
    {
        $productList->prepareGroupByValues($this->getField($filterDefinition), true);
    }

    /**
     * @param FilterNumberRangeSelection $filterDefinition
     *
     */
    public function addCondition(AbstractFilterDefinitionType $filterDefinition, ProductListInterface $productList, array $currentFilter, array $params, bool $isPrecondition = false): array
    {
        $field = $this->getField($filterDefinition);
        $rawValue = $params[$field] ?? null;

        if (!empty($rawValue) && $rawValue != AbstractFilterType::EMPTY_STRING) {
            $values = explode('-', $rawValue);
            $value['from'] = trim($values[0]);
            $value['to'] = trim($values[1]);
        } elseif ($rawValue == AbstractFilterType::EMPTY_STRING) {
            $value = null;
        } else {
            $value['from'] = $filterDefinition->getPreSelectFrom();
            $value['to'] = $filterDefinition->getPreSelectTo();
        }

        $currentFilter[$field] = $value;

        if (!empty($value) && ($value['from'] !== null || $value['to'] !== null)) {
            $range = [];
            if (!empty($value['from'])) {
                $range['gte'] = $value['from'];
            }
            if (!empty($value['to'])) {
                $range['lte'] = $value['to'];
            }
            $productList->addCondition(['range' => ['attributes.' . $field => $range]], $field);
        }

        return $currentFilter;
    }
}
