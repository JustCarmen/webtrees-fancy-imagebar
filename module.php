<?php

/**
 * Fancy Imagebar
 *
 * JustCarmen webtrees modules
 * Copyright (C) 2009-2020 Carmen Pijpers-Knegt
 *
 * Based on webtrees: online genealogy
 * Copyright (C) 2020 webtrees development team
 *
 * This file is part of JustCarmen webtrees modules
 *
 * JustCarmen webtrees modules is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 * JustCarmen webtrees modules is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 * You should have received a copy of the GNU General Public License
 * along with JustCarmen webtrees modules. If not, see <http://www.gnu.org/licenses/>.
 */

declare(strict_types=1);

namespace JustCarmen\Webtrees\Module;

use Fisharebest\Webtrees\Factory;
use Fisharebest\Webtrees\FlashMessages;
use Fisharebest\Webtrees\GedcomRecord;
use Fisharebest\Webtrees\I18N;
use Fisharebest\Webtrees\Module\AbstractModule;
use Fisharebest\Webtrees\Module\ModuleConfigInterface;
use Fisharebest\Webtrees\Module\ModuleConfigTrait;
use Fisharebest\Webtrees\Module\ModuleCustomInterface;
use Fisharebest\Webtrees\Module\ModuleCustomTrait;
use Fisharebest\Webtrees\Module\ModuleGlobalInterface;
use Fisharebest\Webtrees\Module\ModuleGlobalTrait;
use Fisharebest\Webtrees\Tree;
use Fisharebest\Webtrees\View;
use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Collection;
use League\Flysystem\FilesystemInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

return new class extends AbstractModule implements ModuleCustomInterface, ModuleConfigInterface, ModuleGlobalInterface
{
    use ModuleCustomTrait;
    use ModuleConfigTrait;
    use ModuleGlobalTrait;

    /**
     * How should this module be identified in the control panel, etc.?
     *
     * @return string
     */
    public function title(): string
    {
        return I18N::translate('Fancy Imagebar');
    }

    /**
     * A sentence describing what this module does.
     *
     * @return string
     */
    public function description(): string
    {
        return I18N::translate('An imagebar with small images between header and content.');
    }
    /**
     * {@inheritDoc}
     * @see \Fisharebest\Webtrees\Module\ModuleCustomInterface::customModuleAuthorName()
     */
    public function customModuleAuthorName(): string
    {
        return 'JustCarmen';
    }

    /**
     * {@inheritDoc}
     * @see \Fisharebest\Webtrees\Module\ModuleCustomInterface::customModuleVersion()
     *
     * We use a system where the version number is equal to the latest version of webtrees
     * Interim versions get an extra sub number
     *
     * The dev version is always one step above the latest stable version of this module
     * The subsequent stable version depends on the version number of the latest stable version of webtrees
     *
     */
    public function customModuleVersion(): string
    {
        return '2.0.8-dev';
    }

    /**
     * A URL that will provide the latest stable version of this module.
     *
     * @return string
     */
    public function customModuleLatestVersionUrl(): string
    {
        return 'https://raw.githubusercontent.com/JustCarmen/webtrees-fancy-imagebar/master/latest-version.txt';
    }

    /**
     * {@inheritDoc}
     * @see \Fisharebest\Webtrees\Module\ModuleCustomInterface::customModuleSupportUrl()
     */
    public function customModuleSupportUrl(): string
    {
        return 'https://github.com/justcarmen/webtrees-fancy-imagebar/issues';
    }

    /**
     * Bootstrap.  This function is called on *enabled* modules.
     * It is a good place to register routes and views.
     *
     * @return void
     */
    public function boot(): void
    {
        // Register a namespace for our views.
        View::registerNamespace($this->name(), $this->resourcesFolder() . 'views/');
    }

    /**
     * Where does this module store its resources
     *
     * @return string
     */
    public function resourcesFolder(): string
    {
        return __DIR__ . '/resources/';
    }

    /**
     * @param ServerRequestInterface $request
     *
     * @return ResponseInterface
     */
    public function getAdminAction(ServerRequestInterface $request): ResponseInterface
    {
        $this->layout = 'layouts/administration';

        return $this->viewResponse($this->name() . '::settings', [
            'title' => $this->title(),
        ]);
    }

    /**
     * Save the user preference.
     *
     * @param ServerRequestInterface $request
     *
     * @return ResponseInterface
     */
    public function postAdminAction(ServerRequestInterface $request): ResponseInterface
    {
        $params = (array) $request->getParsedBody();

        // store the preferences in the database
        $this->setPreference('activate', $params['activate'] ?? '1'); // test example

        $message = I18N::translate('The preferences for the module “%s” have been updated.', $this->title());
        FlashMessages::addMessage($message, 'success');

        return redirect($this->getConfigLink());
    }

    /**
     * Raw content, to be added at the end of the <body> element.
     * Typically, this will be <script> elements.
     *
     * @return string
     */
    public function bodyContent(): string
    {
        $body = $this->fancyImagebarHtml();
        $body .= '<script>';
        $body .= '$(".wt-main-wrapper").prepend($(".jc-fancy-imagebar"))';
        $body .= '</script>';

        return $body;
    }

    /**
     * Raw content, to be added at the end of the <head> element.
     * Typically, this will be <link> and <meta> elements.
     *
     * @return string
     */
    public function headContent(): string
    {
        $url = $this->assetUrl('css/style.css');

        return '<link rel="stylesheet" href="' . e($url) . '">';
    }

    /**
     * Generate the html for the Fancy imagebar
     */
    public function fancyImagebarHtml(): string
    {
        $request = app(ServerRequestInterface::class);
        $tree = $request->getAttribute('tree');

        $data_filesystem = $request->getAttribute('filesystem.data');
        assert($data_filesystem instanceof FilesystemInterface);

        $data_folder = $request->getAttribute('filesystem.data.name');
        assert(is_string($data_folder));

        if ($tree !== null) {
            $records = $this->allMedia($tree, 'jpg', 'photo'); // parameters are module settings
            $resources = array();
            foreach ($records as $record) {
                foreach ($record->mediaFiles() as $media_file) {

                    if ($media_file->isImage() && $media_file->fileExists($data_filesystem)) {

                        $media_folder = $data_folder . $media_file->media()->tree()->getPreference('MEDIA_DIRECTORY', 'media/');
                        $filename     = $media_folder . $media_file->filename();

                        $resources[] = $this->fancyThumb($filename, '80', true); // height and square are module settings
                    }
                }

                $source_images = array();
                $canvas_width = 0;

                // 2400 is the maximum screensize we will take into account.
                while ($canvas_width < 2400) {
                    shuffle($resources); // this setting depends on the module settings

                    foreach ($resources as $resource) {
                        $canvas_width      = $canvas_width + imagesx($resource);
                        $source_images[]   = $resource;
                        if ($canvas_width >= 2400) {
                            break;
                        }
                    }
                }
            }

            // Generate the response.
            $fancy_imagebar = $this->createFancyImagebar($source_images, $canvas_width, '80'); // '80' is a module setting (canvas height)

            $html  = '<div class="jc-fancy-imagebar">';
            $html .= '<img alt="fancy-imagebar" src="data:image/jpeg;base64,' . base64_encode($fancy_imagebar) . '">';
            $html .= '<div class="jc-fancy-imagebar-divider"></div>';
            $html .= '</div>';

            return $html;
        }
    }

    /**
     * Generate a list of all the media objects matching the criteria in a current tree.
     * Source: app\Module\MediaListModule.php
     *
     * SELECT * FROM `wt_media_file` WHERE `m_file`=8 AND `multimedia_format`='jpg' and `source_media_type`='photo'
     *
     * @param Tree   $tree       find media in this tree
     * @param string $format     'jpg'
     * @param string $type       source media type = 'photo'
     *
     * @return Collection<Media>
     */
    private function allMedia(Tree $tree, string $format, string $type): Collection
    {
        $query = DB::table('media')
            ->join('media_file', static function (JoinClause $join): void {
                $join
                    ->on('media_file.m_file', '=', 'media.m_file')
                    ->on('media_file.m_id', '=', 'media.m_id');
            })
            ->where('media.m_file', '=', $tree->id());

        if ($format) {
            $query->where('multimedia_format', '=', $format);
        }

        if ($type) {
            $query->where('source_media_type', '=', $type);
        }

        return $query
            ->get()
            ->map(Factory::media()->mapper($tree))
            ->uniqueStrict()
            ->filter(GedcomRecord::accessFilter());
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
    private function createFancyImagebar($source_images, $canvas_width, $canvas_height)
    {
        // create the FancyImagebar canvas to put the thumbs on
        $fancy_imagebar_canvas = imagecreatetruecolor((int) $canvas_width, (int) $canvas_height);

        $pos = 0;
        foreach ($source_images as $image) {
            $x = $pos;
            $pos = $pos + imagesx($image);

            // copy the images (thumbnails) onto the canvas
            imagecopy($fancy_imagebar_canvas, $image, $x, 0, 0, 0, imagesx($image), (int) $canvas_height);
        }

        ob_start();
        imagejpeg($fancy_imagebar_canvas);
        $fancy_imagebar = ob_get_clean();

        return $fancy_imagebar;
    }

    /**
     * load image from file
     * return false if image could not be loaded
     *
     * @param type $file
     * @return boolean
     */
    public function loadImage($file)
    {
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

    /**
     * Create a thumbnail
     *
     * @param type $mediaobject
     * @return thumbnail
     */
    private function fancyThumb($file, $canvas_height, $square)
    {
        $image = $this->loadImage($file);
        list($width, $height) = getimagesize($file);
        $ratio = $width / $height;
        if ($square) {
            $thumbwidth = $canvas_height;
            if ($ratio < 1) {
                $new_height = $thumbwidth / $ratio;
                $new_width  = $thumbwidth;
            } else {
                $new_width  = $canvas_height * $ratio;
                $new_height = $canvas_height;
            }
        } else {
            $new_height = $canvas_height;
            $new_width  = $thumbwidth = $canvas_height * $ratio;
        }

        $thumb = imagecreatetruecolor((int) round($new_width), (int) round($new_height));
        imagecopyresampled($thumb, $image, 0, 0, 0, 0, (int) $new_width, (int) $new_height, (int) $width, (int) $height);

        imagedestroy($image);

        return $thumb; // resource
    }
};
