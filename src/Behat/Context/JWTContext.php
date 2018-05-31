<?php
/*******************************************************************************
 *  This file is part of the GraphQL Bundle package.
 *
 *  (c) YnloUltratech <support@ynloultratech.com>
 *
 *  For the full copyright and license information, please view the LICENSE
 *  file that was distributed with this source code.
 ******************************************************************************/

namespace Ynlo\GraphQLBundle\Behat\Context;

use Behat\Behat\Context\Context;
use Behat\Behat\Hook\Scope\BeforeStepScope;
use Behat\Symfony2Extension\Context\KernelAwareContext;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\HttpKernel\KernelInterface;
use Ynlo\GraphQLBundle\Behat\Authentication\JWT\TokenGeneratorInterface;
use Ynlo\GraphQLBundle\Behat\Authentication\UserResolverInterface;
use Ynlo\GraphQLBundle\Behat\Client\ClientAwareInterface;
use Ynlo\GraphQLBundle\Behat\Client\ClientAwareTrait;
use Ynlo\GraphQLBundle\Behat\GraphQLApiExtension;

/**
 * JWT Context
 */
final class JWTContext implements Context, KernelAwareContext, ClientAwareInterface
{
    use ClientAwareTrait;

    /**
     * @var Kernel
     */
    protected $kernel;

    private static $tokens = [];

    protected $token;

    /**
     * Sets Kernel instance.
     *
     * @param KernelInterface $kernel
     */
    public function setKernel(KernelInterface $kernel)
    {
        $this->kernel = $kernel;
    }

    /**
     * @BeforeScenario
     */
    public function beforeScenario()
    {
        $this->token = null;
    }

    /**
     * @BeforeStep
     */
    public function beforeStep(BeforeStepScope $scope)
    {
        $config = GraphQLApiExtension::getConfig();
        if (!isset($config['jwt']['users'])) {
            return;
        }

        if ($this->token) {
            $this->setToken($this->token);

            return;
        }

        foreach ($config['jwt']['users'] as $username) {
            if (\in_array($username, $scope->getFeature()->getTags())) {
                if (isset(self::$tokens[$username])) {
                    $this->token = self::$tokens[$username];
                    $this->setToken($this->token);
                    break;
                }

                $resolverClass = $config['jwt']['user_resolver'];
                $tokenGeneratorClass = $config['jwt']['generator'];

                /** @var UserResolverInterface $resolver */
                $resolver = new $resolverClass($this->kernel);
                $user = $resolver->findByUsername($username);

                /** @var TokenGeneratorInterface $tokenGenerator */
                $tokenGenerator = new $tokenGeneratorClass($this->kernel);
                $this->token = $tokenGenerator->generate($user);

                if (!$this->token) {
                    throw new \RuntimeException('Cant resolve a token using given credentials');
                }

                self::$tokens[$username] = $this->token;
                $this->setToken($this->token);
                break;
            }
        }
    }

    protected function setToken($token)
    {
        $tokenIn = $config['jwt']['token_in'] ?? 'header';
        $tokenName = $config['jwt']['token_name'] ?? 'Authorization';
        $tokenTemplate = $config['jwt']['token_template'] ?? 'Bearer {token}';

        if ($token) {
            $tokenValue = str_replace('{token}', $token, $tokenTemplate);
            switch ($tokenIn) {
                case 'header':
                    $this->client->setServerParameter(sprintf('HTTP_%s', $tokenName), $tokenValue);
                    break;
                case 'query':
                    $query = http_build_query([$tokenName => $tokenValue], null, '&');
                    $this->client->setEndpoint($this->client->getEndpoint().'?'.$query);
            }
        }
    }
}