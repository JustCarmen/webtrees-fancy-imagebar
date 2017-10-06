<?php
/**
 * webtrees: online genealogy
 * Copyright (C) 2018 JustCarmen (http://www.justcarmen.nl)
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
use Fisharebest\Webtrees\Database;
use Fisharebest\Webtrees\Filter;
use Fisharebest\Webtrees\I18N;
use Fisharebest\Webtrees\Log;
use Fisharebest\Webtrees\Module\AbstractModule;
use Fisharebest\Webtrees\Module\ModuleConfigInterface;
use Fisharebest\Webtrees\Module\ModuleMenuInterface;
use Fisharebest\Webtrees\Theme;
use Fisharebest\Webtrees\Tree;
use JustCarmen\WebtreesAddOns\FancyImagebar\Template\AdminTemplate;

class FancyImagebarModule extends AbstractModule implements ModuleConfigInterface, ModuleMenuInterface {

  const CUSTOM_VERSION = '2.0.0-dev';
  const CUSTOM_WEBSITE = 'http://www.justcarmen.nl/fancy-modules/fancy-imagebar/';

  /** @var string location of the Fancy imagebar module files */
  var $directory;

  public function __construct() {
    parent::__construct('fancy_imagebar');

    $this->directory = WT_MODULES_DIR . $this->getName();

    // register the namespaces
    $loader = new ClassLoader();
    $loader->addPsr4('JustCarmen\\WebtreesAddOns\\FancyImagebar\\', $this->directory . '/app');
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
        if (Filter::postBool('save') && Filter::checkCsrf()) {
          $FIB_OPTIONS           = unserialize($this->getPreference('FIB_OPTIONS'));
          $tree                  = Tree::findByName(Filter::post('NEW_FIB_TREE'));
          $tree_id               = $tree->getTreeId();
          $FIB_OPTIONS[$tree_id] = Filter::postArray('NEW_FIB_OPTIONS');

          // unchecked checkboxes have not been posted
          if (!array_key_exists('HOMEPAGE', $FIB_OPTIONS[$tree_id])) {
            $FIB_OPTIONS[$tree_id]['HOMEPAGE'] = 0;
          }
          if (!array_key_exists('MYPAGE', $FIB_OPTIONS[$tree_id])) {
            $FIB_OPTIONS[$tree_id]['MYPAGE'] = 0;
          }
          if (!array_key_exists('ALLPAGES', $FIB_OPTIONS[$tree_id])) {
            $FIB_OPTIONS[$tree_id]['ALLPAGES'] = 0;
          }

          $images                          = explode("|", Filter::post('NEW_FIB_IMAGES'));
          $FIB_OPTIONS[$tree_id]['IMAGES'] = $images;
          $this->setPreference('FIB_OPTIONS', serialize($FIB_OPTIONS));

          if ($images) {
            // now (re)create the image cache
            $this->module()->createCache();
          } else {
            // remove previously cached images
            $this->module()->emptyCache();
          }

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
    global $controller;

    echo $this->includeCss();

    // Check if the Fancy Imagebar is implemented in a (custom) theme
    // Quicker loading through theme. Include styling.
    if (method_exists(Theme::theme(), 'fancyImagebar')) {
      return null;
    }

    try {
      if ($this->module()->loadFancyImagebar()) {
        // put the fancy imagebar in the right position
        echo $this->module()->getFancyImagebar();
        $controller->addInlineJavaScript('
					$("main").before($(".fancy-imagebar").show());
				');
      }
    } catch (\ErrorException $ex) {
      Log::addErrorLog('Fancy Imagebar: ' . $ex->getMessage());
    }

    return null;
  }

  /**
   * Default Fancy script to load a module stylesheet
   *
   * The code to place the stylesheet in the header renders quicker than the default webtrees solution
   * because we do not have to wait until the page is fully loaded
   *
   * @return javascript
   */
  protected function includeCss() {
    return
        '<script>
          var newSheet=document.createElement("link");
          newSheet.setAttribute("rel","stylesheet");
          newSheet.setAttribute("type","text/css");
          newSheet.setAttribute("href","' . $this->directory . '/css/style.css");
          document.getElementsByTagName("head")[0].appendChild(newSheet);
        </script>';
  }

}

return new FancyImagebarModule;
