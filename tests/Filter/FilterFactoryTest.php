<?php
/*******************************************************************************
 *  This file is part of the GraphQL Bundle package.
 *
 *  (c) YnloUltratech <support@ynloultratech.com>
 *
 *  For the full copyright and license information, please view the LICENSE
 *  file that was distributed with this source code.
 ******************************************************************************/

namespace Ynlo\GraphQLBundle\Tests\Filter;

use Mockery\Adapter\Phpunit\MockeryTestCase;
use Ynlo\GraphQLBundle\Annotation\Filter;
use Ynlo\GraphQLBundle\Annotation\ObjectType;
use Ynlo\GraphQLBundle\Definition\ObjectDefinition;
use Ynlo\GraphQLBundle\Definition\QueryDefinition;
use Ynlo\GraphQLBundle\Definition\Registry\Endpoint;
use Ynlo\GraphQLBundle\Filter\Common\DateFilter;
use Ynlo\GraphQLBundle\Filter\Common\StringFilter;
use Ynlo\GraphQLBundle\Filter\FilterFactory;
use Ynlo\GraphQLBundle\Filter\FilterResolverInterface;
use Ynlo\GraphQLBundle\Model\Filter\DateComparisonExpression;
use Ynlo\GraphQLBundle\Model\Filter\StringComparisonExpression;
use Ynlo\GraphQLBundle\Tests\Fixtures\AppBundle\Entity\Post;
use Ynlo\GraphQLBundle\Tests\TestDefinitionHelper;

class FilterFactoryTest extends MockeryTestCase
{
    public function testBuild()
    {
        $endpoint = new Endpoint('default');
        TestDefinitionHelper::loadAnnotationDefinitions(Post::class, $endpoint, [ObjectType::class]);

        /** @var ObjectDefinition $postDefinition */
        $postDefinition = $endpoint->getType(Post::class);

        $titleFilter = new Filter();
        $titleFilter->name = 'title';
        $titleFilter->resolver = StringFilter::class;
        $titleFilter->type = StringComparisonExpression::class;
        $titleFilter->field = 'title';

        $dateFilter = new Filter();
        $dateFilter->name = 'date';
        $dateFilter->resolver = DateFilter::class;
        $dateFilter->type = DateComparisonExpression::class;
        $dateFilter->field = 'date';

        $resolver = \Mockery::mock(FilterResolverInterface::class);
        $resolver->expects('resolve')->andReturn([$titleFilter, $dateFilter]);

        $query = new QueryDefinition();
        $query->setName('allPosts');
        $query->setMeta('pagination', ['filters' => ['*', 'date' => false]]);

        $factory = new FilterFactory([$resolver]);
        $factory->build($query, $postDefinition, $endpoint);

        self::assertTrue($query->hasArgument('where'));
        self::assertEquals('AllPostsCondition', $query->getArgument('where')->getType());

        /** @var ObjectDefinition $condition */
        $condition = $endpoint->getType('AllPostsCondition');

        self::assertCount(1, $condition->getFields());
        self::assertEquals(StringComparisonExpression::class, $condition->getField('title')->getType());
        self::assertEquals(StringFilter::class, $condition->getField('title')->getResolver());
        self::assertEquals('title', $condition->getField('title')->getMeta('filter_field'));
    }
}