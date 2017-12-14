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

use Ynlo\GraphQLBundle\Annotation;
use Ynlo\GraphQLBundle\Definition\FieldDefinition;
use Ynlo\GraphQLBundle\Definition\Registry\Endpoint;
use Ynlo\GraphQLBundle\Util\ClassUtils;
use Ynlo\GraphQLBundle\Util\TypeUtil;

/**
 * Resolve field of types queries using naming conventions
 */
class StandaloneFieldParser extends QueryAnnotationParser
{
    use AnnotationReaderAwareTrait;

    /**
     * {@inheritdoc}
     */
    public function supports($annotation): bool
    {
        return $annotation instanceof Annotation\Field;
    }

    /**
     * {@inheritdoc}
     */
    public function parse($annotation, \ReflectionClass $refClass, Endpoint $endpoint)
    {
        /** @var Annotation\Field $annotation */

        $field = new FieldDefinition();

        if ($annotation->name) {
            $field->setName($annotation->name);
        } else {
            $field->setName(lcfirst(ClassUtils::getDefaultName($refClass->getName())));
        }

        $objectType = null;
        preg_match('/(\w+)\\\\Field\\\\(\w+)$/', $refClass->getName(), $matches);
        if (!isset($matches[1]) || !$endpoint->hasType($matches[1])) {
            $error = sprintf('Can`t resolve a valid object type for field "%s"', $refClass->getName());
            throw new \RuntimeException($error);
        }
        $objectDefinition = $endpoint->getType($matches[1]);
        $objectDefinition->addField($field);

        $argAnnotations = $this->reader->getClassAnnotations($refClass);
        foreach ($argAnnotations as $argAnnotation) {
            if ($argAnnotation instanceof Annotation\Argument) {
                $this->resolveArgument($field, $argAnnotation);
            }
        }

        $field->setType(TypeUtil::normalize($annotation->type));
        $field->setList(TypeUtil::isTypeList($annotation->type));
        $field->setResolver($annotation->resolver ?? $refClass->getName());
        $field->setDeprecationReason($annotation->deprecationReason);
        $field->setDescription($annotation->description);

        foreach ($annotation->options as $option => $value) {
            $field->setMeta($option, $value);
        }
    }
}
