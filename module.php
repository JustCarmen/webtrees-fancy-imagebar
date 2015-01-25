<?php
/*
 * Fancy Imagebar Module
 *
 * webtrees: Web based Family History software
 * Copyright (C) 2014 webtrees development team.
 * Copyright (C) 2014 JustCarmen.
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA
 */

use WT\Auth;
use WT\Log;
use WT\Theme;

class fancy_imagebar_WT_Module extends WT_Module implements WT_Module_Config, WT_Module_Menu {

	public function __construct() {
		parent::__construct();
		// Load any local user translations
		if (is_dir(WT_MODULES_DIR . $this->getName() . '/language')) {
			if (file_exists(WT_MODULES_DIR . $this->getName() . '/language/' . WT_LOCALE . '.mo')) {
				WT_I18N::addTranslation(
					new Zend_Translate('gettext', WT_MODULES_DIR . $this->getName() . '/language/' . WT_LOCALE . '.mo', WT_LOCALE)
				);
			}
			if (file_exists(WT_MODULES_DIR . $this->getName() . '/language/' . WT_LOCALE . '.php')) {
				WT_I18N::addTranslation(
					new Zend_Translate('array', WT_MODULES_DIR . $this->getName() . '/language/' . WT_LOCALE . '.php', WT_LOCALE)
				);
			}
			if (file_exists(WT_MODULES_DIR . $this->getName() . '/language/' . WT_LOCALE . '.csv')) {
				WT_I18N::addTranslation(
					new Zend_Translate('csv', WT_MODULES_DIR . $this->getName() . '/language/' . WT_LOCALE . '.csv', WT_LOCALE)
				);
			}
		}
	}

	// Extend WT_Module
	public function getTitle() {
		return /* I18N: Name of the module */ WT_I18N::translate('Fancy Imagebar');
	}

	// Extend WT_Module
	public function getDescription() {
		return /* I18N: Description of the module */ WT_I18N::translate('An imagebar with small images between header and content.');
	}

	// Set default module options
	private function setDefault($key) {
		$FIB_DEFAULT = array(
			'IMAGES'	 => '1', // All images
			'HOMEPAGE'	 => '1',
			'MYPAGE'	 => '1',
			'OTHERPAGES' => '0',
			'RANDOM'	 => '1',
			'TONE'		 => '0',
			'SEPIA'		 => '30',
			'SIZE'		 => '60'
		);
		return $FIB_DEFAULT[$key];
	}

	// Get module options
	private function options($key) {
		$FIB_OPTIONS = unserialize($this->getSetting('FIB_OPTIONS'));

		$key = strtoupper($key);
		$tree = WT_TREE::getIdFromName(WT_Filter::get('ged'));
		if (empty($tree)) {
			$tree = WT_GED_ID;
		}

		if (empty($FIB_OPTIONS[$tree]) || (is_array($FIB_OPTIONS[$tree]) && !array_key_exists($key, $FIB_OPTIONS[$tree]))) {
			return $this->setDefault($key);
		} else {
			return($FIB_OPTIONS[$tree][$key]);
		}
	}

	private function load_json() {
		Zend_Session::writeClose();
		$gedcom_id = WT_TREE::getIdFromName(WT_Filter::get('ged'));
		if (!$gedcom_id) {
			$gedcom_id = WT_GED_ID;
		}
		$start = WT_Filter::getInteger('start');
		$length = WT_Filter::getInteger('length');

		if ($length > 0) {
			$LIMIT = " LIMIT " . $start . ',' . $length;
		} else {
			$LIMIT = "";
		}

		$sql = "SELECT SQL_CACHE SQL_CALC_FOUND_ROWS m_id AS xref, m_file AS gedcom_id FROM `##media` WHERE m_file=? AND m_type=?" . $LIMIT;
		$args = array($gedcom_id, 'photo');

		$rows = WT_DB::prepare($sql)->execute($args)->fetchAll();

		// Total filtered/unfiltered rows
		$recordsTotal = $recordsFiltered = WT_DB::prepare("SELECT FOUND_ROWS()")->fetchOne();

		$data = array();
		foreach ($rows as $row) {
			$media = WT_Media::getInstance($row->xref, $row->gedcom_id);
			if (file_exists($media->getServerFilename()) && ($media->mimeType() == 'image/jpeg' || $media->mimeType() == 'image/png')) {
				$data[] = array(
					$this->displayImage($media)
				);
			}
		}
		header('Content-type: application/json');
		echo json_encode(array(// See http://www.datatables.net/usage/server-side
			'draw'				 => WT_Filter::getInteger('draw'), // String, but always an integer
			'recordsTotal'		 => $recordsTotal,
			'recordsFiltered'	 => $recordsFiltered,
			'data'				 => $data
		));
		exit;
	}

	private function displayImage($media) {
		$image = $this->FancyThumb($media, 60, 60);
		if ($this->options('images') == 1) {
			$img_checked = ' checked="checked"';
		} elseif (is_array($this->options('images')) && in_array($media->getXref(), $this->options('images'))) {
			$img_checked = ' checked="checked"';
		} else {
			$img_checked = "";
		}

		// ouput all thumbs as jpg thumbs (transparent png files are not possible in the Fancy Imagebar, so there is no need to keep the mimeType png).
		ob_start(); imagejpeg($image, null, 100); $newImage = ob_get_clean();
		return '<img src="data:image/jpeg;base64,' . base64_encode($newImage) . '" alt="' . $media->getXref() . '" title="' . strip_tags($media->getFullName()) . '"/>
				<label class="checkbox"><input type="checkbox" value="' . $media->getXref() . '"' . $img_checked . '></label>';
	}

	private function getXrefs() {
		$gedcom_id = WT_TREE::getIdFromName(WT_Filter::get('ged'));
		if (!$gedcom_id) {
			$gedcom_id = WT_GED_ID;
		}
		$sql = "SELECT m_id AS xref, m_file AS gedcom_id FROM `##media` WHERE m_file=? AND m_type=?";
		$args = array($gedcom_id, 'photo');

		$rows = WT_DB::prepare($sql)->execute($args)->fetchAll();
		$list = array();
		foreach ($rows as $row) {
			$list[] = $row->xref;
		}
		return $list;
	}

	// Extend WT_Module_Config
	public function modAction($mod_action) {
		switch ($mod_action) {
			case 'admin_config':
				$this->config();
				break;
			case 'load_json':
				$this->load_json();
				break;
			case 'admin_reset':
				$this->fib_reset();
				$this->config();
				break;
			default:
				header('HTTP/1.0 404 Not Found');
		}
	}

	// Reset all settings to default
	private function fib_reset() {
		WT_DB::prepare("DELETE FROM `##module_setting` WHERE setting_name LIKE 'FIB%'")->execute();
		Log::addConfigurationLog($this->getTitle() . ' reset to default values');
	}

	private function config() {
		require WT_ROOT . 'includes/functions/functions_edit.php';

		$controller = new WT_Controller_Page;
		$controller
			->restrictAccess(Auth::isAdmin())
			->setPageTitle($this->getTitle())
			->pageHeader()
			->addExternalJavascript(WT_JQUERY_DATATABLES_JS_URL)
			->addExternalJavascript(WT_DATATABLES_BOOTSTRAP_JS_URL);

		if (WT_Filter::postBool('save')) {
			$FIB_OPTIONS = unserialize($this->getSetting('FIB_OPTIONS'));
			$tree = WT_Filter::postInteger('NEW_FIB_TREE');
			$FIB_OPTIONS[$tree] = WT_Filter::postArray('NEW_FIB_OPTIONS');
			$FIB_OPTIONS[$tree]['IMAGES'] = explode("|", WT_Filter::post('NEW_FIB_IMAGES'));
			$this->setSetting('FIB_OPTIONS', serialize($FIB_OPTIONS));
			Log::addConfigurationLog($this->getTitle() . ' config updated');
		}

		$controller->addInlineJavascript('
			function include_css(css_file) {
				var html_doc = document.getElementsByTagName("head")[0];
				var css = document.createElement("link");
				css.setAttribute("rel", "stylesheet");
				css.setAttribute("type", "text/css");
				css.setAttribute("href", css_file);
				html_doc.appendChild(css);
			}
			include_css("' . WT_MODULES_DIR . $this->getName() . '/style.css");

			var oTable=jQuery("#image_block").dataTable( {
				dom: \'<"H"pf<"dt-clear">irl>t<"F"pl>\',
				processing: true,
				serverSide: true,
				ajax: "module.php?mod=' . $this->getName() . '&mod_action=load_json",
				' . WT_I18N::datatablesI18N(array(10, 20, 50, 100, 500, 1000, -1)) . ',
				autoWidth: false,
				filter: false,
				pageLength: 10,
				pagingType: "full_numbers",
				stateSave: true,
				cookieDuration: 300,
                                sort: false,
				columns: [
					{}
				],
				fnDrawCallback: function() {
					var images = jQuery("#imagelist").val().split("|");
					jQuery("input[type=checkbox]", this).each(function(){
							if(jQuery.inArray(jQuery(this).val(), images) > -1){
									jQuery(this).prop("checked", true);
							} else {
									jQuery(this).prop("checked", false);
							}
					});
				}
			});

			// dynamic title
			var treeName = jQuery("#tree option:selected").text();
			jQuery("#panel2 .panel-title a").text("' . WT_I18N::translate('Options for') . '" + treeName);

			var formChanged = false;
			jQuery(oTable).on("change", "input[type=checkbox]",function() {
				var images = jQuery("#imagelist").val().split("|")
				if(this.checked){
					if(jQuery.inArray(jQuery(this).val(), images) == -1) {
						images.push(jQuery(this).val());
					}
				 } else {
					 if(jQuery.inArray(jQuery(this).val(), images) > -1){
						var index = images.indexOf(jQuery(this).val());
					 	images.splice(index, 1 );
					 }
				 }
				 jQuery("#imagelist").val(images.join("|"));
				 formChanged = true;
			});

			jQuery("input[name=select-all]").click(function(){
				if (jQuery(this).is(":checked") == true) {
					jQuery("#imagelist").val("' . implode("|", $this->getXrefs()) . '");
					oTable.find(":checkbox").prop("checked", true);
				} else {
					jQuery("#imagelist").val("");
					oTable.find(":checkbox").prop("checked", false);
				}
				formChanged = true;
			});

			// detect changes on other form elements
			jQuery("#panel2").on("change", "input, select", function(){
				formChanged = true;
			});

			var current = jQuery("#tree option:selected");
			jQuery("#tree").change(function() {
				if (formChanged == false || (formChanged == true && confirm("' . WT_I18N::translate('The settings are changed. You will loose your changes if you switch trees.') . '"))) {
					var ged = jQuery("option:selected", this).data("ged");
					var treeName = jQuery("option:selected", this).text();
					jQuery.get("module.php?mod=' . $this->getName() . '&mod_action=admin_config&ged=" + ged, function(data) {
						 jQuery("#imagelist").replaceWith(jQuery(data).find("#imagelist"));
						 jQuery("#options").replaceWith(jQuery(data).find("#options"));
						 jQuery("#panel2 .panel-title a").text("' . WT_I18N::translate('Options for') . '" + treeName);
						 oTable.fnDraw();
					});
					formChanged = false;
					current = jQuery("option:selected", this);
				}
				else {
					jQuery(current).prop("selected", true);
				}
			})

			// extra options for Sepia Tone
			if(jQuery("#tone select").val() == 0) jQuery("#sepia").show();
			else jQuery("#sepia").hide();
			jQuery("#tone select").change(function() {
				if(jQuery(this).val() == 0) jQuery("#sepia").fadeIn(500);
				else jQuery("#sepia").fadeOut(500);
			});
		');
		?>
		
		<ol class="breadcrumb small">
			<li><a href="admin.php"><?php echo WT_I18N::translate('Administration'); ?></a></li>
			<li><a href="admin_modules.php"><?php echo WT_I18N::translate('Module administration'); ?></a></li>
			<li class="active"><?php echo $controller->getPageTitle(); ?></li>
		</ol>
		<h2><?php echo $this->getTitle(); ?></h2>
		<form class="form-horizontal" method="post" name="configform" action="<?php echo $this->getConfigLink(); ?>">
			<input type="hidden" name="save" value="1">
			<div class="form-group">
				<label class="control-label col-sm-1"><?php echo WT_I18N::translate('Family tree'); ?></label>
				<div class="col-sm-3">
					<select id="tree" name="NEW_FIB_TREE" id="NEW_FIB_TREE" class="form-control">
						<?php foreach (WT_Tree::getAll() as $tree): ?>
							<?php if ($tree->tree_id == WT_GED_ID): ?>
								<option value="<?php echo $tree->tree_id; ?>" data-ged="<?php echo $tree->tree_name; ?>" selected="selected">
									<?php echo $tree->tree_title; ?>
								</option>
							<?php else: ?>
								<option value="<?php echo $tree->tree_id; ?>" data-ged="<?php echo $tree->tree_name; ?>">
									<?php echo $tree->tree_title; ?>
								</option>
							<?php endif; ?>
						<?php endforeach; ?>
					</select>
				</div>
			</div>
			<div class="panel-group" id="accordion">
				<div class="panel panel-default" id="panel1">
					<div class="panel-heading">
						<h4 class="panel-title">
							<a data-toggle="collapse" data-target="#collapseOne" href="#collapseOne">
								<?php echo WT_I18N::translate('Choose which images you want to show in the Fancy Imagebar'); ?>
							</a>
						</h4>
					</div>
					<div id="collapseOne" class="panel-collapse collapse in">
						<div class="panel-body">
							<div id="panel-info" class="bg-info">
								<p class="small"><?php echo WT_I18N::translate('Here you can choose which images should be shown in the Fancy Imagebar. Uncheck the images you do not want to show. If there are less images choosen then needed to fill up the entire Fancy Imagebar, the images will be repeated.'); ?></p>
								<p class="small"><?php echo WT_I18N::translate('Note: Only local “jpg” or “png” images which are set as type = “photo” are supported by this module. External images are not supported. It is not possible to keep transparency for png thumbnails in the Fancy Imagebar. Transparent png-thumbnails will get a black background in the Fancy Imagebar. The images shown in this table have the right specifications already.'); ?></p>
								<p class="small"><?php echo WT_I18N::translate('The Fancy Imagebar module respects privacy settings!'); ?></p>
							</div>
							<!-- SELECT ALL -->
							<div class="checkbox">
								<label>
									<?php echo checkbox('select-all') . WT_I18N::translate('select all'); ?>
								</label>
								<?php // The datatable will be dynamically filled with images from the database. ?>
							</div>
							<!-- IMAGE LIST -->
							<h3 id="no-images" class="hidden"><?php echo WT_I18N::translate('No images to display for this tree'); ?></h3>
							<?php $this->options('images') == 1 ? $imagelist = $this->getXrefs() : $imagelist = $this->options('images'); ?>
							<input id="imagelist" type="hidden" name="NEW_FIB_IMAGES" value = "<?php echo implode("|", $imagelist); ?>">
							<table id="image_block" class="table">
								<thead></thead>
								<tbody></tbody>
							</table>
						</div>
					</div>
				</div>
				<div class="panel panel-default" id="panel2">
					<div class="panel-heading">
						<h4 class="panel-title">
							<a data-toggle="collapse" data-target="#collapseTwo" href="#collapseTwo" class="collapsed">
								<!-- Dynamic text here -->
							</a>
						</h4>
					</div>
					<div id="collapseTwo" class="panel-collapse collapse">
						<div id="options" class="panel-body">
							<div class="form-group form-group-sm">
								<label class="control-label col-sm-3">
									<!-- FANCY IMAGEBAR -->
									<?php echo WT_I18N::translate('Show Fancy Imagebar on'); ?>
								</label>
								<div class="checkbox col-sm-8">
									<label>
										<?php echo two_state_checkbox('NEW_FIB_OPTIONS[HOMEPAGE]', $this->options('homepage')) . WT_I18N::translate('Home page'); ?>
									</label>
									<label>' .
										<?php echo two_state_checkbox('NEW_FIB_OPTIONS[MYPAGE]', $this->options('mypage')) . WT_I18N::translate('My page'); ?>
									</label>
									<label>' .
										<?php echo two_state_checkbox('NEW_FIB_OPTIONS[OTHERPAGES]', $this->options('otherpages')) . WT_I18N::translate('Other pages'); ?>
									</label>
								</div>
							</div>
							<!-- RANDOM IMAGES -->
							<div class="form-group form-group-sm">
								<label class="control-label col-sm-3">
									<?php echo WT_I18N::translate('Random images'); ?>
								</label>
								<div class="col-sm-8">
									<?php echo radio_buttons('NEW_FIB_OPTIONS[RANDOM]', array(WT_I18N::translate("no"), WT_I18N::translate("yes")), $this->options('random'), 'class="radio-inline"'); ?>
								</div>
							</div>
							<!-- IMAGE TONE -->
							<div id="tone" class="form-group form-group-sm">
								<label class="control-label col-sm-3">
									<?php echo WT_I18N::translate('Images Tone'); ?>
								</label>
								<div class="col-sm-2">
									<?php echo select_edit_control('NEW_FIB_OPTIONS[TONE]', array('Sepia', 'Black and White', 'Colors'), null, $this->options('tone'), 'class="form-control"'); ?>
								</div>
							</div>
							<!-- SEPIA -->
							<div id="sepia" class="form-group form-group-sm">
								<label class="control-label col-sm-3">
									<?php echo WT_I18N::translate('Amount of sepia'); ?>
								</label>
								<div class="col-sm-2">
									<input
										class="form-control"
										type="text"
										name="NEW_FIB_OPTIONS[SEPIA]"
										size="3"
										value="<?php echo $this->options('sepia'); ?>"
										>
								</div>
								<p class="col-sm-offset-3 col-sm-8 small text-muted">
									<?php echo WT_I18N::translate('Enter a value between 0 and 100'); ?>
								</p>
							</div>
							<!-- CROPPING SIZE -->
							<div class="form-group">
								<label class="control-label col-sm-3">
									<?php echo WT_I18N::translate('Cropped image size'); ?>
								</label>
								<div class="row">
									<div class="col-sm-2">
										<input
											class="form-control"
											type="text"
											name="NEW_FIB_OPTIONS[SIZE]"
											size="3"
											value="<?php echo $this->options('size'); ?>"
											>
									</div>
									<div class="form-control-static">px</div>
								</div>
							</div>
						</div>
					</div>
				</div>
			</div>
			<button class="btn btn-primary" type="submit"><?php echo WT_I18N::translate('Save'); ?></button>
			<button class="btn btn-primary" type="reset" onclick="if (confirm('<?php echo WT_I18N::translate('The settings will be reset to default (for all trees). Are you sure you want to do this?'); ?>'))
						window.location.href = 'module.php?mod=<?php echo $this->getName(); ?>&amp;mod_action=admin_reset';">
					<?php echo WT_I18N::translate('Reset'); ?>
			</button>
		</form>
		<?php
	}

	// Implement WT_Module_Config
	public function getConfigLink() {
		return 'module.php?mod=' . $this->getName() . '&amp;mod_action=admin_config';
	}

	// Get the medialist from the database
	private function FancyImageBarMedia() {
		$sql = "SELECT SQL_CACHE m_id AS xref, m_file AS gedcom_id FROM `##media` WHERE m_file='" . WT_GED_ID . "'";
		if ($this->options('images') == 1) {
			$sql .= " AND m_type='photo'";
		} else {
			// single quotes needed around id's for sql statement.
			foreach ($this->options('images') as $image) {
				$images_sql[] = '\'' . $image . '\'';
			}
			$sql .= " AND m_id IN (" . implode(',', $images_sql) . ")";
		}
		$sql .= $this->options('random') == 1 ? " ORDER BY RAND()" : " ORDER BY m_id DESC";
		$sql .= " LIMIT " . ceil(2400 / $this->options('size'));

		$rows = WT_DB::prepare($sql)->execute()->fetchAll();
		$list = array();
		foreach ($rows as $row) {
			$media = WT_Media::getInstance($row->xref, $row->gedcom_id);
			if ($media->canShow() && ($media->mimeType() == 'image/jpeg' || $media->mimeType() == 'image/png')) {
				$list[] = $media;
			}
		}
		return $list;
	}

	private function FancyThumb($mediaobject, $thumbwidth, $thumbheight) {
		$imgSrc = $mediaobject->getServerFilename();
		$type = $mediaobject->mimeType();

		//getting the image dimensions
		list($width_orig, $height_orig) = @getimagesize($imgSrc);
		switch ($type) {
			case 'image/jpeg':
				$image = @imagecreatefromjpeg($imgSrc);
				break;
			case 'image/png':
				$image = @imagecreatefrompng($imgSrc);
				break;
		}

		$ratio_orig = $width_orig / $height_orig;

		if ($thumbwidth / $thumbheight > $ratio_orig) {
			$new_height = $thumbwidth / $ratio_orig;
			$new_width = $thumbwidth;
		} else {
			$new_width = $thumbheight * $ratio_orig;
			$new_height = $thumbheight;
		}

		// transparent png files are not possible in the Fancy Imagebar, so no extra code needed.
		$new_image = @imagecreatetruecolor(round($new_width), round($new_height));
		@imagecopyresampled($new_image, $image, 0, 0, 0, 0, $new_width, $new_height, $width_orig, $height_orig);

		$thumb = @imagecreatetruecolor($thumbwidth, $thumbheight);
		@imagecopyresampled($thumb, $new_image, 0, 0, 0, 0, $thumbwidth, $thumbheight, $thumbwidth, $thumbheight);

		@imagedestroy($new_image);
		@imagedestroy($image);
		return $thumb;
	}

	private function CreateFancyImageBar($srcImages, $thumbWidth, $thumbHeight, $numberOfThumbs) {
		// defaults
		$pxBetweenThumbs = 0;
		$leftOffSet = $topOffSet = 0;
		$canvasWidth = ($thumbWidth + $pxBetweenThumbs) * $numberOfThumbs;
		$canvasHeight = $thumbHeight;

		// create the FancyImagebar canvas to put the thumbs on
		$FancyImageBar = @imagecreatetruecolor($canvasWidth, $canvasHeight);

		foreach ($srcImages as $index => $thumb) {
			$x = ($index % $numberOfThumbs) * ($thumbWidth + $pxBetweenThumbs) + $leftOffSet;
			$y = floor($index / $numberOfThumbs) * ($thumbWidth + $pxBetweenThumbs) + $topOffSet;

			@imagecopy($FancyImageBar, $thumb, $x, $y, 0, 0, $thumbWidth, $thumbHeight);
		}
		return $FancyImageBar;
	}

	private function FancyImageBarSepia($FancyImageBar, $depth) {
		@imagetruecolortopalette($FancyImageBar, 1, 256);

		for ($c = 0; $c < 256; $c++) {
			$col = @imagecolorsforindex($FancyImageBar, $c);
			$new_col = floor($col['red'] * 0.2125 + $col['green'] * 0.7154 + $col['blue'] * 0.0721);
			if ($depth > 0) {
				$r = $new_col + $depth;
				$g = floor($new_col + $depth / 1.86);
				$b = floor($new_col + $depth / -3.48);
			} else {
				$r = $new_col;
				$g = $new_col;
				$b = $new_col;
			}
			@imagecolorset($FancyImageBar, $c, max(0, min(255, $r)), max(0, min(255, $g)), max(0, min(255, $b)));
		}
		return $FancyImageBar;
	}

	// Extend WT_Module_Menu
	private function GetFancyImageBar() {

		$medialist = $this->FancyImageBarMedia();
		if ($medialist) {
			$width = $height = $this->options('size');

			// begin looping through the media and write the imagebar
			$srcImages = array();
			foreach ($medialist as $media) {
				if (file_exists($media->getServerFilename())) {
					$srcImages[] = $this->FancyThumb($media, $width, $height);
				}
			}

			if (count($srcImages) === 0) {
				return false;
			}

			// be sure the imagebar will be big enough for wider screens
			$newArray = array();

			// determine how many thumbs we need (based on a users screen of 2400px);
			$fib_length = ceil(2400 / $this->options('size'));
			while (count($newArray) <= $fib_length) {
				$newArray = array_merge($newArray, $srcImages);
			}
			// reduce the new array to the desired length (as there might be too many elements in the new array
			$images = array_slice($newArray, 0, $fib_length);
			$FancyImageBar = $this->CreateFancyImageBar($images, $width, $height, $fib_length);
			if ($this->options('tone') == 0) {
				$FancyImageBar = $this->FancyImageBarSepia($FancyImageBar, $this->options('sepia'));
			}
			if ($this->options('tone') == 1) {
				$FancyImageBar = $this->FancyImageBarSepia($FancyImageBar, 0);
			}
			ob_start(); imagejpeg($FancyImageBar, null, 100); $NewFancyImageBar = ob_get_clean();
			$html = '<div id="fancy_imagebar" style="display:none">
						<img alt="fancy_imagebar" src="data:image/jpeg;base64,' . base64_encode($NewFancyImageBar) . '">
					</div>';

			// output
			return $html;
		}
	}

	// Implement WT_Module_Menu
	public function defaultMenuOrder() {
		return 999;
	}

	// Implement WT_Module_Menu
	public function getMenu() {
		// We don't actually have a menu - this is just a convenient "hook" to execute code at the right time during page execution
		global $controller, $ctype, $SEARCH_SPIDER;

		if ($SEARCH_SPIDER) {
			return null;
		}

		if ($this->options('images') !== 0) {

			if ($this->options('otherpages') == 1 || (WT_SCRIPT_NAME === 'index.php' && ($ctype == 'gedcom' && $this->options('homepage') == 1 || ($ctype == 'user' && $this->options('mypage') == 1)))) {

				// add js file to set a few theme depending styles
				$controller->addInlineJavascript('var $theme = "' . Theme::theme()->themeId() . '"', WT_Controller_Base::JS_PRIORITY_HIGH);
				$controller->addExternalJavascript(WT_MODULES_DIR . $this->getName() . '/style.js');

				// put the fancy imagebar in the right position
				echo $this->GetFancyImageBar();
				$controller->addInlineJavaScript('
					jQuery("main").before(jQuery("#fancy_imagebar").show());
				');
			}
		}
		return null;
	}

}
