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

use Pimcore\Bundle\EcommerceFrameworkBundle\Exception\InvalidConfigException;
use Pimcore\Bundle\EcommerceFrameworkBundle\FilterService\FilterType\AbstractFilterType;
use Pimcore\Bundle\EcommerceFrameworkBundle\IndexService\ProductList\ProductListInterface;
use Pimcore\Bundle\EcommerceFrameworkBundle\Model\AbstractFilterDefinitionType;
use Pimcore\Model\DataObject\Fieldcollection\Data\FilterInputfield;

/**
 * @deprecated This class will be moved to the SearchIndex namespace in version 2.0.0.
 */
class Input extends \Pimcore\Bundle\EcommerceFrameworkBundle\FilterService\FilterType\Input
{
    public function addCondition(AbstractFilterDefinitionType $filterDefinition, ProductListInterface $productList, array $currentFilter, array $params, bool $isPrecondition = false): array
    {
        $field = $this->getField($filterDefinition);

        if (!$filterDefinition instanceof FilterInputfield) {
            throw new InvalidConfigException('invalid config');
        }
        $preSelect = $filterDefinition->getPreSelect();

        $value = $params[$field] ?? null;
        $isReload = $params['is_reload'] ?? null;

        if ($value == AbstractFilterType::EMPTY_STRING) {
            $value = null;
        } elseif (empty($value) && !$isReload) {
            $value = $preSelect;
        }

        if (is_string($value)) {
            $value = trim($value);
        }

        $currentFilter[$field] = $value;

        if (!empty($value)) {
            $value = '.*"' . $value .  '".*';
            $productList->addCondition(['regexp' => ['attributes.' . $field => $value]], $field);
        }

        return $currentFilter;
    }
}
