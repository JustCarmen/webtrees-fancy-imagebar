<?php
/**
 * webtrees: online genealogy
 * Copyright (C) 2015 webtrees development team
 * Copyright (C) 2015 JustCarmen
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */
namespace JustCarmen\WebtreesAddOns\FancyImagebar;

use Composer\Autoload\ClassLoader;
use Fisharebest\Webtrees\Auth;
use Fisharebest\Webtrees\Controller\BaseController;
use Fisharebest\Webtrees\Database;
use Fisharebest\Webtrees\Filter;
use Fisharebest\Webtrees\I18N;
use Fisharebest\Webtrees\Log;
use Fisharebest\Webtrees\Module\AbstractModule;
use Fisharebest\Webtrees\Module\ModuleConfigInterface;
use Fisharebest\Webtrees\Module\ModuleMenuInterface;
use Fisharebest\Webtrees\Theme;
use JustCarmen\WebtreesAddOns\FancyImagebar\Template\AdminTemplate;

class FancyImagebarModule extends AbstractModule implements ModuleConfigInterface, ModuleMenuInterface {

	/** @var string location of the Fancy imagebar module files */
	var $directory;

	public function __construct() {
		parent::__construct('fancy_imagebar');

		$this->directory = WT_MODULES_DIR . $this->getName();

		// register the namespaces
		$loader = new ClassLoader();
		$loader->addPsr4('JustCarmen\\WebtreesAddOns\\FancyImagebar\\', $this->directory . '/src');
		$loader->register();
	}

	/**
	 * Get the module class.
	 * 
	 * Class functions are called with $this inside the source directory.
	 */
	private function module() {
		return new FancyImagebarClass;
	}

	// Extend Module
	public function getTitle() {
		return /* I18N: Name of the module */ I18N::translate('Fancy Imagebar');
	}

	// Extend Module
	public function getDescription() {
		return /* I18N: Description of the module */ I18N::translate('An imagebar with small images between header and content.');
	}

	// Extend ModuleConfigInterface
	public function modAction($mod_action) {
		switch ($mod_action) {
			case 'admin_config':
				if (Filter::postBool('save')) {
					$FIB_OPTIONS = unserialize($this->getSetting('FIB_OPTIONS'));
					$tree = Filter::postInteger('NEW_FIB_TREE');
					$FIB_OPTIONS[$tree] = Filter::postArray('NEW_FIB_OPTIONS');
					$FIB_OPTIONS[$tree]['IMAGES'] = explode("|", Filter::post('NEW_FIB_IMAGES'));
					$this->setSetting('FIB_OPTIONS', serialize($FIB_OPTIONS));
					Log::addConfigurationLog($this->getTitle() . ' config updated');
				}
				$template = new AdminTemplate;
				return $template->pageContent();
			case 'load_json':
				return $this->module()->loadJson();
			case 'admin_reset':
				Database::prepare("DELETE FROM `##module_setting` WHERE setting_name LIKE 'FIB%'")->execute();
				Log::addConfigurationLog($this->getTitle() . ' reset to default values');
				$template = new AdminTemplate;
				return $template->pageContent();
			default:
				http_response_code(404);
				break;
		}
	}

	// Implement ModuleConfigInterface
	public function getConfigLink() {
		return 'module.php?mod=' . $this->getName() . '&amp;mod_action=admin_config';
	}

	// Implement ModuleMenuInterface
	public function defaultMenuOrder() {
		return 999;
	}

	// Implement ModuleMenuInterface
	public function getMenu() {
		// We don't actually have a menu - this is just a convenient "hook" to execute code at the right time during page execution
		global $controller, $ctype;

		if (!Auth::isSearchEngine() && $this->module()->options('images') !== 0 && Theme::theme()->themeId() !== '_administration') {

			if ($this->module()->options('allpages') == 1 || (WT_SCRIPT_NAME === 'index.php' && ($ctype == 'gedcom' && $this->module()->options('homepage') == 1 || ($ctype == 'user' && $this->module()->options('mypage') == 1)))) {

				// add js file to set a few theme depending styles
				$parentclass = get_parent_class(Theme::theme());
				if (basename($parentclass) === 'AbstractTheme') {
					$theme = Theme::theme()->themeId();
				} else {
					$parenttheme = new $parentclass;
					$theme = $parenttheme->themeId();
				}
				
				$controller->addInlineJavascript('var $theme = "' . $theme . '"', BaseController::JS_PRIORITY_HIGH);
				$controller->addExternalJavascript($this->directory . '/js/style.js');

				// put the fancy imagebar in the right position
				echo $this->module()->getFancyImagebar();
				$controller->addInlineJavaScript('
					jQuery("main").before(jQuery("#fancy_imagebar").show());
				');
			}
		}
		return null;
	}

}

return new FancyImagebarModule;
