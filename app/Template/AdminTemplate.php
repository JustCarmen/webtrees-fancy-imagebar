<?php
/*
 * webtrees: online genealogy
 * Copyright (C) 2018 JustCarmen (http://www.justcarmen.nl)
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */
namespace JustCarmen\WebtreesAddOns\FancyImagebar\Template;

use Fisharebest\Webtrees\Auth;
use Fisharebest\Webtrees\Controller\PageController;
use Fisharebest\Webtrees\Functions\FunctionsEdit;
use Fisharebest\Webtrees\I18N;
use Fisharebest\Webtrees\Tree;
use JustCarmen\WebtreesAddOns\FancyImagebar\FancyImagebarClass;

class AdminTemplate extends FancyImagebarClass {

	protected function pageContent() {
		$controller = new PageController;
		return
			$this->pageHeader($controller) .
			$this->pageBody($controller);
	}

	private function pageHeader(PageController $controller) {
		$controller
			->restrictAccess(Auth::isAdmin())
			->setPageTitle($this->getTitle())
			->pageHeader()
			->addExternalJavascript(WT_JQUERY_DATATABLES_JS_URL)
			->addExternalJavascript(WT_DATATABLES_BOOTSTRAP_JS_URL);

		$controller->addInlineJavascript('
			function include_css(css_file) {
				var html_doc = document.getElementsByTagName("head")[0];
				var css = document.createElement("link");
				css.setAttribute("rel", "stylesheet");
				css.setAttribute("type", "text/css");
				css.setAttribute("href", css_file);
				html_doc.appendChild(css);
			}
			include_css("' . $this->directory . '/css/style.css");

			var oTable=jQuery("#image_block").dataTable( {
				dom: \'<p<"dt-clear">il>t<r>\',
				processing: true,
				serverSide: true,
				ajax: "module.php?mod=' . $this->getName() . '&mod_action=load_json",
				' . I18N::datatablesI18N([10, 20, 50, 100, 500, 1000, -1]) . ',
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
			jQuery("#panel2 .panel-title a").text("' . I18N::translate('Options for') . '" + treeName);

			var formChanged = false;
			jQuery(oTable).on("change", "input[type=checkbox]",function() {
				var images = jQuery("#imagelist").val().split("|")
				if(this.checked){
					images.push(jQuery(this).val());
				 } else {
					var index = images.indexOf(jQuery(this).val());
					images.splice(index, 1 );
				 }
				 
				 // remove empty values from array
				 images = images.filter(function(e){return e});
				 
				 // turn array into a string
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
			
			function getImageList() {
				var ged = jQuery("option:selected", "#tree").data("ged");
				var folder = jQuery("option:selected", ".folderlist").val();
				var photos = jQuery(".photos").is(":checked")
				return "module.php?mod=' . $this->getName() . '&mod_action=admin_config&ged=" + ged + "&folder=" + folder + "&photos=" + photos;
			}

			var current = jQuery("#tree option:selected");
			jQuery("#tree").change(function() {
				if (formChanged == false || (formChanged == true && confirm("' . I18N::translate('The settings are changed. You will lose your changes if you switch trees.') . '"))) {					
					var treeName = jQuery("option:selected", this).text();
					jQuery.get(getImageList(), function(data) {
						 jQuery(".folderlist").replaceWith(jQuery(data).find(".folderlist"));
						 jQuery("#imagelist").replaceWith(jQuery(data).find("#imagelist"));
						 jQuery("#options").replaceWith(jQuery(data).find("#options"));
						 jQuery("#panel2 .panel-title a").text("' . I18N::translate('Options for') . '" + treeName);
						 oTable.fnDraw();
					});
					formChanged = false;
					current = jQuery("option:selected", this);
				}
				else {
					jQuery(current).prop("selected", true);
				}
			})

			// folder select
			jQuery("form").on("change", ".folderlist", function(){
				jQuery.get(getImageList(), function(data) {
					 jQuery(".folderlist").replaceWith(jQuery(data).find(".folderlist"));
					 jQuery("#imagelist").replaceWith(jQuery(data).find("#imagelist"));
					 formChanged = false;
					 oTable.fnDraw();
				});
			});

			// select files with or without type = "photo"
			jQuery("form").on("click", ".photos", function(){
				jQuery.get(getImageList(), function(data) {
					 jQuery("#imagelist").replaceWith(jQuery(data).find("#imagelist"));
					 formChanged = false;
					 oTable.fnDraw();
				});
			});

			// extra options for Sepia Tone
			if(jQuery("#tone select").val() == 0) {
				jQuery("#sepia").show();
			} else {
				jQuery("#sepia").hide();
			}
			
			jQuery("#tone select").change(function() {
				if(jQuery(this).val() == 0) {
					jQuery("#sepia").fadeIn(500);
				} else {
					jQuery("#sepia").fadeOut(500);
				}
			});
		');
	}

	private function pageBody(PageController $controller) {
		?>
		<ol class="breadcrumb small">
			<li><a href="admin.php"><?= I18N::translate('Control panel') ?></a></li>
			<li><a href="admin_modules.php"><?= I18N::translate('Module administration') ?></a></li>
			<li class="active"><?= $controller->getPageTitle() ?></li>
		</ol>
		<h2><?= $this->getTitle() ?></h2>
		<form class="form-horizontal" method="post" name="configform" action="<?= $this->getConfigLink() ?>">
			<input type="hidden" name="save" value="1">
			<div class="form-group">
				<label class="control-label col-sm-1"><?= I18N::translate('Family tree') ?></label>
				<div class="col-sm-3">
					<select id="tree" name="NEW_FIB_TREE" class="form-control">
						<?php foreach (Tree::getAll() as $tree): ?>
							<?php if ($tree->getTreeId() === $this->getTreeId()): ?>
								<option value="<?= $tree->getTreeId(); ?>" data-ged="<?= $tree->getNameHtml() ?>" selected="selected">
									<?= $tree->getTitleHtml() ?>
								</option>
							<?php else: ?>
								<option value="<?= $tree->getTreeId(); ?>" data-ged="<?= $tree->getNameHtml() ?>">
									<?= $tree->getTitleHtml() ?>
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
								<?= I18N::translate('Choose which images you want to show in the Fancy Imagebar') ?>
							</a>
						</h4>
					</div>
					<div id="collapseOne" class="panel-collapse collapse in">
						<div class="panel-body">
							<div class="alert alert-info alert-dismissible" role="alert">
								<button type="button" class="close" data-dismiss="alert" aria-label="' . I18N::translate('close') . '">
									<span aria-hidden="true">&times;</span>
								</button>
								<p class="small"><?= I18N::translate('Here you can choose which images should be shown in the Fancy Imagebar. If there are fewer images chosen than needed to fill up the entire Fancy Imagebar, the images will be repeated.') ?></p>
								<p class="small"><?= I18N::translate('Note: Only local “jpg” or “png” images are supported by this module. External images are not supported. It is not possible to keep transparency for png thumbnails in the Fancy Imagebar. Transparent png-thumbnails will get a black background in the Fancy Imagebar. The images shown in this table have the right specifications already.') ?></p>
								<p class="small"><?= I18N::translate('The Fancy Imagebar module respects privacy settings!') ?></p>
							</div>
							<!-- MEDIA LIST -->
							<?php $folders = $this->listMediaFolders(); ?>
							<div id="medialist" class="form-group">
								<label class="control-label col-sm-1">
									<?= I18N::translate('Media folder') ?>
								</label>
								<div class="col-sm-3">
									<?= FunctionsEdit::selectEditControl('NEW_FIB_OPTIONS[IMAGE_FOLDER]', $folders, null, $this->options('image_folder'), 'class="folderlist form-control"') ?>
								</div>
								<label class="checkbox-inline">
									<?= FunctionsEdit::twoStateCheckbox('NEW_FIB_OPTIONS[PHOTOS]', $this->options('photos'), 'class="photos"') . I18N::translate('Only show images with type = “photo”') ?>
								</label>
							</div>
							<!-- SELECT ALL -->
							<label class="checkbox-inline">
								<?= FunctionsEdit::checkbox('select-all') . I18N::translate('select all') ?>
							</label>
							<?php // The datatable will be dynamically filled with images from the database.  ?>
							<!-- IMAGE LIST -->
							<?php
							if (empty($this->options('images'))) {
								// we have not used the configuration page yet so use the default (list all images)
								$imagelist = implode("|", $this->getXrefs());
							} else {
								$imagelist = implode("|", $this->options('images'));
							}
							?>
							<input id="imagelist" type="hidden" name="NEW_FIB_IMAGES" value = "<?= $imagelist ?>">
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
									<?= I18N::translate('Show Fancy Imagebar on') ?>
								</label>
								<div class="checkbox col-sm-8">
									<label>
										<?= FunctionsEdit::twoStateCheckbox('NEW_FIB_OPTIONS[HOMEPAGE]', $this->options('homepage')) . I18N::translate('Home page') ?>
									</label>
									<label>
										<?= FunctionsEdit::twoStateCheckbox('NEW_FIB_OPTIONS[MYPAGE]', $this->options('mypage')) . I18N::translate('My page') ?>
									</label>
									<label>
										<?= FunctionsEdit::twoStateCheckbox('NEW_FIB_OPTIONS[ALLPAGES]', $this->options('allpages')) . I18N::translate('All pages') ?>
									</label>
								</div>
							</div>
							<!-- RANDOM IMAGES -->
							<div class="form-group form-group-sm">
								<label class="control-label col-sm-3">
									<?= I18N::translate('Random images') ?>
								</label>
								<div class="col-sm-8">
									<?= FunctionsEdit::editFieldYesNo('NEW_FIB_OPTIONS[RANDOM]', $this->options('random'), 'class="radio-inline"') ?>
								</div>
							</div>
							<!-- IMAGE TONE -->
							<div id="tone" class="form-group form-group-sm">
								<label class="control-label col-sm-3">
									<?= I18N::translate('Images Tone') ?>
								</label>
								<div class="col-sm-2">
									<?= FunctionsEdit::selectEditControl('NEW_FIB_OPTIONS[TONE]', ['Sepia', 'Black and White', 'Colors'], null, $this->options('tone'), 'class="form-control"') ?>
								</div>
							</div>
							<!-- SEPIA -->
							<div id="sepia" class="form-group form-group-sm">
								<label class="control-label col-sm-3">
									<?= I18N::translate('Amount of sepia') ?>
								</label>
								<div class="col-sm-2">
									<input
										class="form-control"
										type="text"
										name="NEW_FIB_OPTIONS[SEPIA]"
										size="3"
										value="<?= $this->options('sepia') ?>"
										>
								</div>
								<p class="col-sm-offset-3 col-sm-8 small text-muted">
									<?= I18N::translate('Enter a value between 0 and 100') ?>
								</p>
							</div>
							<!-- HEIGHT OF THE IMAGE BAR -->
							<div class="form-group form-group-sm">
								<label class="control-label col-sm-3">
									<?= I18N::translate('Height of the Fancy Imagebar') ?>
								</label>
								<div class="row">
									<div class="col-sm-2">
										<input
											class="form-control"
											type="text"
											name="NEW_FIB_OPTIONS[HEIGHT]"
											size="3"
											value="<?= $this->options('height') ?>"
											>
									</div>
									<div class="form-control-static">px</div>
								</div>
							</div>
							<!-- CROP THUMBNAILS TO SQUARE -->
							<div class="form-group form-group-sm">
								<label class="control-label col-sm-3">
									<?= I18N::translate('Use square thumbs') ?>
								</label>
								<div class="col-sm-8">
									<?= FunctionsEdit::editFieldYesNo('NEW_FIB_OPTIONS[SQUARE]', $this->options('square'), 'class="radio-inline"') ?>
								</div>
							</div>
						</div>
					</div>
				</div>
			</div>
			<button class="btn btn-primary" type="submit">
				<i class="fa fa-check"></i>
				<?= I18N::translate('save') ?>
			</button>
			<button class="btn btn-primary" type="reset" onclick="if (confirm('<?= I18N::translate('The settings will be reset to default (for all trees). Are you sure you want to do this?') ?>'))
						window.location.href = 'module.php?mod=<?= $this->getName() ?>&amp;mod_action=admin_reset';">
				<i class="fa fa-recycle"></i>
				<?= I18N::translate('reset') ?>
			</button>
		</form>
		<?php
	}

}
