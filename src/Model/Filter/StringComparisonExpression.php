<?php
/*******************************************************************************
 *  This file is part of the GraphQL Bundle package.
 *
 *  (c) YnloUltratech <support@ynloultratech.com>
 *
 *  For the full copyright and license information, please view the LICENSE
 *  file that was distributed with this source code.
 ******************************************************************************/

namespace Ynlo\GraphQLBundle\Model\Filter;

use Ynlo\GraphQLBundle\Annotation as GraphQL;

/**
 * @GraphQL\InputObjectType(
 *     description="Create string comparison expression to filter values by contents.

#### Example:

To select values containing given string:
````
value: 'Lorem Itsum'
````

or records starting with given string
````
op: STARTS_WITH
value: 'Lorem'
````
")
 */
class StringComparisonExpression
{
    /**
     * @var string|null
     *
     * @GraphQL\Field(type="StringComparisonOperator", description="Comparison operator, default value: `CONTAINS`")
     */
    private $op;

    /**
     * @var string|null
     *
     * @GraphQL\Field(type="string!", description="String value to search")
     */
    private $value;

    /**
     * @return null|string
     */
    public function getOp(): ?string
    {
        return $this->op;
    }

    /**
     * @param null|string $op
     */
    public function setOp(?string $op): void
    {
        $this->op = $op;
    }

    /**
     * @return null|string
     */
    public function getValue(): ?string
    {
        return $this->value;
    }

    /**
     * @param null|string $value
     */
    public function setValue(?string $value): void
    {
        $this->value = $value;
    }
}