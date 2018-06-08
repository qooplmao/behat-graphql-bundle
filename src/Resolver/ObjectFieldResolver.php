<?php
/*******************************************************************************
 *  This file is part of the GraphQL Bundle package.
 *
 *  (c) YnloUltratech <support@ynloultratech.com>
 *
 *  For the full copyright and license information, please view the LICENSE
 *  file that was distributed with this source code.
 ******************************************************************************/

namespace Ynlo\GraphQLBundle\Resolver;

use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Persistence\Proxy;
use GraphQL\Deferred;
use GraphQL\Error\Error;
use GraphQL\Type\Definition\ResolveInfo;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerAwareTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\PropertyAccess\PropertyAccessor;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Ynlo\GraphQLBundle\Definition\FieldDefinition;
use Ynlo\GraphQLBundle\Definition\FieldsAwareDefinitionInterface;
use Ynlo\GraphQLBundle\Definition\QueryDefinition;
use Ynlo\GraphQLBundle\Definition\Registry\Endpoint;
use Ynlo\GraphQLBundle\Events\GraphQLEvents;
use Ynlo\GraphQLBundle\Events\GraphQLFieldEvent;
use Ynlo\GraphQLBundle\Events\GraphQLFieldInfo;
use Ynlo\GraphQLBundle\Model\NodeInterface;
use Ynlo\GraphQLBundle\Type\Definition\EndpointAwareInterface;
use Ynlo\GraphQLBundle\Type\Definition\EndpointAwareTrait;
use Ynlo\GraphQLBundle\Type\Types;
use Ynlo\GraphQLBundle\Util\IDEncoder;

/**
 * Default resolver for all object fields
 */
class ObjectFieldResolver implements ContainerAwareInterface, EndpointAwareInterface
{
    use ContainerAwareTrait;
    use EndpointAwareTrait;

    /**
     * @var int[]
     */
    private static $concurrentUsages = [];

    protected $definition;
    protected $deferredBuffer;
    protected $authorizationChecker;

    public function __construct(ContainerInterface $container, Endpoint $endpoint, FieldsAwareDefinitionInterface $definition, DeferredBuffer $deferredBuffer, AuthorizationCheckerInterface $authorizationChecker)
    {
        $this->container = $container;
        $this->endpoint = $endpoint;
        $this->definition = $definition;
        $this->deferredBuffer = $deferredBuffer;
        $this->authorizationChecker = $authorizationChecker;
    }

    /**
     * @param mixed       $root
     * @param array       $args
     * @param mixed       $context
     * @param ResolveInfo $info
     *
     * @return mixed|null|string
     *
     * @throws \Exception
     */
    public function __invoke($root, array $args, $context, ResolveInfo $info)
    {
        $value = null;
        $fieldDefinition = $this->definition->getField($info->fieldName);
        $eventDispatcher = $this->container->get(EventDispatcherInterface::class);

        $fieldInfo = new GraphQLFieldInfo($this->definition, $fieldDefinition, $info);
        $event = new GraphQLFieldEvent(
            $fieldInfo,
            $root,
            $args,
            $context
        );
        $eventDispatcher->dispatch(GraphQLEvents::PRE_READ_FIELD, $event);

        if ($event->isPropagationStopped() || $event->getValue()) {
            $eventDispatcher->dispatch(GraphQLEvents::POST_READ_FIELD, $event);

            return $event->getValue();
        }

        $this->verifyConcurrentUsage($context, $fieldDefinition);
        $this->denyAccessUnlessGranted($fieldDefinition);

        //when use external resolver or use a object method with arguments
        if (($resolver = $fieldDefinition->getResolver()) || $fieldDefinition->getArguments()) {
            $queryDefinition = new QueryDefinition();
            $queryDefinition->setName($fieldDefinition->getName());
            $queryDefinition->setType($fieldDefinition->getType());
            $queryDefinition->setNode($fieldDefinition->getNode());
            $queryDefinition->setArguments($fieldDefinition->getArguments());
            $queryDefinition->setList($fieldDefinition->isList());
            $queryDefinition->setRoles($fieldDefinition->getRoles());
            $queryDefinition->setMetas($fieldDefinition->getMetas());

            if ($resolver) {
                $queryDefinition->setResolver($resolver);
            } elseif ($fieldDefinition->getOriginType() === \ReflectionMethod::class) {
                $queryDefinition->setResolver($fieldDefinition->getOriginName());
            }

            $resolver = new ResolverExecutor($this->container, $this->endpoint, $queryDefinition);
            $value = $resolver($root, $args, $context, $info);
        } else {
            $accessor = new PropertyAccessor(true);
            $originName = $fieldDefinition->getOriginName() ?: $fieldDefinition->getName();
            $value = $accessor->getValue($root, $originName);
        }

        if (null !== $value && Types::ID === $fieldDefinition->getType() && $root instanceof NodeInterface) {
            //ID are formed with base64 representation of the Types and real database ID
            //in order to create a unique and global identifier for each resource
            //@see https://facebook.github.io/relay/docs/graphql-object-identification.html
            $value = IDEncoder::encode($root);
        }

        if ($value instanceof Collection) {
            $value = $value->toArray();
        }

        if ($value instanceof Proxy && $value instanceof NodeInterface && !$value->__isInitialized()) {
            $this->deferredBuffer->add($value);

            return new Deferred(
                function () use ($value) {
                    $this->deferredBuffer->loadBuffer();

                    return $this->deferredBuffer->getLoadedEntity($value);
                }
            );
        }

        $event->setValue($value);
        $eventDispatcher->dispatch(GraphQLEvents::POST_READ_FIELD, $event);

        return $event->getValue();
    }

    /**
     * @param QueryExecutionContext $context
     * @param FieldDefinition       $definition
     *
     * @throws Error
     */
    private function verifyConcurrentUsage(QueryExecutionContext $context, FieldDefinition $definition)
    {
        if ($maxConcurrentUsage = $definition->getMaxConcurrentUsage()) {
            $oid = spl_object_hash($definition);
            $usages = static::$concurrentUsages[$context->getQueryId()][$oid] ?? 1;
            if ($usages > $maxConcurrentUsage) {
                if (1 === $maxConcurrentUsage) {
                    $error = sprintf(
                        'The field "%s" can be fetched only once per query. This field can`t be used in a list.',
                        $definition->getName()
                    );
                } else {
                    $error = sprintf(
                        'The field "%s" can`t be fetched more than %s times per query.',
                        $definition->getName(),
                        $maxConcurrentUsage
                    );
                }
                throw new Error($error);
            }
            static::$concurrentUsages[$context->getQueryId()][$oid] = $usages + 1;
        }
    }

    /**
     * @throws Error
     */
    private function denyAccessUnlessGranted(FieldDefinition $fieldDefinition): void
    {
        if ($fieldDefinition->hasMeta('roles')) {
            $roles = $fieldDefinition->getMeta('roles');
        } else {
            $roles = $fieldDefinition->getRoles();
        }

        if ($roles && !$this->authorizationChecker->isGranted($roles, $fieldDefinition)) {
            throw new Error(sprintf('Access denied to "%s" field', $fieldDefinition->getName()));
        }
    }
}
