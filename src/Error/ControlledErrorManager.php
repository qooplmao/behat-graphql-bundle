<?php
/*******************************************************************************
 *  This file is part of the GraphQL Bundle package.
 *
 *  (c) YnloUltratech <support@ynloultratech.com>
 *
 *  For the full copyright and license information, please view the LICENSE
 *  file that was distributed with this source code.
 ******************************************************************************/

namespace Ynlo\GraphQLBundle\Error;

use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;
use Symfony\Component\HttpKernel\Kernel;
use Ynlo\GraphQLBundle\Exception\ControlledErrorInterface;

class ControlledErrorManager
{
    protected $kernel;

    /**
     * @var array
     */
    protected $config = [];

    /**
     * @var bool
     */
    protected $loaded = false;

    /**
     * @var array|MappedControlledError[]
     */
    protected $errors = [];

    /**
     * ControlledErrorManager constructor.
     *
     * @param Kernel $kernel
     * @param array  $config
     */
    public function __construct(Kernel $kernel, $config = [])
    {
        $this->kernel = $kernel;
        $this->config = $config;
    }

    /**
     * @return array|MappedControlledError[]
     */
    public function all(): array
    {
        if (!$this->loaded) {
            $this->loadAllErrors();
        }

        return $this->errors;
    }

    /**
     * @param MappedControlledError $error
     */
    public function add(MappedControlledError $error)
    {
        if ($this->has($error->getCode())) {
            $message = sprintf(
                'Duplicate error definition, the error code "%s" can\'t be used for "%s" because this code is already used with: "%s"',
                $error->getCode(),
                $error->getDescription(),
                $this->all()[$error->getCode()]->getDescription()
            );
            throw new \LogicException($message);
        }

        $this->errors[$error->getCode()] = $error;
    }

    /**
     * @param string $code
     *
     * @return bool
     */
    public function has($code)
    {
        return isset($this->all()[$code]);
    }

    /**
     * @param string $code
     *
     * @return MappedControlledError
     */
    public function get($code)
    {
        return $this->all()[$code];
    }

    /**
     * Clear errors and cache
     */
    public function clear(): void
    {
        $this->errors = [];
        if (file_exists($this->cacheFileName())) {
            @unlink($this->cacheFileName());
        }
    }

    /**
     * @return void
     */
    private function loadAllErrors(): void
    {
        $this->loadFromCache();
        if ($this->loaded) {
            return;
        }

        $loadedErrors = [];
        foreach ($this->config['map'] as $code => $error) {
            $loadedErrors[] = new MappedControlledError(
                $error['category'],
                $error['message'],
                $code,
                $error['description']
            );
        }

        if ($this->config['autoload']['enabled'] ?? false) {
            foreach ($this->controlledExceptions() as $error) {
                $loadedErrors[] = $error;
            }
        }

        $this->loaded = true;
        foreach ($loadedErrors as $error) {
            $this->add($error);
        }

        ksort($this->errors, SORT_NATURAL);

        $this->saveCache();
    }

    /**
     * @return MappedControlledError[]|iterable
     */
    private function controlledExceptions(): iterable
    {
        $paths = [];
        if (Kernel::VERSION_ID >= 40000) {
            foreach ($this->config['autoload']['locations'] ?? [] as $location) {
                $path = $this->kernel->getRootDir().'/'.$location;
                if (file_exists($path)) {
                    $paths[$path] = 'App\\'.$location;
                }
            }
        }

        foreach ($this->kernel->getBundles() as $bundle) {
            foreach ($this->config['autoload']['locations'] ?? [] as $location) {
                $path = $bundle->getPath().'/'.$location;
                if (file_exists($path)) {
                    $paths[$path] = $bundle->getNamespace().'\\'.$location;
                }
            }
        }

        foreach ($paths as $path => $namespace) {
            $finder = new Finder();
            $finder
                ->in($path)
                ->name('*.php');

            /** @var SplFileInfo[] $files */
            $files = $finder->files();
            foreach ($files as $file) {
                $className = sprintf(
                    '%s\%s',
                    $namespace,
                    preg_replace(
                        '/.php$/',
                        null,
                        str_replace('/', '\\', $file->getRelativePathname())
                    )
                );
                if (class_exists($className)) {
                    $allowed = false;
                    if ($whitelist = $this->config['autoload']['whitelist'] ?? []) {
                        foreach ($whitelist as $exp) {
                            if (preg_match($exp, $className)) {
                                $allowed = true;
                                continue;
                            }
                        }
                    }

                    if ($blackList = $this->config['autoload']['blacklist'] ?? []) {
                        foreach ($blackList as $exp) {
                            if (preg_match($exp, $className)) {
                                $allowed = false;
                                continue;
                            }
                        }
                    }

                    if (!$allowed) {
                        continue;
                    }

                    $ref = new \ReflectionClass($className);
                    if ($ref->implementsInterface(ControlledErrorInterface::class) && $ref->isInstantiable()) {
                        /** @var ControlledErrorInterface $error */
                        $error = $ref->newInstanceWithoutConstructor();
                        yield  new MappedControlledError(
                            $error->getCategory(),
                            $error->getMessage(),
                            $error->getCode(),
                            $error->getDescription()
                        );
                    }
                }
            }
        }
    }

    /**
     * @return string
     */
    private function cacheFileName(): string
    {
        return $this->kernel->getCacheDir().DIRECTORY_SEPARATOR.'graphql.controlled_errors.meta';
    }

    /**
     * Load cache
     */
    private function loadFromCache(): void
    {
        if (file_exists($this->cacheFileName())) {
            $content = @file_get_contents($this->cacheFileName());
            if ($content) {
                $this->loaded = true;
                $this->errors = unserialize($content, ['allowed_classes' => [MappedControlledError::class]]);
            }
        }
    }

    /**
     * Save cache
     */
    private function saveCache(): void
    {
        file_put_contents($this->cacheFileName(), serialize($this->errors));
    }
}