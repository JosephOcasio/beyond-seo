<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Symfony\Bundles;

use App\Symfony\ErrorHandlers\AppErrorHandler;
use LogicException;
use Symfony\Bundle\FrameworkBundle\DependencyInjection\Compiler\AddAnnotationsCachedReaderPass;
use Symfony\Bundle\FrameworkBundle\DependencyInjection\Compiler\AddDebugLogProcessorPass;
use Symfony\Bundle\FrameworkBundle\DependencyInjection\Compiler\AssetsContextPass;
use Symfony\Bundle\FrameworkBundle\DependencyInjection\Compiler\ContainerBuilderDebugDumpPass;
use Symfony\Bundle\FrameworkBundle\DependencyInjection\Compiler\ProfilerPass;
use Symfony\Bundle\FrameworkBundle\DependencyInjection\Compiler\RemoveUnusedSessionMarshallingHandlerPass;
use Symfony\Bundle\FrameworkBundle\DependencyInjection\Compiler\TestServiceContainerRealRefPass;
use Symfony\Bundle\FrameworkBundle\DependencyInjection\Compiler\TestServiceContainerWeakRefPass;
use Symfony\Bundle\FrameworkBundle\DependencyInjection\Compiler\UnusedTagsPass;
use Symfony\Bundle\FrameworkBundle\DependencyInjection\FrameworkExtension;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Adapter\ChainAdapter;
use Symfony\Component\Cache\Adapter\PhpArrayAdapter;
use Symfony\Component\Cache\Adapter\PhpFilesAdapter;
use Symfony\Component\Cache\DependencyInjection\CacheCollectorPass;
use Symfony\Component\Cache\DependencyInjection\CachePoolClearerPass;
use Symfony\Component\Cache\DependencyInjection\CachePoolPass;
use Symfony\Component\Cache\DependencyInjection\CachePoolPrunerPass;
use Symfony\Component\Config\Resource\ClassExistenceResource;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\DependencyInjection\AddConsoleCommandPass;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\Compiler\RegisterReverseContainerPass;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\ErrorHandler\ErrorHandler;
use Symfony\Component\EventDispatcher\DependencyInjection\RegisterListenersPass;
use Symfony\Component\Form\DependencyInjection\FormPass;
use Symfony\Component\HttpClient\DependencyInjection\HttpClientPass;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Bundle\Bundle;
use Symfony\Component\HttpKernel\DependencyInjection\ControllerArgumentValueResolverPass;
use Symfony\Component\HttpKernel\DependencyInjection\FragmentRendererPass;
use Symfony\Component\HttpKernel\DependencyInjection\LoggerPass;
use Symfony\Component\HttpKernel\DependencyInjection\RegisterControllerArgumentLocatorsPass;
use Symfony\Component\HttpKernel\DependencyInjection\RegisterLocaleAwareServicesPass;
use Symfony\Component\HttpKernel\DependencyInjection\RemoveEmptyControllerArgumentLocatorsPass;
use Symfony\Component\HttpKernel\DependencyInjection\ResettableServicePass;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Messenger\DependencyInjection\MessengerPass;
use Symfony\Component\Mime\DependencyInjection\AddMimeTypeGuesserPass;
use Symfony\Component\PropertyInfo\DependencyInjection\PropertyInfoPass;
use Symfony\Component\Routing\DependencyInjection\RoutingResolverPass;
use Symfony\Component\Serializer\DependencyInjection\SerializerPass;
use Symfony\Component\Translation\DependencyInjection\TranslationDumperPass;
use Symfony\Component\Translation\DependencyInjection\TranslationExtractorPass;
use Symfony\Component\Translation\DependencyInjection\TranslatorPass;
use Symfony\Component\Translation\DependencyInjection\TranslatorPathsPass;
use Symfony\Component\Validator\DependencyInjection\AddAutoMappingConfigurationPass;
use Symfony\Component\Validator\DependencyInjection\AddConstraintValidatorsPass;
use Symfony\Component\Validator\DependencyInjection\AddValidatorInitializersPass;
use Symfony\Component\VarExporter\Internal\Hydrator;
use Symfony\Component\VarExporter\Internal\Registry;

// Help opcache.preload discover always-needed symbols
//class_exists(ApcuAdapter::class);
class_exists(ArrayAdapter::class);
class_exists(ChainAdapter::class);
class_exists(PhpArrayAdapter::class);
class_exists(PhpFilesAdapter::class);
class_exists(Dotenv::class);
class_exists(ErrorHandler::class);
class_exists(Hydrator::class);
class_exists(Registry::class);

/**
 * Bundle.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class FrameworkBundle extends \Symfony\Bundle\FrameworkBundle\FrameworkBundle
{
    /**
     * @return void
     */
    public function boot(): void {
        $_ENV['DOCTRINE_DEPRECATIONS'] = $_SERVER['DOCTRINE_DEPRECATIONS'] ??= 'trigger';

        // Step 1: Store the current class loaders
        $originalClassLoaders = spl_autoload_functions();

        // Step 2: Unregister only the DebugClassLoader (but keep Elementor's)
        foreach ($originalClassLoaders as $loader) {
            $externLoaderClass = (is_array($loader) && is_string($loader[0])) ? $loader[0] : null;
            if ($externLoaderClass === "Elementor\\Autoloader") {
                continue;
            }
            spl_autoload_unregister($loader);
        }

        $kernelPath = $this->container->getParameter('kernel.project_dir');
        $composerAutoloadFile = $kernelPath . '/vendor/autoload.php';

        if (file_exists($composerAutoloadFile)) {
            $composerAutoloader = require $composerAutoloadFile;

            if ($composerAutoloader instanceof \Composer\Autoload\ClassLoader) {
                // Unregister Composer’s default loader
                spl_autoload_unregister([$composerAutoloader, 'loadClass']);

                // Wrap Composer’s loadClass with "safe load"
                spl_autoload_register(function ($class) use ($composerAutoloader) {
                    if (
                        class_exists($class, false) ||
                        interface_exists($class, false) ||
                        trait_exists($class, false)
                    ) {
                        return true; // already loaded → skip
                    }

                    return $composerAutoloader->loadClass($class);
                }, true, true);
            }
        }

        // Step 3: Execute the register function with the Composer class loader
        $handler = AppErrorHandler::register(null, true);
        error_reporting(E_ALL & ~E_NOTICE & ~E_USER_NOTICE & ~E_STRICT & ~E_DEPRECATED & ~E_USER_DEPRECATED);

        // Retrieve the current error handler
        $currentErrorHandler = set_error_handler(null);

        // Step 4: Restore the DebugClassLoader (but keep Elementor's)
        foreach ($originalClassLoaders as $loader) {
            $externLoaderClass = (is_array($loader) && is_string($loader[0])) ? $loader[0] : null;
            if ($externLoaderClass === "Elementor\\Autoloader") {
                continue;
            }
            spl_autoload_register($loader);
        }
        // Retrieve the current error handler
        $currentErrorHandler = set_error_handler(null);

        // When upgrading an existing Symfony application from 6.2 to 6.3, ...
        if ($this->container->has('debug.error_handler_configurator')) {
            //$this->container->get('debug.error_handler_configurator')->configure($handler);
        }

        if ($this->container->getParameter('kernel.http_method_override')) {
            Request::enableHttpMethodParameterOverride();
        }

        if ($this->container->hasParameter('kernel.trust_x_sendfile_type_header') &&
            $this->container->getParameter('kernel.trust_x_sendfile_type_header')) {
            BinaryFileResponse::trustXSendfileTypeHeader();
        }
    }


    /**
     * Returns the bundle's container extension.
     *
     * @throws LogicException
     */
    public function getContainerExtension(): ?ExtensionInterface
    {
        if (!isset($this->extension)) {
            $extension = new FrameworkExtension();

            if (null !== $extension) {
                if (!$extension instanceof ExtensionInterface) {
                    throw new LogicException(
                        sprintf(
                            'Extension "%s" must implement Symfony\Component\DependencyInjection\Extension\ExtensionInterface.',
                            esc_html(get_debug_type($extension))
                        )
                    );
                }

                // check naming convention
                $basename = preg_replace('/Bundle$/', '', $this->getName());
                $expectedAlias = Container::underscore($basename);

                if ($expectedAlias !== $extension->getAlias()) {
                    throw new LogicException(
                        sprintf(
                            'Users will expect the alias of the default extension of a bundle to be the underscored version of the bundle name ("%s"). You can override "Bundle::getContainerExtension()" if you want to use "%s" or another alias.',
                            esc_html($expectedAlias),
                            esc_html($extension->getAlias())
                        )
                    );
                }

                $this->extension = $extension;
            } else {
                $this->extension = false;
            }
        }

        return $this->extension ?: null;
    }

    /**
     * @return void
     */
    public function build(ContainerBuilder $container): void {
        Bundle::build($container);

        $registerListenersPass = new RegisterListenersPass();
        $registerListenersPass->setHotPathEvents([
            KernelEvents::REQUEST,
            KernelEvents::CONTROLLER,
            KernelEvents::CONTROLLER_ARGUMENTS,
            KernelEvents::RESPONSE,
            KernelEvents::FINISH_REQUEST,
        ]);
        if (class_exists(ConsoleEvents::class)) {
            $registerListenersPass->setNoPreloadEvents([
                ConsoleEvents::COMMAND,
                ConsoleEvents::TERMINATE,
                ConsoleEvents::ERROR,
            ]);
        }

        $container->addCompilerPass(new AssetsContextPass(), PassConfig::TYPE_BEFORE_OPTIMIZATION);
        $container->addCompilerPass(new LoggerPass(), PassConfig::TYPE_BEFORE_OPTIMIZATION, -32);
        $container->addCompilerPass(new RegisterControllerArgumentLocatorsPass());
        $container->addCompilerPass(new RemoveEmptyControllerArgumentLocatorsPass(), PassConfig::TYPE_BEFORE_REMOVING);
        $container->addCompilerPass(new RoutingResolverPass());
        $container->addCompilerPass(new ProfilerPass());
        // must be registered before removing private services as some might be listeners/subscribers
        // but as late as possible to get resolved parameters
        $container->addCompilerPass($registerListenersPass, PassConfig::TYPE_BEFORE_REMOVING);
        $this->addCompilerPassIfExists($container, AddConstraintValidatorsPass::class);
        $container->addCompilerPass(new AddAnnotationsCachedReaderPass(), PassConfig::TYPE_AFTER_REMOVING, -255);
        $this->addCompilerPassIfExists($container, AddValidatorInitializersPass::class);
        $this->addCompilerPassIfExists($container, AddConsoleCommandPass::class, PassConfig::TYPE_BEFORE_REMOVING);
        // must be registered as late as possible to get access to all Twig paths registered in
        // twig.template_iterator definition
        $this->addCompilerPassIfExists($container, TranslatorPass::class, PassConfig::TYPE_BEFORE_OPTIMIZATION, -32);
        $this->addCompilerPassIfExists($container, TranslatorPathsPass::class, PassConfig::TYPE_AFTER_REMOVING);
        $this->addCompilerPassIfExists($container, TranslationExtractorPass::class);
        $this->addCompilerPassIfExists($container, TranslationDumperPass::class);
        $container->addCompilerPass(new FragmentRendererPass());
        $this->addCompilerPassIfExists($container, SerializerPass::class);
        $this->addCompilerPassIfExists($container, PropertyInfoPass::class);
        $container->addCompilerPass(new ControllerArgumentValueResolverPass());
        $container->addCompilerPass(new CachePoolPass(), PassConfig::TYPE_BEFORE_OPTIMIZATION, 32);
        $container->addCompilerPass(new CachePoolClearerPass(), PassConfig::TYPE_AFTER_REMOVING);
        $container->addCompilerPass(new CachePoolPrunerPass(), PassConfig::TYPE_AFTER_REMOVING);
        $this->addCompilerPassIfExists($container, FormPass::class);
        $container->addCompilerPass(new ResettableServicePass(), PassConfig::TYPE_BEFORE_OPTIMIZATION, -32);
        $container->addCompilerPass(new RegisterLocaleAwareServicesPass());
        $container->addCompilerPass(new TestServiceContainerWeakRefPass(), PassConfig::TYPE_BEFORE_REMOVING, -32);
        $container->addCompilerPass(new TestServiceContainerRealRefPass(), PassConfig::TYPE_AFTER_REMOVING);
        $this->addCompilerPassIfExists($container, AddMimeTypeGuesserPass::class);
        $this->addCompilerPassIfExists($container, MessengerPass::class);
        $this->addCompilerPassIfExists($container, HttpClientPass::class);
        $this->addCompilerPassIfExists($container, AddAutoMappingConfigurationPass::class);
        $container->addCompilerPass(new RegisterReverseContainerPass(true));
        $container->addCompilerPass(new RegisterReverseContainerPass(false), PassConfig::TYPE_AFTER_REMOVING);
        $container->addCompilerPass(new RemoveUnusedSessionMarshallingHandlerPass());
        // must be registered after MonologBundle's LoggerChannelPass

        if ($container->getParameter('kernel.debug')) {
            $container->addCompilerPass(new AddDebugLogProcessorPass(), PassConfig::TYPE_BEFORE_OPTIMIZATION, 2);
            $container->addCompilerPass(new UnusedTagsPass(), PassConfig::TYPE_AFTER_REMOVING);
            $container->addCompilerPass(new ContainerBuilderDebugDumpPass(), PassConfig::TYPE_BEFORE_REMOVING, -255);
            $container->addCompilerPass(new CacheCollectorPass(), PassConfig::TYPE_BEFORE_REMOVING);
        }
    }

    private function addCompilerPassIfExists(
        ContainerBuilder $container,
        string $class,
        string $type = PassConfig::TYPE_BEFORE_OPTIMIZATION,
        int $priority = 0
    ): void {
        $container->addResource(new ClassExistenceResource($class));

        if (class_exists($class)) {
            $container->addCompilerPass(new $class(), $type, $priority);
        }
    }
}
