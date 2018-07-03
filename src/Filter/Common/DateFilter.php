<?php
/*******************************************************************************
 *  This file is part of the GraphQL Bundle package.
 *
 *  (c) YnloUltratech <support@ynloultratech.com>
 *
 *  For the full copyright and license information, please view the LICENSE
 *  file that was distributed with this source code.
 ******************************************************************************/

namespace Ynlo\GraphQLBundle\Filter\Common;

use Doctrine\ORM\QueryBuilder;
use Ynlo\GraphQLBundle\Filter\FilterContext;
use Ynlo\GraphQLBundle\Filter\FilterInterface;
use Ynlo\GraphQLBundle\Model\Filter\DateComparisonExpression;
use Ynlo\GraphQLBundle\Type\DateComparisonOperatorType;

class DateFilter implements FilterInterface
{
    /**
     * @inheritDoc
     */
    public function __invoke(FilterContext $context, QueryBuilder $qb, $condition)
    {
        if (!$condition instanceof DateComparisonExpression) {
            throw new \RuntimeException('Invalid filter condition');
        }

        if (!$context->getField() || !$context->getField()->getName()) {
            throw new \RuntimeException('There are not valid field related to this filter.');
        }

        $alias = $qb->getRootAliases()[0];
        $column = $context->getField()->getOriginName();
        $operator = null;

        $date = $condition->getDate()->format('Y-m-d H:i:s');
        $maxDate = $date;
        if ($condition->getMaxDate()) {
            $maxDate = $condition->getMaxDate()->format('Y-m-d H:i:s');
        }

        switch ($condition->getOp()) {
            case DateComparisonOperatorType::AFTER:
                if ($condition->isStrict()) {
                    $qb->andWhere($qb->expr()->gt("{$alias}.{$column}", "'$date'"));
                } else {
                    $qb->andWhere($qb->expr()->gte("{$alias}.{$column}", "'$date'"));
                }
                break;
            case DateComparisonOperatorType::BEFORE:
                if ($condition->isStrict()) {
                    $qb->andWhere($qb->expr()->lt("{$alias}.{$column}", "'$date'"));
                } else {
                    $qb->andWhere($qb->expr()->lte("{$alias}.{$column}", "'$date'"));
                }
                break;
            case DateComparisonOperatorType::BETWEEN:
                if ($condition->isStrict()) {
                    $qb->andWhere($qb->expr()->gt("{$alias}.{$column}", "'$date'"));
                    $qb->andWhere($qb->expr()->lt("{$alias}.{$column}", "'$maxDate'"));
                } else {
                    $qb->andWhere($qb->expr()->between("{$alias}.{$column}", "'$date'", "'$maxDate'"));
                }
                break;
        }
    }
}