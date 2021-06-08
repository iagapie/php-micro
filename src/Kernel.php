<?php

declare(strict_types=1);

namespace IA\Micro;

use Exception;
use Laminas\HttpHandlerRunner\Emitter\EmitterInterface;
use LogicException;
use Nyholm\Psr7Server\ServerRequestCreatorInterface;
use Psr\Container\ContainerInterface;
use Psr\Http\Server\RequestHandlerInterface;
use ReflectionClass;
use RuntimeException;
use Symfony\Component\Config\ConfigCache;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Config\Loader\DelegatingLoader;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\Config\Loader\LoaderResolver;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\DependencyInjection\AddConsoleCommandPass;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Compiler\MergeExtensionConfigurationPass;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Dumper\PhpDumper;
use Symfony\Component\DependencyInjection\Loader\ClosureLoader;
use Symfony\Component\DependencyInjection\Loader\DirectoryLoader;
use Symfony\Component\DependencyInjection\Loader\GlobFileLoader;
use Symfony\Component\DependencyInjection\Loader\IniFileLoader;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\EventDispatcher\DependencyInjection\RegisterListenersPass;

use Throwable;

use function array_filter;
use function array_map;
use function array_unshift;
use function dirname;
use function error_log;
use function error_reporting;
use function explode;
use function ini_set;
use function is_dir;
use function is_file;
use function is_writable;
use function mkdir;
use function preg_grep;
use function realpath;
use function scandir;
use function set_time_limit;
use function sprintf;
use function str_replace;
use function str_starts_with;
use function trim;
use function ucfirst;

abstract class Kernel
{
    /**
     * @var ContainerInterface
     */
    private ContainerInterface $container;

    /**
     * @var string
     */
    private string $name;

    /**
     * @var string
     */
    private string $version;

    /**
     * @var array
     */
    private array $compilerPasses = [
        [RegisterListenersPass::class, PassConfig::TYPE_BEFORE_OPTIMIZATION, 0],
        [AddConsoleCommandPass::class, PassConfig::TYPE_BEFORE_OPTIMIZATION, 0],
    ];

    /**
     * Kernel constructor.
     * @param string $projectDir
     * @param string $environment
     * @param bool $debug
     */
    public function __construct(private string $projectDir, private string $environment, private bool $debug)
    {
        $this->name = $_SERVER['APP_NAME'] ?? 'Micro App';
        $this->version = $_SERVER['APP_VERSION'] ?? '1.0.0';

        foreach ([$this->getCacheDir(), $this->getLogsDir()] as $dir) {
            static::mkdir($dir);
            static::isWritable($dir);
        }
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @return string
     */
    public function getVersion(): string
    {
        return $this->version;
    }

    /**
     * @return string
     */
    public function getEnvironment(): string
    {
        return $this->environment;
    }

    /**
     * @return bool
     */
    public function isDebug(): bool
    {
        return $this->debug;
    }

    /**
     * Returns the parameters.
     *
     * @return array An array of parameters
     */
    public function getParameters(): array
    {
        return [
            'kernel.name' => $this->getName(),
            'kernel.version' => $this->getVersion(),
            'kernel.environment' => $this->getEnvironment(),
            'kernel.debug' => $this->isDebug(),
            'kernel.project_dir' => realpath($this->getProjectDir()) ?: $this->getProjectDir(),
            'kernel.resources_dir' => realpath($this->getResourcesDir()) ?: $this->getResourcesDir(),
            'kernel.config_dir' => realpath($this->getConfigDir()) ?: $this->getConfigDir(),
            'kernel.var_dir' => realpath($this->getVarDir()) ?: $this->getVarDir(),
            'kernel.cache_dir' => realpath($this->getCacheDir()) ?: $this->getCacheDir(),
            'kernel.logs_dir' => realpath($this->getLogsDir()) ?: $this->getLogsDir(),
            'kernel.wiring_dir' => realpath($this->getWiringDir()) ?: $this->getWiringDir(),
            'kernel.controller_dirs' => $this->getControllerDirs(),
        ];
    }

    /**
     * Gets the application root dir (path of the project's composer file).
     *
     * @return string
     * @throws LogicException
     */
    public static function getComposerDir(): string
    {
        $r = new ReflectionClass(static::class);

        if (!is_file($dir = $r->getFileName())) {
            throw new LogicException(
                sprintf('Cannot auto-detect project dir for kernel of class "%s".', $r->name)
            );
        }

        $dir = $rootDir = dirname($dir);
        while (!is_file($dir . '/composer.json')) {
            if ($dir === dirname($dir)) {
                return $rootDir;
            }
            $dir = dirname($dir);
        }

        return $dir;
    }

    /**
     * @return string The project root dir
     */
    public function getProjectDir(): string
    {
        return $this->projectDir;
    }

    /**
     * @return string[]
     */
    public function getControllerDirs(): array
    {
        $default = $this->getProjectDir() . '/src/Controller';

        if ($dirs = $_SERVER['CONTROLLER_DIRS'] ?? '') {
            $dirs = explode(',', $dirs);
            $dirs = array_map(
                function (string $dir) {
                    $dir = trim($dir);

                    if (empty($dir)) {
                        return '';
                    }

                    if (str_starts_with(trim($dir), '/')) {
                        return $dir;
                    }

                    return sprintf('%s/%s', $this->getProjectDir(), $dir);
                },
                $dirs
            );

            array_unshift($dirs, $default);

            return array_filter($dirs, 'is_dir');
        }

        return [$default];
    }

    /**
     * @return string
     */
    public function getResourcesDir(): string
    {
        return $this->getProjectDir() . '/resources';
    }

    /**
     * @return string
     */
    public function getConfigDir(): string
    {
        return $this->getResourcesDir() . '/config';
    }

    /**
     * @return string
     */
    public function getVarDir(): string
    {
        return $this->getProjectDir() . '/var';
    }

    /**
     * @return string
     */
    public function getCacheDir(): string
    {
        return $this->getVarDir() . '/cache/' . $this->getEnvironment();
    }

    /**
     * @return string
     */
    public function getLogsDir(): string
    {
        return $this->getVarDir() . '/logs/' . $this->getEnvironment();
    }

    /**
     * @return string
     */
    public function getWiringDir(): string
    {
        return $this->getResourcesDir() . '/wiring';
    }

    /**
     * @param bool $console
     */
    public static function run(bool $console = false): void
    {
        if (self::class === static::class) {
            throw new RuntimeException('Kernel should be extended.');
        }

        if ($console) {
            $input = new ArgvInput();

            if (null !== $env = $input->getParameterOption(['--env', '-e'], null, true)) {
                putenv('APP_ENV='.$_SERVER['APP_ENV'] = $_ENV['APP_ENV'] = $env);
            }

            if ($input->hasParameterOption('--no-debug', true)) {
                putenv('APP_DEBUG='.$_SERVER['APP_DEBUG'] = $_ENV['APP_DEBUG'] = '0');
            }

            $run = fn(self $kernel) => $kernel->runConsole($input);
        } else {
            $run = fn(self $kernel) => $kernel->runHttp();
        }

        try {
            $projectDir = static::getComposerDir();
            (new Dotenv())->bootEnv($projectDir.'/.env');
            $status = $run(new static($projectDir, $_SERVER['APP_ENV'] ?? 'dev', (bool)($_SERVER['APP_DEBUG'] ?? 0)));
        } catch (Throwable $e) {
            error_log(sprintf("\033[31m%s\033[0m", $e->getMessage()));
            $status = 1;
        }

        exit($status);
    }

    /**
     * @return int
     * @throws Exception
     */
    public function runHttp(): int
    {
        if ($this->isDebug()) {
            ini_set('display_errors', 'On');
            error_reporting(-1);
        }

        /** @var ServerRequestCreatorInterface $requestCreator */
        $requestCreator = $this->getContainer()->get(ServerRequestCreatorInterface::class);

        /** @var RequestHandlerInterface $handler */
        $handler = $this->getContainer()->get(RequestHandlerInterface::class);

        /** @var EmitterInterface $emitter */
        $emitter = $this->getContainer()->get(EmitterInterface::class);

        $request = $requestCreator->fromGlobals();
        $response = $handler->handle($request);
        $emitter->emit($response);

        return 0;
    }

    /**
     * @param InputInterface|null $input
     * @param OutputInterface|null $output
     * @return int
     * @throws Exception
     */
    public function runConsole(?InputInterface $input = null, ?OutputInterface $output = null): int
    {
        /** @var Application $application */
        $application = $this->getContainer()->get(Application::class);

        $application->getDefinition()->addOptions(
            [
                new InputOption(
                    '--env', '-e', InputOption::VALUE_REQUIRED, 'The Environment name.', $this->getEnvironment()
                ),
                new InputOption('--no-debug', null, InputOption::VALUE_NONE, 'Switches off debug mode.')
            ]
        );

        set_time_limit(0);

        return $application->run($input, $output);
    }

    /**
     * @return ContainerInterface
     * @throws Exception
     */
    public function getContainer(): ContainerInterface
    {
        if (isset($this->container)) {
            return $this->container;
        }

        $class = $this->getContainerClass();
        $cache = new ConfigCache($this->getCacheDir() . '/' . $class . '.php', $this->isDebug());
        $cachePath = $cache->getPath();

        if ($this->isDebug() || !$cache->isFresh()) {
            $containerBuilder = $this->getContainerBuilder();
            $containerBuilder->compile();

            $dumper = new PhpDumper($containerBuilder);

            $content = $dumper->dump(
                [
                    'class' => $class,
                    'file' => $cachePath,
                    'debug' => $this->isDebug(),
                ]
            );

            $cache->write($content, $containerBuilder->getResources());
        }

        require $cachePath;
        $container = new $class();
        $container->set('kernel', $this);

        return $this->container = $container;
    }

    /**
     * Gets a new ContainerBuilder instance used to build the service container.
     *
     * @return ContainerBuilder
     * @throws Exception
     */
    protected function getContainerBuilder(): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->getParameterBag()->add($this->getParameters());

        static::registerSynthetic($container, 'kernel', $this::class);

        // ensure these extensions are implicitly loaded
        $container->getCompilerPassConfig()->setMergePass(new MergeExtensionConfigurationPass());

        foreach ($this->compilerPasses as [$compilerPass, $type, $priority]) {
            $container->addCompilerPass(new $compilerPass(), $type, $priority);
        }

        $loader = static::getContainerLoader($container);

        if (is_dir($this->getMicroWiringDir())) {
            $this->loadServices($loader, $this->getMicroWiringDir());
            $this->loadServices($loader, $this->getMicroWiringDir() . '/' . $this->getEnvironment());
        }

        $this->loadServices($loader, $this->getWiringDir());
        $this->loadServices($loader, $this->getWiringDir() . '/' . $this->getEnvironment());

        return $container;
    }

    /**
     * Gets the container class.
     *
     * @return string The container class
     *
     */
    protected function getContainerClass(): string
    {
        $class = str_replace('\\', '_', $this::class);
        $class .= ucfirst($this->environment);
        $class .= $this->debug ? 'Debug' : '';
        $class .= 'Container';

        return $class;
    }

    /**
     * @param ContainerBuilder $container
     * @param string $id
     * @param string $class
     */
    protected static function registerSynthetic(ContainerBuilder $container, string $id, string $class): void
    {
        $container
            ->register($id, $class)
            ->setAutoconfigured(true)
            ->setSynthetic(true)
            ->setPublic(true);

        $container
            ->setAlias($class, $id)
            ->setPublic(true);
    }

    /**
     * Returns a loader for the container.
     *
     * @param ContainerBuilder $container
     * @return DelegatingLoader
     */
    protected static function getContainerLoader(ContainerBuilder $container): DelegatingLoader
    {
        $locator = new FileLocator();
        $resolver = new LoaderResolver(
            [
                new XmlFileLoader($container, $locator),
                new YamlFileLoader($container, $locator),
                new IniFileLoader($container, $locator),
                new PhpFileLoader($container, $locator),
                new GlobFileLoader($container, $locator),
                new DirectoryLoader($container, $locator),
                new ClosureLoader($container),
            ]
        );

        return new DelegatingLoader($resolver);
    }

    /**
     * @param LoaderInterface $loader
     * @param string $path
     * @throws Exception
     */
    protected static function loadServices(LoaderInterface $loader, string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        foreach (preg_grep('/^.*\.(xml|yaml|yml|ini|php)$/i', scandir($path)) as $file) {
            $loader->load($path . '/' . $file);
        }
    }

    /**
     * @param string $dir
     * @throws RuntimeException
     */
    protected static function mkdir(string $dir): void
    {
        if (!is_dir($dir)) {
            if (false === mkdir($dir, 0777, true) && !is_dir($dir)) {
                throw new RuntimeException(sprintf('Unable to create directory (%s).', $dir));
            }
        }
    }

    /**
     * @param string $dir
     * @throws RuntimeException
     */
    protected static function isWritable(string $dir): void
    {
        if (!is_writable($dir)) {
            throw new RuntimeException(sprintf('Unable to write in directory (%s).', $dir));
        }
    }

    /**
     * @return string
     */
    protected function getMicroWiringDir(): string
    {
        return dirname(__DIR__) . '/resources/wiring';
    }
}