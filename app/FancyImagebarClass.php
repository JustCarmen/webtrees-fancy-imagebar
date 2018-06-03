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
namespace JustCarmen\WebtreesAddOns\FancyImagebar;

use Fisharebest\Webtrees\Database;
use Fisharebest\Webtrees\File;
use Fisharebest\Webtrees\Filter;
use Fisharebest\Webtrees\I18N;
use Fisharebest\Webtrees\Media;
use Fisharebest\Webtrees\MediaFile;
use Fisharebest\Webtrees\Query\QueryMedia;
use Fisharebest\Webtrees\Theme;
use Fisharebest\Webtrees\Tree;

/**
 * Class Fancy Imagebar
 */
class FancyImagebarClass extends FancyImagebarModule {

  /**
   * Set the default module options
   *
   * @param type $key
   * @return string
   */
	private function setDefault($key) {
		$FIB_DEFAULT = [
		'IMAGES'       => [], // All images
		'IMAGE_FOLDER' => 'all', // All folders
		'PHOTOS'       => '1',
		'HOMEPAGE'     => '1',
		'MYPAGE'       => '1',
		'ALLPAGES'     => '0',
		'RANDOM'       => '1',
		'TONE'         => '2', // Colors
		'SEPIA'        => '30', // Example
		'HEIGHT'       => '60',
		'SQUARE'       => '1'
	];
		return $FIB_DEFAULT[$key];
	}

	/**
	 * Get module options
	 *
	 * @param type $k
	 * @return type
	 */
	protected function options($k) {
		$FIB_OPTIONS = unserialize($this->getPreference('FIB_OPTIONS'));
		$key         = strtoupper($k);

		if (empty($FIB_OPTIONS[$this->getTreeId()]) || (is_array($FIB_OPTIONS[$this->getTreeId()]) && !array_key_exists($key, $FIB_OPTIONS[$this->getTreeId()]))) {
			return $this->setDefault($key);
		} else {
			return($FIB_OPTIONS[$this->getTreeId()][$key]);
		}
	}

	private function setOptions($options) {
		$FIB_OPTIONS = unserialize($this->getPreference('FIB_OPTIONS'));
		foreach ($options as $key => $value) {
			$FIB_OPTIONS[$this->getTreeId()][strtoupper($key)] = $value;
		}
		$this->setPreference('FIB_OPTIONS', serialize($FIB_OPTIONS));
	}

	/**
	 * Get the current tree id
	 *
	 * @global type $WT_TREE
	 * @return type
	 */
	protected function getTreeId() {
		global $WT_TREE;

		$tree = Tree::findByName(Filter::get('ged'));
		if ($tree) {
			return $tree->getTreeId();
		} else {
			return $WT_TREE->getTreeId();
		}
	}

	/**
	 * Get the chosen image folder
	 *
	 * @return string
	 */
	protected function getImageFolder() {
		if (Filter::get('folder')) {
			$this->setOptions([
		  'image_folder' => Filter::get('folder'),
		  'images'       => [] // reset the image list
	  ]);
		}

		if ($this->options('image_folder') !== 'all') {
			return $this->options('image_folder');
		}
	}

	/**
	 * Should we only use images with type="photo" set?
	 *
	 * @return boolean
	 */
	protected function getPhotos() {
		$status = Filter::get('photos');
		if ($status) {
			if ($status === 'true') {
				$options['photos'] = '1';
			} else {
				$options['photos'] = '0';
			}
			$options['images'] = []; // reset the image list
			$this->setOptions($options);
		}

		if ($this->options('photos')) {
			return true;
		}
	}

	/**
	 * Generate a list of all the folders in a current tree.
	 *
	 * @param Tree $tree
	 *
	 * @return string[]
	 */
	private function allFolders(Tree $tree) {
		$folders = Database::prepare(
			"SELECT SQL_CACHE LEFT(multimedia_file_refn, CHAR_LENGTH(multimedia_file_refn) - CHAR_LENGTH(SUBSTRING_INDEX(multimedia_file_refn, '/', -1))) AS media_path" .
			" FROM  `##media_file`" .
			" WHERE m_file = ?" .
			" AND   multimedia_file_refn NOT LIKE 'http://%'" .
			" AND   multimedia_file_refn NOT LIKE 'https://%'" .
			" GROUP BY 1" .
			" ORDER BY 1"
		)->execute([
			$tree->getTreeId(),
		])->fetchOneColumn();

		// Ensure we have an empty (top level) folder.
		if (!$folders || reset($folders) !== '') {
			array_unshift($folders, '');
		}

		return array_combine($folders, $folders);
	}

	/**
	 * Get a list of all the media folders
	 *
	 * @global $WT_TREE
	 * @return array
	 */
	protected function listMediaFolders() {
		global $WT_TREE;

		$MEDIA_DIRECTORY = $WT_TREE->getPreference('MEDIA_DIRECTORY');
		$folders         = $this->allFolders($WT_TREE);
		array_shift($folders);

		$folderlist        = [];
		$folderlist['all'] = I18N::translate('All');
		foreach ($folders as $key => $value) {
			if (count(glob(WT_DATA_DIR . $MEDIA_DIRECTORY . $value . '*')) > 0) {
				$folder = array_filter(explode("/", $value));
				// only list first level folders
				if (count($folder) > 0 && !array_search($folder[0], $folderlist)) {
					$folderlist[$folder[0]] = I18N::translate($folder[0]);
				}
			}
		}
		return $folderlist;
	}

	/**
	 * Get the media info from the database
	 *
	 * @param type $LIMIT
	 * @return array
	 */
	private function dbMedia($LIMIT = '') {
		$sql               = "SELECT SQL_CALC_FOUND_ROWS m_id AS xref, m_file AS gedcom_id FROM `##media_file` WHERE m_file = :gedcom_id";
		$args['gedcom_id'] = $this->getTreeId();

		if ($this->getImageFolder()) {
			$sql .= " AND SUBSTRING_INDEX(multimedia_file_refn, '/', 1) = :image_folder";
			$args['image_folder'] = $this->getImageFolder();
		}

		if ($this->getPhotos()) {
			$sql .= " AND source_media_type = 'photo'";
		}

		$sql .= " AND multimedia_format IN ('jpg', 'jpeg', 'png')" . $LIMIT;

		$rows = Database::prepare($sql)->execute($args)->fetchAll();
		return $rows;
	}

	/**
	 * Get a list of all the media xrefs
	 *
	 * @return list
	 */
	protected function getXrefs() {
		$rows = $this->dbMedia();
		$list = [];
		foreach ($rows as $row) {
			$list[] = $row->xref;
		}

		if (count($list) === 0) {
			$this->setOptions(['images[0]' => '']);
		}
		return $list;
	}

	/**
	 * Use Json to load images in control panel (datatable)*
	 *
	 */
	protected function loadJson() {
		$start  = Filter::getInteger('start');
		$length = Filter::getInteger('length');

		if ($length > 0) {
			$LIMIT = " LIMIT " . $start . ',' . $length;
		} else {
			$LIMIT = "";
		}

		$rows = $this->dbMedia($LIMIT);

		// Total filtered/unfiltered rows
		$recordsTotal = $recordsFiltered = Database::prepare("SELECT FOUND_ROWS()")->fetchOne();

		$data = [];
		// example from: admin_media.php
		foreach ($rows as $row) {
			$media = Media::getInstance($row->xref, Tree::findById($row->gedcom_id));
			Media::getInstance($row->xref, Tree::findById($row->gedcom_id));
			$media_files = $media->mediaFiles();
			$media_files = array_map(function (MediaFile $media_file) use ($media) {
				if ($this->options('images') == 1) {
					$attribute = ' checked="checked"';
				} elseif (is_array($this->options('images')) && in_array($media->getXref(), $this->options('images'))) {
					$attribute = ' checked="checked"';
				} else {
					$attribute = "";
				}
				return
					$media_file->displayImage(60, 60, 'crop', []) .
					'<div class="form-check">' .
					'<input class="form-check-input position-static" type="checkbox" aria-label="image-select" value="' . $media->getXref() . '"' . $attribute . '>';
			}, $media_files);
			$data[] = [
				implode('', $media_files)
			];
		}
		header('Content-type: application/json');
		// See http://www.datatables.net/usage/server-side
		echo json_encode([
			'draw'            => Filter::getInteger('draw'), // String, but always an integer
			'recordsTotal'    => $recordsTotal,
			'recordsFiltered' => $recordsFiltered,
			'data'            => $data,
		]);
		return;
	}

	/**
	 * Conditionally load the Fancy Imagebar
	 * This class maybe called directly from a theme
	 *
	 * @global type $ctype
	 * @return boolean
	 */
	public function loadFancyImagebar() {
		if (Theme::theme()->themeId() === '_administration') {
			return false;
		}

		$route = Filter::get('route');

		$all_pages = $this->options('allpages');
		$homepage  = $this->options('homepage');
		$mypage    = $this->options('mypage');

		if ($all_pages || ($route == 'tree-page' && $homepage) || ($route == 'user-page' && $mypage)) {
			return true;
		}
	}

	/**
	 * Get the Fancy Imagebar with chosen images and options
	 * This class may be called directly from a theme
	 *
	 * @return html
	 */
	public function getFancyImagebar() {
		$thumbnails = $this->getThumbnails();

		if (count($thumbnails) === 0) {
			return false;
		}

		// fill up the srcImages array up to a total width of 2400px (for wider screens)
		$srcImages   = [];
		$canvasWidth = 0;
		while ($canvasWidth < 2400) {
			if ($this->options('random') === '1') {
				// shuffle thumbnails before each repetition
				shuffle($thumbnails);
			}

			foreach ($thumbnails as $thumbnail) {
				$canvasWidth = $canvasWidth + imagesx($thumbnail);
				$srcImages[] = $thumbnail;
				if ($canvasWidth >= 2400) {
					break;
				}
			}
		}

		$fancyImagebar = $this->createFancyImagebar($srcImages, $canvasWidth);
		if ($this->options('tone') == 0) {
			$fancyImagebar = $this->fancyImageBarSepia($fancyImagebar, $this->options('sepia'));
		}
		if ($this->options('tone') == 1) {
			$fancyImagebar = $this->fancyImageBarSepia($fancyImagebar, 0);
		}

		// if the Fancy Imagebar is implemented in a theme we don't need to hide the imagebar on load.
		if (method_exists(Theme::theme(), 'fancyImagebar')) {
			$style = "";
		} else {
			$style = ' style="display:none"';
		}

		// set the class
		$theme = Theme::theme()->themeId();
		if ($theme === 'fab') {
			$class = 'fancy-imagebar container theme-' . $theme;
		} else {
			$class = 'fancy-imagebar theme-' . $theme;
		}

		// output
		ob_start();
		imagejpeg($fancyImagebar, null, 100);
		$NewFancyImageBar = ob_get_clean();
		return '<div class="' . $class . '"' . $style . '><img alt="fancy-imagebar" src="data:image/jpeg;base64,' . base64_encode($NewFancyImageBar) . '"><div class="fancy-imagebar-divider"></div></div>';
	}

	/**
	 * Get the medialist from the database
	 *
	 * @return list
	 */
	private function fancyImagebarMedia() {
		$images = $this->options('images');

		$xrefs = [];
		if (empty($images)) {
			$rows = $this->dbMedia();
			foreach ($rows as $row) {
				$xrefs[] = $row->xref;
			}
		} else {
			$xrefs = $images;
		}

		$list = [];
		foreach ($xrefs as $xref) {
			$media = Media::getInstance($xref, Tree::findById($this->getTreeId()));
			if ($media->canshow()) {
				foreach ($media->mediaFiles() as $media_file) {
					if ($media_file->mimeType() == 'image/jpeg' || $media_file->mimeType() == 'image/png') {
						$list[$xref] = $media_file;
					}
				}
			}
		}
		return $list;
	}

	/**
	 * Get the fib_cache directory
	 *
	 * @return cache directory
	 */
	private function cacheDir() {
		return WT_DATA_DIR . 'fib_cache/';
	}

	/**
	 * Get the filename of the cached image
	 *
	 * @param MediaFile $media_file
	 * @return filename
	 */
	private function cacheFileName($filename, $xref) {
		return $this->cacheDir() . $this->getTreeId() . '-' . $xref . '-' . filemtime($filename) . '.jpg';
	}

	/**
	 * (Re)create thumbnails to cache
	 * This function is used when saving settings in control panel
	 *
	 */
	protected function createCache() {
		if (file_exists($this->cacheDir())) {
			$this->emptyCache();
		} else {
			File::mkdir($this->cacheDir());
		}

		foreach ($this->fancyImagebarMedia() as $xref => $media_file) {
			$filename = $media_file->getServerFileName();
			if (file_exists($filename)) {
				$thumbnail = $this->fancyThumb($media_file, $this->options('height'), $this->options('square'));
				if ($thumbnail) {
					imagejpeg($thumbnail, $this->cacheFileName($filename, $xref));
				}
			}
		}
	}

	/**
	 * remove all old cached files for this tree
	 */
	protected function emptyCache() {
		foreach (glob($this->cacheDir() . '*') as $cache_file) {
			if (is_file($cache_file)) {
				$tmp     = explode('-', basename($cache_file));
				$tree_id = intval($tmp[0]);
				if ($tree_id === $this->getTreeId()) {
					unlink($cache_file);
				}
			}
		}
	}

	/**
	 * load image from file
	 * return false if image could not be loaded
	 *
	 * @param type $file
	 * @return boolean
	 */
	public function loadImage($file) {
		if (file_exists($file)) {
			$size = getimagesize($file);
			switch ($size["mime"]) {
		case "image/jpeg":
		  $image = imagecreatefromjpeg($file);
		  break;
		case "image/png":
		  $image = imagecreatefrompng($file);
		  break;
		default:
		  $image = false;
		  break;
	  }
			return $image;
		}
	}

	/**
	 * Get the thumbnails from cache or (re)create them.
	 * Check the filetime of the original image with the filetime stored in the cached image filename
	 * Only recreate a thumbnail if the filetime differs: the original image has been changed.
	 *
	 * @return array with thumbnails
	 */
	private function getThumbnails() {
		$cache_dir = $this->cacheDir();

		if (!file_exists($cache_dir)) {
			File::mkdir($cache_dir);
		}

		$thumbnails = [];
		foreach ($this->fancyImagebarMedia() as $xref => $media_file) {
			$filename = $media_file->getServerFileName();
			if (file_exists($filename)) {
				$cache_filename = $this->cacheFileName($filename, $xref);
				if (is_file($cache_filename)) {
					$thumbnail = $this->loadImage($cache_filename);
					if ($thumbnail) {
						$thumbnails[] = $thumbnail;
					}
				} else {
					$thumbnail = $this->fancyThumb($media_file, $this->options('height'), $this->options('square'));
					if ($thumbnail) {
						imagejpeg($thumbnail, $cache_filename);
						$thumbnails[] = $thumbnail;
					}
				}
			}
		}
		return $thumbnails;
	}

	/**
	 * Create a thumbnail
	 *
	 * @param type $media_file
	 * @return thumbnail
	 */
	private function fancyThumb(MediaFile $media_file, $thumbheight, $square) {
		$filename = $media_file->getServerFilename();
		$type     = $media_file->mimeType();

		$image = $this->loadImage($filename);
		if ($image) {
			list($imagewidth, $imageheight) = getimagesize($filename);
			$ratio                          = $imagewidth / $imageheight;
			if ($square) {
				$thumbwidth = $thumbheight;
				if ($ratio < 1) {
					$new_height = $thumbwidth / $ratio;
					$new_width  = $thumbwidth;
				} else {
					$new_width  = $thumbheight * $ratio;
					$new_height = $thumbheight;
				}
			} else {
				$new_height = $thumbheight;
				$new_width  = $thumbwidth  = $thumbheight * $ratio;
			}

			// transparent png files are not possible in the Fancy Imagebar, so no extra code needed.
			$new_image = imagecreatetruecolor(round($new_width), round($new_height));
			imagecopyresampled($new_image, $image, 0, 0, 0, 0, $new_width, $new_height, $imagewidth, $imageheight);

			$thumb = imagecreatetruecolor($thumbwidth, $thumbheight);
			imagecopyresampled($thumb, $new_image, 0, 0, 0, 0, $thumbwidth, $thumbheight, $thumbwidth, $thumbheight);

			imagedestroy($new_image);
			imagedestroy($image);

			return $thumb;
		}
	}

	/**
	 * Create the Fancy Imagebar
	 *
	 * @param type $srcImages
	 * @param type $thumbWidth
	 * @param type $thumbHeight
	 * @param type $numberOfThumbs
	 * @return Fancy Imagebar
	 */
	private function createFancyImagebar($srcImages, $canvasWidth) {
		$canvasHeight = $this->options('height');

		// create the FancyImagebar canvas to put the thumbs on
		$fancyImagebar = imagecreatetruecolor($canvasWidth, $canvasHeight);

		$pos = 0;
		foreach ($srcImages as $thumb) {
			$x   = $pos;
			$pos = $pos + imagesx($thumb);

			imagecopy($fancyImagebar, $thumb, $x, 0, 0, 0, imagesx($thumb), $canvasHeight);
		}
		return $fancyImagebar;
	}

	/**
	 * Use sepia (optional)
	 *
	 * @param type $fancyImagebar
	 * @param type $depth
	 * @return Fancy Imagebar in Sepia
	 */
	private function fancyImagebarSepia($fancyImagebar, $depth) {
		imagetruecolortopalette($fancyImagebar, 1, 256);
		$palletsize = imagecolorstotal($fancyImagebar);
		for ($c = 0; $c < $palletsize; $c++) {
			$col     = imagecolorsforindex($fancyImagebar, $c);
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
			imagecolorset($fancyImagebar, $c, max(0, min(255, $r)), max(0, min(255, $g)), max(0, min(255, $b)));
		}
		return $fancyImagebar;
	}
}
