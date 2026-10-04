<?php

/**
 * This file is part of the package magicsunday/webtrees-module-installer-plugin.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\Webtrees\Test\Composer;

use MagicSunday\Webtrees\Composer\ModuleInstaller;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Tests the package-type routing of the module installer.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/GPL-3.0 GNU General Public License v3.0
 * @link    https://github.com/magicsunday/webtrees-module-installer-plugin/
 */
#[CoversClass(ModuleInstaller::class)]
class ModuleInstallerTest extends TestCase
{
    use CreatesModuleInstaller;

    /**
     * The installer must keep handling the classic webtrees-module package type.
     *
     * @return void
     */
    #[Test]
    public function supportsModulePackageType(): void
    {
        self::assertTrue($this->createModuleInstaller()->supports('webtrees-module'));
    }

    /**
     * The installer must also handle the webtrees-theme package type so themes
     * land in the modules_v4 directory just like ordinary modules.
     *
     * @return void
     */
    #[Test]
    public function supportsThemePackageType(): void
    {
        self::assertTrue($this->createModuleInstaller()->supports('webtrees-theme'));
    }

    /**
     * Unrelated package types must not be claimed by the installer.
     *
     * @return void
     */
    #[Test]
    public function doesNotSupportUnrelatedPackageType(): void
    {
        self::assertFalse($this->createModuleInstaller()->supports('library'));
    }
}
