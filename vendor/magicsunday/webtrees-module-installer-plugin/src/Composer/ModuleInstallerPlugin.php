<?php

/**
 * This file is part of the package magicsunday/webtrees-module-installer-plugin.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\Webtrees\Composer;

use Composer\Composer;
use Composer\DependencyResolver\Operation\InstallOperation;
use Composer\DependencyResolver\Operation\UninstallOperation;
use Composer\DependencyResolver\Operation\UpdateOperation;
use Composer\Installer\PackageEvent;
use Composer\Installer\PackageEvents;
use Composer\IO\IOInterface;
use Composer\Package\PackageInterface;
use Composer\Plugin\PluginInterface;

use function sprintf;

/**
 * A Composer plugin to handle the installation, update, and uninstallation of webtrees modules and themes.
 * This plugin listens to various Composer events and manages the deployment of packages of type
 * "webtrees-module" and "webtrees-theme" into a specific target directory within the webtrees application.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/GPL-3.0 GNU General Public License v3.0
 * @link    https://github.com/magicsunday/webtrees-module-installer-plugin/
 */
class ModuleInstallerPlugin implements PluginInterface
{
    private Composer $composer;

    private IOInterface $io;

    private ModuleInstaller $installer;

    /**
     * @var array<string, InstallOperation|UpdateOperation|UninstallOperation>
     */
    private array $pendingPackages = [];

    /**
     * Activates the plugin by initializing and registering the custom module installer,
     * as well as subscribing to package-level events handled by the Composer event dispatcher.
     *
     * @param Composer    $composer The Composer instance to be configured with the custom installer
     * @param IOInterface $io       The input/output interface to enable interaction with Composer
     *
     * @return void
     */
    public function activate(Composer $composer, IOInterface $io): void
    {
        $this->composer  = $composer;
        $this->io        = $io;
        $this->installer = new ModuleInstaller($io, $composer, ModuleInstaller::PACKAGE_TYPE);

        // Register the custom installer
        $composer
            ->getInstallationManager()
            ->addInstaller($this->installer);

        // Subscribe to package-level events
        $dispatcher = $composer->getEventDispatcher();

        $dispatcher->addListener(PackageEvents::PRE_PACKAGE_INSTALL, $this->onPrePackageEvent(...));
        $dispatcher->addListener(PackageEvents::PRE_PACKAGE_UPDATE, $this->onPrePackageEvent(...));
        $dispatcher->addListener(PackageEvents::PRE_PACKAGE_UNINSTALL, $this->onPrePackageEvent(...));

        $dispatcher->addListener(PackageEvents::POST_PACKAGE_INSTALL, $this->onPostPackageEvent(...));
        $dispatcher->addListener(PackageEvents::POST_PACKAGE_UPDATE, $this->onPostPackageEvent(...));
        $dispatcher->addListener(PackageEvents::POST_PACKAGE_UNINSTALL, $this->onPostPackageEvent(...));
    }

    /**
     * Deactivates the plugin.
     *
     * @param Composer    $composer The Composer instance
     * @param IOInterface $io       The input/output interface
     *
     * @return void
     */
    public function deactivate(Composer $composer, IOInterface $io): void
    {
        // nothing to do
    }

    /**
     * Uninstalls the plugin.
     *
     * @param Composer    $composer The Composer instance
     * @param IOInterface $io       The input/output interface
     *
     * @return void
     */
    public function uninstall(Composer $composer, IOInterface $io): void
    {
        // nothing to do
    }

    /**
     * Handles actions to be performed before a package operation is executed.
     *
     * @param PackageEvent $event The package event instance
     *
     * @return void
     */
    public function onPrePackageEvent(PackageEvent $event): void
    {
        $this->installer->skipInstallation(false);

        /** @var InstallOperation|UpdateOperation|UninstallOperation $operation */
        $operation = $event->getOperation();
        $package   = $this->getPackageFromOperation($operation);

        if ($this->installer->supports($package->getType())) {
            $this->installer->skipInstallation(true);

            $this->pendingPackages[$package->getName()] = $operation;
        }

        // If the root package gets updated, reinstall all packages handled by this installer
        if (
            ($operation instanceof UpdateOperation)
            && ($package->getName() === ModuleInstaller::ROOT_PACKAGE_NAME)
        ) {
            $canonicalPackages = $this->composer
                ->getRepositoryManager()
                ->getLocalRepository()
                ->getCanonicalPackages();

            foreach ($canonicalPackages as $canonicalPackage) {
                if (!$this->installer->supports($canonicalPackage->getType())) {
                    continue;
                }

                if (!isset($this->pendingPackages[$canonicalPackage->getName()])) {
                    $this->pendingPackages[$canonicalPackage->getName()] = new InstallOperation($canonicalPackage);
                }
            }
        }
    }

    /**
     * Retrieves the package associated with the given operation.
     *
     * @param InstallOperation|UpdateOperation|UninstallOperation $operation The operation instance
     *
     * @return PackageInterface The package associated with the operation
     */
    private function getPackageFromOperation(InstallOperation|UpdateOperation|UninstallOperation $operation): PackageInterface
    {
        if ($operation instanceof UpdateOperation) {
            return $operation->getTargetPackage();
        }

        return $operation->getPackage();
    }

    /**
     * Handles the post-package event to process package operations.
     *
     * This method is responsible for processing pending package operations
     * such as installations, updates, and removals for Webtrees modules.
     * It writes the results of the operations to the I/O interface and
     * executes the installation manager to perform the necessary actions.
     *
     * @param PackageEvent $event The package event triggered by Composer
     *
     * @return void
     */
    public function onPostPackageEvent(PackageEvent $event): void
    {
        // Skip because there are no pending packages to install
        if ($this->pendingPackages === []) {
            return;
        }

        // Skip because the package "fisharebest/webtrees" is missing
        if ($this->installer->findWebtreesBasePath() === null) {
            return;
        }

        $this->io->write('<info>Processing Webtrees modules in the "modules_v4" directory</info>');

        $counters = [
            'install'   => 0,
            'update'    => 0,
            'uninstall' => 0,
        ];

        foreach ($this->pendingPackages as $operation) {
            $type = $operation->getOperationType();

            if (isset($counters[$type])) {
                ++$counters[$type];
            }
        }

        $this->io->write(
            sprintf(
                '<info>Package operations: %d install%s, %d update%s, %d removal%s</info>',
                $counters['install'],
                $counters['install'] === 1 ? '' : 's',
                $counters['update'],
                $counters['update'] === 1 ? '' : 's',
                $counters['uninstall'],
                $counters['uninstall'] === 1 ? '' : 's',
            )
        );

        // Get the installation manager and local repository
        $installManager  = $this->composer->getInstallationManager();
        $localRepository = $this->composer->getRepositoryManager()->getLocalRepository();
        $devMode         = $localRepository->getDevMode() ?? true;

        $this->installer->skipInstallation(false);

        // Execute the installation manager to perform the pending operations
        $installManager->execute(
            $localRepository,
            $this->pendingPackages,
            $devMode,
            false
        );

        $this->pendingPackages = [];
    }
}
