<?php

/**
 * This file is part of the package magicsunday/webtrees-module-installer-plugin.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\Webtrees\Composer;

use Composer\Config;
use Composer\Installer\LibraryInstaller;
use Composer\Package\PackageInterface;
use Composer\Repository\InstalledRepositoryInterface;
use Override;
use React\Promise\PromiseInterface;
use ReflectionClass;
use RuntimeException;

use function explode;
use function is_dir;
use function is_string;
use function React\Promise\resolve;
use function rtrim;

/**
 * ModuleInstaller is responsible for handling the installation of packages of type `webtrees-module`
 * and `webtrees-theme`. It integrates with Composer's library installation process and ensures modules
 * and themes are correctly placed into the appropriate path within the `fisharebest/webtrees` directory
 * structure if installed.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/GPL-3.0 GNU General Public License v3.0
 * @link    https://github.com/magicsunday/webtrees-module-installer-plugin/
 */
class ModuleInstaller extends LibraryInstaller
{
    /**
     * The name of the root package.
     */
    public const string ROOT_PACKAGE_NAME = 'fisharebest/webtrees';

    /**
     * The package type used by this installer.
     */
    public const string PACKAGE_TYPE = 'webtrees-module';

    /**
     * The theme package type handled by this installer. Themes are webtrees modules too and
     * therefore install into the same `modules_v4` directory.
     */
    public const string PACKAGE_TYPE_THEME = 'webtrees-theme';

    /**
     * The directory used to install the module into.
     */
    private const string MODULES_DIR = 'modules_v4' . DIRECTORY_SEPARATOR;

    /**
     * Whether to skip the installation process of a package.
     */
    private bool $skipInstall = false;

    /**
     * Determines whether this installer handles packages of the given type.
     *
     * Both `webtrees-module` and `webtrees-theme` packages are handled, as webtrees themes
     * are modules as well and live in the same `modules_v4` directory.
     *
     * @param string $packageType The composer package type to check
     *
     * @return bool True if the package type is handled by this installer
     */
    #[Override]
    public function supports(string $packageType): bool
    {
        return ($packageType === self::PACKAGE_TYPE)
            || ($packageType === self::PACKAGE_TYPE_THEME);
    }

    /**
     * Executes the given operation unless skipInstall is set, in which case resolves immediately with null.
     *
     * @template T
     *
     * @param (callable(): (PromiseInterface<T>|T)) $operation The operation to invoke when installation is not skipped
     *
     * @return PromiseInterface<T>|PromiseInterface<null>|T
     */
    private function maybeSkip(callable $operation)
    {
        return $this->skipInstall ? resolve(null) : $operation();
    }

    /**
     * Delegates the install operation to the parent unless skipInstall is active.
     *
     * @param InstalledRepositoryInterface $repo    The local repository the operation runs against
     * @param PackageInterface             $package The package to install
     *
     * @return PromiseInterface<void|null>|null
     */
    #[Override]
    public function install(InstalledRepositoryInterface $repo, PackageInterface $package): ?PromiseInterface
    {
        return $this->maybeSkip(
            fn () => parent::install(
                $repo,
                $package
            )
        );
    }

    /**
     * Delegates the update operation to the parent unless skipInstall is active.
     *
     * @param InstalledRepositoryInterface $repo    The local repository the operation runs against
     * @param PackageInterface             $initial The currently installed package version
     * @param PackageInterface             $target  The package version to update to
     *
     * @return PromiseInterface<void|null>|null
     */
    #[Override]
    public function update(InstalledRepositoryInterface $repo, PackageInterface $initial, PackageInterface $target): ?PromiseInterface
    {
        return $this->maybeSkip(
            fn () => parent::update(
                $repo,
                $initial,
                $target
            )
        );
    }

    /**
     * Delegates the uninstall operation to the parent unless skipInstall is active.
     *
     * @param InstalledRepositoryInterface $repo    The local repository the operation runs against
     * @param PackageInterface             $package The package to uninstall
     *
     * @return PromiseInterface<void|null>|null
     */
    #[Override]
    public function uninstall(InstalledRepositoryInterface $repo, PackageInterface $package): ?PromiseInterface
    {
        return $this->maybeSkip(
            fn () => parent::uninstall(
                $repo,
                $package
            )
        );
    }

    /**
     * Determines and returns the installation path for a given package.
     *
     * @param PackageInterface $package The package instance for which the installation path is to be determined
     *
     * @return string The resolved installation path for the specified package
     *
     * @throws RuntimeException When the webtrees base path cannot be resolved from either the local
     *                          repository or the configured vendor directory
     */
    #[Override]
    public function getInstallPath(PackageInterface $package): string
    {
        $parts           = explode('/', $package->getPrettyName(), 2);
        $packageBaseName = $parts[1] ?? $parts[0];
        $webtreesPath    = $this->findWebtreesBasePath();

        if ($webtreesPath === null) {
            $vendorDirectory = $this->composer->getConfig()->get('vendor-dir');

            if (is_string($vendorDirectory)) {
                $webtreesPath = $vendorDirectory
                    . DIRECTORY_SEPARATOR . 'fisharebest'
                    . DIRECTORY_SEPARATOR . 'webtrees';
            }
        }

        if ($webtreesPath === null) {
            throw new RuntimeException(
                'Cannot determine webtrees installation path; ensure fisharebest/webtrees is installed.'
            );
        }

        return $webtreesPath
            . DIRECTORY_SEPARATOR . self::MODULES_DIR . $packageBaseName;
    }

    /**
     * Finds and returns the base path of the webtrees installation if available.
     *
     * The method first attempts to locate the installation path of the webtrees package
     * from the Composer local repository. If the webtrees package is found, its
     * installation path is returned. If the webtrees package is set as the root package,
     * then the base directory of the Composer configuration is returned.
     *
     * @return string|null The base path of the webtrees installation if found, or null if it cannot be determined
     */
    public function findWebtreesBasePath(): ?string
    {
        $webtreesPackage = $this->composer
            ->getRepositoryManager()
            ->getLocalRepository()
            ->findPackage(
                self::ROOT_PACKAGE_NAME,
                '*'
            );

        // Return installation path of webtrees package
        if ($webtreesPackage !== null) {
            $path = $this->composer
                ->getInstallationManager()
                ->getInstallPath($webtreesPackage);

            if (is_string($path) && is_dir($path)) {
                return rtrim(
                    $path,
                    DIRECTORY_SEPARATOR
                );
            }
        }

        // If webtrees exists as root package, return the base directory
        if ($this->composer->getPackage()->getName() === self::ROOT_PACKAGE_NAME) {
            $path = $this->getComposerBaseDir($this->composer->getConfig());

            if (is_string($path) && is_dir($path)) {
                return rtrim(
                    $path,
                    DIRECTORY_SEPARATOR
                );
            }
        }

        return null;
    }

    /**
     * Retrieves the base directory of the Composer configuration.
     *
     * @param Config $config The Composer configuration instance containing the base directory information
     *
     * @return string|null The base directory of the Composer setup if available, or null if it could not be determined
     */
    private function getComposerBaseDir(Config $config): ?string
    {
        $reflectionClass    = new ReflectionClass($config);
        $reflectionProperty = $reflectionClass->getProperty('baseDir');

        $value = $reflectionProperty->getValue($config);

        if (is_string($value)) {
            return $value;
        }

        return null;
    }

    /**
     * Sets whether the installation process should be skipped.
     *
     * @param bool $skip A boolean value indicating whether to skip the installation (true to skip, false otherwise)
     *
     * @return void
     */
    public function skipInstallation(bool $skip): void
    {
        $this->skipInstall = $skip;
    }
}
