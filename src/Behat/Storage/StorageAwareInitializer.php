<?php
/*******************************************************************************
 *  This file is part of the GraphQL Bundle package.
 *
 *  (c) YnloUltratech <support@ynloultratech.com>
 *
 *  For the full copyright and license information, please view the LICENSE
 *  file that was distributed with this source code.
 ******************************************************************************/

namespace Ynlo\GraphQLBundle\Behat\Storage;

use Behat\Behat\Context\Context;
use Behat\Behat\Context\Initializer\ContextInitializer;

/**
 * Inject Storage instance on very context implementing StorageAwareInterface
 */
class StorageAwareInitializer implements ContextInitializer
{
    /**
     * @var Storage
     */
    private $storage;

    /**
     * @param Storage $storage
     */
    public function __construct(Storage $storage)
    {
        $this->storage = $storage;
    }

    /**
     * @param Context $context
     */
    public function initializeContext(Context $context)
    {
        if ($context instanceof StorageAwareInterface) {
            $context->setStorage($this->storage);
        }
    }
}