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
use Fisharebest\Webtrees\Bootstrap4;
use Fisharebest\Webtrees\Controller\PageController;
use Fisharebest\Webtrees\Filter;
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
        ->addExternalJavascript(WT_DATATABLES_BOOTSTRAP_JS_URL)
        ->addExternalJavascript(WT_DATATABLES_BOOTSTRAP_JS_URL);

    echo $this->includeCss();

    $controller->addInlineJavascript('
			var oTable=$("#image_block").dataTable( {
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
					var images = $("#imagelist").val().split("|");
					$("input[type=checkbox]", this).each(function(){
							if($.inArray($(this).val(), images) > -1){
									$(this).prop("checked", true);
							} else {
									$(this).prop("checked", false);
							}
					});
				}
			});

			// dynamic title
			var treeName = $("#tree option:selected").text();
			$("#card-options-header a").text("' . I18N::translate('Options for') . ' " + treeName);

			var formChanged = false;
			$(oTable).on("change", "input[type=checkbox]",function() {
				var images = $("#imagelist").val().split("|")
				if(this.checked){
					images.push($(this).val());
				 } else {
					var index = images.indexOf($(this).val());
					images.splice(index, 1 );
				 }
				 
				 // remove empty values from array
				 images = images.filter(function(e){return e});
				 
				 // turn array into a string
				 $("#imagelist").val(images.join("|"));
				 	 
				 formChanged = true;
			});

			$("input[name=select-all]").click(function(){
				if ($(this).is(":checked") == true) {
					$("#imagelist").val("' . implode("|", $this->getXrefs()) . '");
					oTable.find(":checkbox").prop("checked", true);
				} else {
					$("#imagelist").val("");
					oTable.find(":checkbox").prop("checked", false);
				}
				formChanged = true;
			});

			// detect changes on other form elements
			$("#card-options-content").on("change", "input, select", function(){
				formChanged = true;
			});
			
			function getImageList() {
				var ged = $("option:selected", "#tree").val();
				var folder = $("option:selected", "#folderlist").val();
				var photos = $("#photos").is(":checked")
				return "module.php?mod=' . $this->getName() . '&mod_action=admin_config&ged=" + ged + "&folder=" + folder + "&photos=" + photos;
			}

			var current = $("#tree option:selected");
			$("#tree").change(function() {
				if (formChanged == false || (formChanged == true && confirm("' . I18N::translate('The settings are changed. You will lose your changes if you switch trees.') . '"))) {					
					var treeName = $("option:selected", this).text();
					$.get(getImageList(), function(data) {
						 $("#folderlist").replaceWith($(data).find("#folderlist"));
						 $("#imagelist").replaceWith($(data).find("#imagelist"));
						 $("#options").replaceWith($(data).find("#options"));
						 $("#card-options-header a").text("' . I18N::translate('Options for') . ' " + treeName);
						 oTable.fnDraw();
					});
					formChanged = false;
					current = $("option:selected", this);
				}
				else {
					$(current).prop("selected", true);
				}
			})

			// folder select
			$("form").on("change", "#folderlist", function(){
				$.get(getImageList(), function(data) {
					 $("#folderlist").replaceWith($(data).find("#folderlist"));
					 $("#imagelist").replaceWith($(data).find("#imagelist"));
					 formChanged = false;
					 oTable.fnDraw();
				});
			});

			// select files with or without type = "photo"
			$("form").on("click", "#photos", function(){
				$.get(getImageList(), function(data) {
					 $("#imagelist").replaceWith($(data).find("#imagelist"));
					 formChanged = false;
					 oTable.fnDraw();
				});
			});

			// extra options for Sepia Tone
			if($("#tone select").val() == 0) {
				$("#sepia").show();
			} else {
				$("#sepia").hide();
			}
			
			$("#tone select").change(function() {
				if($(this).val() == 0) {
					$("#sepia").fadeIn(500);
				} else {
					$("#sepia").fadeOut(500);
				}
			});
		');
  }

  private function pageBody(PageController $controller) {
    global $WT_TREE;

    echo Bootstrap4::breadcrumbs([
        'admin.php'         => I18N::translate('Control panel'),
        'admin_modules.php' => I18N::translate('Module administration'),
        ], $controller->getPageTitle());
    ?>

    <h1><?= $controller->getPageTitle() ?></h1>
    <form class="form-horizontal" method="post" name="configform" action="<?= $this->getConfigLink() ?>">
      <?= Filter::getCsrf() ?>
      <input type="hidden" name="save" value="1">
      <div class="row form-group mt-3">
        <label class="col-form-label col-sm-1"><?= I18N::translate('Family tree') ?></label>
        <div class="col-sm-4">
          <?= Bootstrap4::select(Tree::getNameList(), $WT_TREE->getName(), ['id' => 'tree', 'name' => 'NEW_FIB_TREE']) ?>
        </div>
      </div>
      <div id="accordion" role="tablist" aria-multiselectable="true">
        <div class="card">
          <div class="card-header" role="tab" id="card-imagelist-header">
            <h5 class="mb-0">
              <a data-toggle="collapse" data-parent="#accordion" href="#card-imagelist-content" aria-expanded="true" aria-controls="card-imagelist-content">
                <?= I18N::translate('Choose which images you want to show in the Fancy Imagebar') ?>
              </a>
            </h5>
          </div>
          <div id="card-imagelist-content" class="collapse show" role="tabpanel" aria-labelledby="card-imagelist-header">
            <div class="card-block">
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
              <div id="medialist" class="row form-group">
                <label class="col-form-label col-sm-2">
                  <?= I18N::translate('Media folder') ?>
                </label>
                <div class="col-sm-4">
                  <?= Bootstrap4::select($folders, $this->options('image_folder'), ['id' => 'folderlist', 'name' => 'NEW_FIB_OPTIONS[IMAGE_FOLDER]']) ?>
                  <?= Bootstrap4::checkbox(I18N::translate('Only show images with type = “photo”'), false, ['id' => 'photos', 'name' => 'NEW_FIB_OPTIONS[PHOTOS]', 'checked' => $this->options('photos')]) ?>
                </div>
                <!-- SELECT ALL -->
                <div class="col-sm-6 text-right">
                  <?= Bootstrap4::checkbox(I18N::translate('select all'), true, ['name' => 'select-all']) ?>
                </div>
              </div>
              <?php // The datatable will be dynamically filled with images from the database.   ?>
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
              <table id="image_block" class="table table-sm">
                <thead></thead>
                <tbody></tbody>
              </table>
            </div>
          </div>
        </div>
        <div class="card">
          <div class="card-header" role="tab" id="card-options-header">
            <h5 class="mb-0">
              <a data-toggle="collapse" data-parent="#accordion" href="#card-options-content" aria-expanded="true" aria-controls="card-options-content">
                <!-- Dynamic text here -->
              </a>
            </h5>
          </div>
          <div id="card-options-content" class="collapse" role="tabpanel" aria-labelledby="card-options-header">
            <div id="options" class="card-block">
              <!-- FANCY IMAGEBAR -->
              <div class="row form-group">
                <label class="col-form-label col-sm-4">
                  <?= I18N::translate('Show Fancy Imagebar on') ?>
                </label>
                <div class="col-sm-8">
                  <?= Bootstrap4::checkbox(I18N::translate('Home page'), true, ['name' => 'NEW_FIB_OPTIONS[HOMEPAGE]', 'checked' => (bool) $this->options('homepage')]) ?>
                  <?= Bootstrap4::checkbox(I18N::translate('My page'), true, ['name' => 'NEW_FIB_OPTIONS[MYPAGE]', 'checked' => (bool) $this->options('mypage')]) ?>
                  <?= Bootstrap4::checkbox(I18N::translate('All pages'), true, ['name' => 'NEW_FIB_OPTIONS[ALLPAGES]', 'checked' => (bool) $this->options('allpages')]) ?>
                </div>
              </div>
              <!-- RANDOM IMAGES -->
              <div class="row form-group">
                <label class="col-form-label col-sm-4">
                  <?= I18N::translate('Random images') ?>
                </label>
                <div class="col-sm-8">
                  <?= Bootstrap4::radioButtons('NEW_FIB_OPTIONS[RANDOM]', FunctionsEdit::optionsNoYes(), $this->options('random'), true) ?>
                </div>
              </div>
              <!-- IMAGE TONE -->
              <div id="tone" class="row form-group">
                <label class="col-form-label col-sm-4">
                  <?= I18N::translate('Images Tone') ?>
                </label>
                <div class="col-sm-2">
                  <?= Bootstrap4::select(['Sepia', 'Black and White', 'Colors'], $this->options('tone'), ['name' => 'NEW_FIB_OPTIONS[TONE]']) ?>
                </div>
              </div>
              <!-- SEPIA -->
              <div id="sepia" class="row form-group">
                <label class="col-form-label col-sm-4">
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
                <p class="offset-sm-3 col-sm-8 small text-muted">
                  <?= I18N::translate('Enter a value between 0 and 100') ?>
                </p>
              </div>
              <!-- HEIGHT OF THE IMAGE BAR -->
              <div class="row form-group">
                <label class="col-form-label col-sm-4">
                  <?= I18N::translate('Height of the Fancy Imagebar') ?>
                </label>
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
              <!-- CROP THUMBNAILS TO SQUARE -->
              <div class="row form-group">
                <label class="col-form-label col-sm-4">
                  <?= I18N::translate('Use square thumbs') ?>
                </label>
                <div class="col-sm-8">
                  <?= Bootstrap4::radioButtons('NEW_FIB_OPTIONS[SQUARE]', FunctionsEdit::optionsNoYes(), $this->options('square'), true) ?>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
      <div class="mt-3">
        <button class="btn btn-primary" type="submit">
          <i class="fa fa-check"></i>
          <?= I18N::translate('save') ?>
        </button>
        <button class="btn btn-primary" type="reset" onclick="if (confirm('<?= I18N::translate('The settings will be reset to default (for all trees). Are you sure you want to do this?') ?>'))
              window.location.href = 'module.php?mod=<?= $this->getName() ?>&amp;mod_action=admin_reset';">
          <i class="fa fa-recycle"></i>
          <?= I18N::translate('reset') ?>
        </button>
      </div>
    </form>
    <?php
  }

}
