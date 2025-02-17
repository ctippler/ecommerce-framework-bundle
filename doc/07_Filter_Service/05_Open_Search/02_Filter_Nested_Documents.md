# Filter for nested documents

In some cases it is necessary to store an array of objects, but in a way so that they can be queried independently of each 
other, i.e. if you want to store an array of additional data from field collections. The data in your index may look as follows:

```json
{
   "_source": {
       "attributes": {  
         "my_attributes": [  
            {  
               "id": "12345",
               "name": "Höhe",
               "value": "15 mm"
            },
            {  
               "id": "12346",
               "name": "Länge",
               "value": "7 mm"
            },
            {  
               "id": "12347",
               "name": "Breite",
               "value": "30 mm"
            }
         ]
      }
   }
}
```

To utilize the `nested` document functionality the mapping type of the field `my_attributes` must be defined as `nested`, 
to let OpenSearch know about the sub-documents:

```yaml
 attributes:
    filtergroup: string
    type: 'nested'
    options:
        mapping:
            type: 'nested'
    interpreter_id: App\Ecommerce\IndexService\Interpreter\MyAttributes
    interpreter_options:
        locale: '%%locale%%'
```

Now you can create a filter for the nested document field, which has to be defined in a nested manner as well:

```php
class SelectMyAttribute extends \Pimcore\Bundle\EcommerceFrameworkBundle\FilterService\FilterType\AbstractFilterType
{
    public function prepareGroupByValues(AbstractFilterDefinitionType $filterDefinition, ProductListInterface $productList): void
    {
        /** @var AbstractOpenSearch $productList */

        $nestedPath = "attributes.my_attributes";

        // first group by id
        $subAggregationField = $nestedPath . ".id";
        
        // then group by value
        $subSubAggregationField = $nestedPath . ".value.keyword";

        $productList->prepareGroupByValuesWithConfig($this->getField($filterDefinition), true, false, [
            "nested" => [
                "path" => $nestedPath
            ],
            "aggs" => [
                $subAggregationField => [
                    "terms" => [
                        "size" => 200,
                        "field" => $subAggregationField
                    ],
                    "aggs" => [
                        $subSubAggregationField => [
                            "terms" => [
                                "size" => 200,
                                "field" => $subSubAggregationField
                            ]
                        ]
                    ]
                ]
            ]
        ]);
    }

    public function addCondition(AbstractFilterDefinitionType $filterDefinition, ProductListInterface $productList, array $currentFilter, array $params, bool $isPrecondition = false): void
    {
        $nestedPath = "attributes.my_attributes";
        
        // @todo: get $myAttributeValue and $myAttributeId from request params

        $condition = [
            "nested" => [
                "path" => $nestedPath,
                "query" => [
                    "bool" => [
                        $mode => [
                            [
                                "term" => [
                                    $nestedPath . ".value.keyword" => $myAttributeValue
                                ]
                            ],
                            [
                                "term" => [
                                    $nestedPath."id" => $myAttributeId
                                ]
                            ]
                        ]
                    ]
                ]
            ]
        ];

        $productList->addCondition($condition, $this->getField($filterDefinition));
    }

    public function getFilterValues(AbstractFilterDefinitionType $filterDefinition, ProductListInterface $productList, array $currentFilter): array
    {
        $field = $this->getField($filterDefinition);
        $this->prepareGroupByValues($filterDefinition, $productList);

        $values = $productList->getGroupByValues($field, true, !$filterDefinition->getUseAndCondition());

        return [
            'label' => $filterDefinition->getLabel(),
            'values' => $values,
            'metaData' => $filterDefinition->getMetaData(),
            'hasValue' => $this->hasValue,
        ];
    }
}
```
