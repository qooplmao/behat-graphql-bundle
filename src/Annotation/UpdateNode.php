<?php
/*******************************************************************************
 *  This file is part of the GraphQL Bundle package.
 *
 *  (c) YnloUltratech <support@ynloultratech.com>
 *
 *  For the full copyright and license information, please view the LICENSE
 *  file that was distributed with this source code.
 ******************************************************************************/

namespace Ynlo\GraphQLBundle\Annotation;

use Doctrine\Common\Annotations\Annotation\Target;

/**
 * @Annotation()
 *
 * @Target({"CLASS"})
 */
final class UpdateNode
{
    /**
     * @var string
     */
    public $input;

    /**
     * @var array
     */
    public $validationGroups = [];

    /**
     * @var string
     */
    public $description;

    /**
     * @var string
     */
    public $node;

    /**
     * @var string
     */
    public $mutationName;

    /**
     * @var string
     */
    public $deprecationReason;
}
