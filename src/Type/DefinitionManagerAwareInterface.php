<?php
/*******************************************************************************
 *  This file is part of the GraphQL Bundle package.
 *
 *  (c) YnloUltratech <support@ynloultratech.com>
 *
 *  For the full copyright and license information, please view the LICENSE
 *  file that was distributed with this source code.
 ******************************************************************************/

namespace Ynlo\GraphQLBundle\Type;

use Ynlo\GraphQLBundle\DefinitionLoader\DefinitionManager;

/**
 * Interface DefinitionManagerAwareInterface
 */
interface DefinitionManagerAwareInterface
{
    /**
     * @param DefinitionManager $manager
     *
     * @return mixed
     */
    public function setDefinitionManager(DefinitionManager $manager);
}
