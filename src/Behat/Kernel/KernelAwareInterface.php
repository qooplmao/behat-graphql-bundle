<?php
/*******************************************************************************
 *  This file is part of the GraphQL Bundle package.
 *
 *  (c) YnloUltratech <support@ynloultratech.com>
 *
 *  For the full copyright and license information, please view the LICENSE
 *  file that was distributed with this source code.
 ******************************************************************************/

namespace Ynlo\GraphQLBundle\Behat\Kernel;

use Symfony\Component\HttpKernel\KernelInterface;

/**
 * KernelAwareInterface should be implemented by classes that depends on the kernel.
 */
interface KernelAwareInterface
{
    /**
     * @param KernelInterface $kernel
     *
     * @return KernelAwareInterface
     */
    public function setKernel(KernelInterface $kernel): KernelAwareInterface;
}
