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

use Behat\Behat\Context\Environment\InitializedContextEnvironment;
use Behat\Behat\Tester\Result\StepResult;
use Behat\Behat\Tester\StepTester;
use Behat\Gherkin\Node\FeatureNode;
use Behat\Gherkin\Node\StepNode;
use Behat\Testwork\Environment\Environment;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * Inject Storage instance on very context implementing StorageAwareInterface
 */
class KernelAwareInitializer implements StepTester
{
    /**
     * @var StepTester
     */
    private $baseTester;

    /**
     * @var KernelInterface
     */
    private $kernel;

    public function __construct(StepTester $baseTester, KernelInterface $kernal)
    {
        $this->baseTester = $baseTester;
        $this->kernel = $kernal;
    }

    public function setUp(Environment $env, FeatureNode $feature, StepNode $step, $skip)
    {
        return $this->baseTester->setUp($env, $feature, $step, $skip);
    }

    public function test(Environment $env, FeatureNode $feature, StepNode $step, $skip)
    {
        /** @var InitializedContextEnvironment $env */
        foreach ($env->getContexts() as $context) {
            if ($context instanceof KernelAwareInterface) {
                $context->setKernel($this->kernel);
            }
        }

        return $this->baseTester->test($env, $feature, $step, $skip);
    }

    public function tearDown(Environment $env, FeatureNode $feature, StepNode $step, $skip, StepResult $result)
    {
        return $this->baseTester->tearDown($env, $feature, $step, $skip, $result);
    }
}