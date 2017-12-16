<?php
/*******************************************************************************
 *  This file is part of the GraphQL Bundle package.
 *
 *  (c) YnloUltratech <support@ynloultratech.com>
 *
 *  For the full copyright and license information, please view the LICENSE
 *  file that was distributed with this source code.
 ******************************************************************************/

namespace Ynlo\GraphQLBundle\Definition\Loader\Annotation;

use Symfony\Component\Form\FormFactory;
use Ynlo\GraphQLBundle\Annotation;
use Ynlo\GraphQLBundle\Definition\MutationDefinition;
use Ynlo\GraphQLBundle\Definition\Registry\Endpoint;
use Ynlo\GraphQLBundle\Util\ClassUtils;

/**
 * Parse mutation annotation to fetch definitions
 */
class MutationAnnotationParser extends QueryAnnotationParser
{
    use AnnotationReaderAwareTrait;

    /**
     * @var FormFactory
     */
    protected $formFactory;

    /**
     * @var Endpoint
     */
    protected $endpoint;

    /**
     * @param FormFactory $formFactory
     */
    public function __construct(FormFactory $formFactory)
    {
        $this->formFactory = $formFactory;
    }

    /**
     * {@inheritdoc}
     */
    public function supports($annotation): bool
    {
        return $annotation instanceof Annotation\Mutation;
    }

    /**
     * {@inheritdoc}
     */
    public function parse($annotation, \ReflectionClass $refClass, Endpoint $endpoint)
    {
        /** @var Annotation\Mutation $annotation */

        if (!preg_match('/Bundle\\\\Mutation\\\\/', $refClass->getName())) {
            $error = sprintf(
                'Annotation "@Mutation" in the class "%s" is not valid, 
            mutations can only be applied to classes inside "...Bundle\Mutation\..."',
                $refClass->getName()
            );
            throw new \Exception($error);
        }

        if (!$refClass->hasMethod('__invoke') && !$annotation->resolver) {
            $error = sprintf(
                'The class "%s" should have a method "__invoke" to process the mutation.',
                $refClass->getName()
            );
            throw new \Exception($error);
        }

        $mutation = new MutationDefinition();

        if ($annotation->name) {
            $mutation->setName($annotation->name);
        } else {
            $mutation->setName(lcfirst(ClassUtils::getDefaultName($refClass->getName())));
        }

        $endpoint->addMutation($mutation);

        if (!$annotation->payload) {
            if (class_exists($refClass->getName().'Payload')) {
                $annotation->payload = $refClass->getName().'Payload';
                if (!$endpoint->hasTypeForClass($annotation->payload)) {
                    $error = sprintf(
                        'The payload "%s" exist but does not exist a valid GraphQL type, is missing ObjectType annotation?',
                        $annotation->payload
                    );
                    throw new \Exception($error);
                }
            }
        }

        $mutation->setType($annotation->payload);

        if (!$mutation->getType()) {
            $error = sprintf(
                'The mutation "%s" does not have a valid payload,
                 create a file called %sPayload or specify a payload.',
                $mutation->getName(),
                $refClass->getName()
            );
            throw new \Exception($error);
        }

        $argAnnotations = $this->reader->getClassAnnotations($refClass);
        foreach ($argAnnotations as $argAnnotation) {
            if ($argAnnotation instanceof Annotation\Argument) {
                $this->resolveArgument($mutation, $argAnnotation);
            }
        }

        if ($annotation->node) {
            $mutation->setNode($annotation->node);
        } else {
            if ($node = ClassUtils::getNodeFromClass($refClass->getName())) {
                $mutation->setNode($node);
            }
        }

        $mutation->setResolver($annotation->resolver ?? $refClass->getName());
        $mutation->setDeprecationReason($annotation->deprecationReason);
        $mutation->setDescription($annotation->description);

        //enable form auto-loaded by default
        if (!isset($annotation->options['form'])) {
            $annotation->options['form'] = true;
        }

        foreach ($annotation->options as $option => $value) {
            $mutation->setMeta($option, $value);
        }
    }
}
