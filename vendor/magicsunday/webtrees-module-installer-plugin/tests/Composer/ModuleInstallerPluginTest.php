<?php

/**
 * This file is part of the package magicsunday/webtrees-module-installer-plugin.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\Webtrees\Test\Composer;

use Composer\Composer;
use Composer\DependencyResolver\Operation\InstallOperation;
use Composer\DependencyResolver\Operation\UninstallOperation;
use Composer\DependencyResolver\Operation\UpdateOperation;
use Composer\Installer\PackageEvent;
use Composer\Package\Package;
use Composer\Repository\InstalledRepositoryInterface;
use Composer\Repository\RepositoryManager;
use MagicSunday\Webtrees\Composer\ModuleInstaller;
use MagicSunday\Webtrees\Composer\ModuleInstallerPlugin;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Tests the package-event handling of the installer plugin. The plugin is wired to a real
 * ModuleInstaller so the type routing it delegates to is exercised for real, not re-stubbed.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/GPL-3.0 GNU General Public License v3.0
 * @link    https://github.com/magicsunday/webtrees-module-installer-plugin/
 */
#[CoversClass(ModuleInstallerPlugin::class)]
#[UsesClass(ModuleInstaller::class)]
class ModuleInstallerPluginTest extends TestCase
{
    use CreatesModuleInstaller;

    /**
     * Creates a package with the given name and composer type.
     *
     * @param string $name The composer package name
     * @param string $type The composer package type
     *
     * @return Package
     */
    private function createPackage(string $name, string $type): Package
    {
        $package = new Package($name, '1.0.0.0', '1.0.0');
        $package->setType($type);

        return $package;
    }

    /**
     * Builds a pre-package event wrapping an install operation for the given package.
     *
     * @param Package $package The package the operation installs
     *
     * @return PackageEvent
     */
    private function createInstallEvent(Package $package): PackageEvent
    {
        $event = $this->createStub(PackageEvent::class);
        $event->method('getOperation')->willReturn(new InstallOperation($package));

        return $event;
    }

    /**
     * Builds a pre-package event wrapping an update operation for the given package.
     *
     * @param Package $package The package the operation updates
     *
     * @return PackageEvent
     */
    private function createUpdateEvent(Package $package): PackageEvent
    {
        $event = $this->createStub(PackageEvent::class);
        $event->method('getOperation')->willReturn(new UpdateOperation($package, $package));

        return $event;
    }

    /**
     * Creates a Composer stub whose local repository exposes the given canonical packages.
     *
     * @param array<Package> $packages The canonical packages the local repository should return
     *
     * @return Composer
     */
    private function createComposerWithCanonicalPackages(array $packages): Composer
    {
        $localRepository = $this->createStub(InstalledRepositoryInterface::class);
        $localRepository->method('getCanonicalPackages')->willReturn($packages);

        $repositoryManager = $this->createStub(RepositoryManager::class);
        $repositoryManager->method('getLocalRepository')->willReturn($localRepository);

        $composer = $this->createStub(Composer::class);
        $composer->method('getRepositoryManager')->willReturn($repositoryManager);

        return $composer;
    }

    /**
     * Creates a plugin wired to a real installer and, optionally, a stubbed Composer instance.
     *
     * @param Composer|null $composer The Composer instance to inject, or null when unused
     *
     * @return array{0: ModuleInstallerPlugin, 1: ReflectionClass<ModuleInstallerPlugin>}
     */
    private function createPlugin(?Composer $composer = null): array
    {
        $plugin     = new ModuleInstallerPlugin();
        $reflection = new ReflectionClass($plugin);

        $reflection->getProperty('installer')->setValue($plugin, $this->createModuleInstaller());

        if ($composer !== null) {
            $reflection->getProperty('composer')->setValue($plugin, $composer);
        }

        return [$plugin, $reflection];
    }

    /**
     * Reads the plugin's pending package operations keyed by package name.
     *
     * @param ModuleInstallerPlugin                $plugin     The plugin to inspect
     * @param ReflectionClass<ModuleInstallerPlugin> $reflection A reflection handle for the plugin
     *
     * @return array<string, InstallOperation|UpdateOperation|UninstallOperation>
     */
    private function readPendingPackages(ModuleInstallerPlugin $plugin, ReflectionClass $reflection): array
    {
        /** @var array<string, InstallOperation|UpdateOperation|UninstallOperation> $pendingPackages */
        $pendingPackages = $reflection->getProperty('pendingPackages')->getValue($plugin);

        return $pendingPackages;
    }

    /**
     * A webtrees-theme package must be queued for processing just like a module.
     *
     * @return void
     */
    #[Test]
    public function queuesThemePackageForProcessing(): void
    {
        [$plugin, $reflection] = $this->createPlugin();

        $plugin->onPrePackageEvent($this->createInstallEvent($this->createPackage('vendor/example-theme', 'webtrees-theme')));

        self::assertArrayHasKey('vendor/example-theme', $this->readPendingPackages($plugin, $reflection));
    }

    /**
     * A regular webtrees-module package must keep being queued for processing.
     *
     * @return void
     */
    #[Test]
    public function queuesModulePackageForProcessing(): void
    {
        [$plugin, $reflection] = $this->createPlugin();

        $plugin->onPrePackageEvent($this->createInstallEvent($this->createPackage('vendor/example-module', 'webtrees-module')));

        self::assertArrayHasKey('vendor/example-module', $this->readPendingPackages($plugin, $reflection));
    }

    /**
     * An unrelated package type must not be queued for processing.
     *
     * @return void
     */
    #[Test]
    public function ignoresUnrelatedPackage(): void
    {
        [$plugin, $reflection] = $this->createPlugin();

        $plugin->onPrePackageEvent($this->createInstallEvent($this->createPackage('vendor/example-library', 'library')));

        self::assertSame([], $this->readPendingPackages($plugin, $reflection));
    }

    /**
     * When the webtrees core package is updated, all handled packages must be re-queued so they
     * survive the upgrade — including webtrees-theme packages, while unrelated packages are skipped.
     *
     * @return void
     */
    #[Test]
    public function reinstallsHandledPackagesWhenRootPackageUpdates(): void
    {
        $canonicalPackages = [
            $this->createPackage('vendor/canonical-theme', 'webtrees-theme'),
            $this->createPackage('vendor/canonical-module', 'webtrees-module'),
            $this->createPackage('vendor/canonical-library', 'library'),
        ];

        $composer = $this->createComposerWithCanonicalPackages($canonicalPackages);

        [$plugin, $reflection] = $this->createPlugin($composer);

        $rootPackage = $this->createPackage(ModuleInstaller::ROOT_PACKAGE_NAME, 'library');

        $plugin->onPrePackageEvent($this->createUpdateEvent($rootPackage));

        $pendingPackages = $this->readPendingPackages($plugin, $reflection);

        self::assertCount(2, $pendingPackages);
        self::assertArrayHasKey('vendor/canonical-theme', $pendingPackages);
        self::assertArrayHasKey('vendor/canonical-module', $pendingPackages);
        self::assertArrayNotHasKey('vendor/canonical-library', $pendingPackages);
    }
}
