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

use Behat\Behat\Context\Context;
use Behat\Behat\Context\Initializer\ContextInitializer;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * Inject Storage instance on very context implementing StorageAwareInterface
 */
class KernelAwareInitializer implements ContextInitializer
{
    /**
     * @var KernelInterface
     */
    private $kernel;

    /**
     * @param KernelInterface $kernal
     */
    public function __construct(KernelInterface $kernal)
    {
        $this->kernel = $kernal;
    }

    /**
     * @param Context $context
     */
    public function initializeContext(Context $context)
    {
        if ($context instanceof KernelAwareInterface) {
            $context->setKernel($this->kernel);
        }
    }
}