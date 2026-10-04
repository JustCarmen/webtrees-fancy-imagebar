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
use Composer\Config;
use Composer\Downloader\DownloadManager;
use Composer\IO\IOInterface;
use MagicSunday\Webtrees\Composer\ModuleInstaller;

/**
 * Builds a real module installer whose framework collaborators are stubbed, so the installer's
 * own routing logic runs unmodified while the surrounding Composer machinery stays inert.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/GPL-3.0 GNU General Public License v3.0
 * @link    https://github.com/magicsunday/webtrees-module-installer-plugin/
 */
trait CreatesModuleInstaller
{
    /**
     * Creates a module installer backed by a minimally configured, stubbed Composer instance.
     *
     * @return ModuleInstaller
     */
    private function createModuleInstaller(): ModuleInstaller
    {
        $config = new Config(false);
        $config->merge([
            'config' => [
                'vendor-dir' => 'vendor',
                'bin-dir'    => 'vendor/bin',
            ],
        ]);

        $composer = $this->createStub(Composer::class);
        $composer->method('getConfig')->willReturn($config);
        $composer->method('getDownloadManager')->willReturn($this->createStub(DownloadManager::class));

        return new ModuleInstaller(
            $this->createStub(IOInterface::class),
            $composer,
            ModuleInstaller::PACKAGE_TYPE
        );
    }
}
