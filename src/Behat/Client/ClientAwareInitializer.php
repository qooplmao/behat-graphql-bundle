<?php
/*******************************************************************************
 *  This file is part of the GraphQL Bundle package.
 *
 *  (c) YnloUltratech <support@ynloultratech.com>
 *
 *  For the full copyright and license information, please view the LICENSE
 *  file that was distributed with this source code.
 ******************************************************************************/

namespace Ynlo\GraphQLBundle\Behat\Client;

use Behat\Behat\Context\Context;
use Behat\Behat\Context\Initializer\ContextInitializer;

/**
 * Inject GraphQLClient instance on very context implementing ClientAwareInterface
 */
class ClientAwareInitializer implements ContextInitializer
{
    /**
     * @var GraphQLClient
     */
    private $client;

    /**
     * @param GraphQLClient $client
     */
    public function __construct(GraphQLClient $client)
    {
        $this->client = $client;
    }

    /**
     * @param Context $context
     */
    public function initializeContext(Context $context)
    {
        if ($context instanceof ClientAwareInterface) {
            $context->setClient($this->client);
        }
    }
}