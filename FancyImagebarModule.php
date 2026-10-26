<?php

declare(strict_types=1);

namespace JustCarmen\Webtrees\Module\FancyImagebar;

use Exception;
use Fisharebest\Localization\Translation;
use Fisharebest\Webtrees\FlashMessages;
use Fisharebest\Webtrees\GedcomRecord;
use Fisharebest\Webtrees\Http\RequestHandlers\TreePage;
use Fisharebest\Webtrees\I18N;
use Fisharebest\Webtrees\Individual;
use Fisharebest\Webtrees\Media;
use Fisharebest\Webtrees\MediaFile;
use Fisharebest\Webtrees\Module\AbstractModule;
use Fisharebest\Webtrees\Module\ModuleConfigInterface;
use Fisharebest\Webtrees\Module\ModuleConfigTrait;
use Fisharebest\Webtrees\Module\ModuleCustomInterface;
use Fisharebest\Webtrees\Module\ModuleCustomTrait;
use Fisharebest\Webtrees\Module\ModuleGlobalInterface;
use Fisharebest\Webtrees\Module\ModuleGlobalTrait;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Services\LinkedRecordService;
use Fisharebest\Webtrees\Services\MediaFileService;
use Fisharebest\Webtrees\Services\TreeService;
use Fisharebest\Webtrees\Tree;
use Fisharebest\Webtrees\View;
use Fisharebest\Webtrees\Webtrees;
use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Database\Query\Expression;
use Illuminate\Support\Collection;
use JustCarmen\Webtrees\Internationalization\MoreI18N;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;

class FancyImagebarModule extends AbstractModule implements ModuleCustomInterface, ModuleConfigInterface, ModuleGlobalInterface
{
    use ModuleCustomTrait;
    use ModuleConfigTrait;
    use ModuleGlobalTrait;

    // Module constants
    public const CUSTOM_AUTHOR = 'JustCarmen';
    public const CUSTOM_VERSION = '2.4.1';
    public const GITHUB_REPO = 'webtrees-fancy-imagebar';
    public const AUTHOR_WEBSITE = 'https://justcarmen.nl';
    public const CUSTOM_SUPPORT_URL = self::AUTHOR_WEBSITE . '/modules-webtrees-2/fancy-imagebar/';

    private const MIN_CANVAS_HEIGHT = 40;
    private const MAX_CANVAS_HEIGHT = 160;
    private const DEFAULT_CANVAS_HEIGHT = 80;

    // Image cache dir
    private const CACHE_DIR = Webtrees::DATA_DIR . 'fib-cache/';

    // Supported image formats. GEDCOM 5.5.1 uses a file extension as format (FORM jpg),
    // GEDCOM 7 uses a media type (FORM image/jpeg). Both are accepted.
    private const SUPPORTED_FORMATS = ['jpg', 'jpeg', 'png'];
    private const SUPPORTED_MEDIA_TYPES = ['image/jpeg', 'image/jpg', 'image/png'];

    // webtrees stores only the first four characters of the format in the media_file table,
    // so every GEDCOM 7 image media type (image/jpeg, image/png, image/gif, ...) is stored as 'IMAG'.
    private const GEDCOM7_IMAGE_FORMAT = 'IMAG';

    /** @var MediaFileService */
    private $media_file_service;

    /** @var TreeService */
    private $tree_service;

    /** @var LinkedRecordService */
    private $linked_record_service;

    /**
     * FancyImagebar constructor.
     *
     * @param MediaFileService  $media_file_service
     * @param TreeService       $tree_service
     */
    public function __construct(MediaFileService $media_file_service, TreeService $tree_service, LinkedRecordService $linked_record_service)
    {
        $this->media_file_service       = $media_file_service;
        $this->tree_service             = $tree_service;
        $this->linked_record_service    = $linked_record_service;
    }

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
        return self::CUSTOM_AUTHOR;
    }

    /**
     * {@inheritDoc}
     * @see \Fisharebest\Webtrees\Module\ModuleCustomInterface::customModuleVersion()
     */
    public function customModuleVersion(): string
    {
        return self::CUSTOM_VERSION;
    }

    /**
     * A URL that will provide the latest stable version of this module.
     *
     * @return string
     */
    public function customModuleLatestVersionUrl(): string
    {
        return 'https://raw.githubusercontent.com/' . self::CUSTOM_AUTHOR . '/' . self::GITHUB_REPO . '/main/latest-version.txt';
    }

    /**
     * {@inheritDoc}
     * @see \Fisharebest\Webtrees\Module\ModuleCustomInterface::customModuleSupportUrl()
     */
    public function customModuleSupportUrl(): string
    {
        return self::CUSTOM_SUPPORT_URL;
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

        $tree_id = $this->getPreference('last-tree-id', '');

        try {
            $tree = $this->tree_service->find((int)$tree_id);
        } catch (Exception $ex) {
            // the last tree saved doesn't exist anymore. Use the first tree instead.
            $tree = $this->tree_service->all()->first();
            $tree_id = $tree->id();

            // remove settings from non existing tree from the database
            DB::table('module_setting')
                ->where('module_name', '=', $this->name())
                ->where('setting_name', 'LIKE', '' . $this->getPreference('last-tree-id') . '-%' )
                ->delete();

            // reset the last tree id
            $this->setPreference('last-tree-id', '');
        }

        $data_filesystem = Registry::filesystem()->data();

        $media_folders = $this->media_file_service->allMediaFolders($data_filesystem);
        $media_types = Registry::elementFactory()->make('OBJE:FILE:FORM:TYPE')->values();

        $media_folder = $this->getPreference($tree_id . '-media-folder');
        $media_type = $this->getPreference($tree_id . '-media-type');
        $subfolders = $this->getPreference($tree_id . '-subfolders');
        $xrefs = $this->getMediaXrefs($tree, str_replace($tree->getPreference('MEDIA_DIRECTORY', 'media/'), "", $media_folder), $subfolders, $media_type);

        return $this->viewResponse($this->name() . '::settings', [
            'all_trees'        => $this->tree_service->all(),
            'canvas_height'    => $this->canvasHeight($tree),
            'canvas_height_min' => self::MIN_CANVAS_HEIGHT,
            'canvas_height_max' => self::MAX_CANVAS_HEIGHT,
            'display_all'      => $this->getPreference($tree_id . '-display-all'),
            'homepage_only'    => $this->getPreference($tree_id . '-homepage-only'),
            'media_folder'     => $media_folder,
            'media_folders'    => $media_folders,
            'media_list'       => $this->getMediaList($tree),
            'media_type'       => $media_type,
            'media_types'      => $media_types,
            'square_thumbs'    => $this->getPreference($tree_id . '-square-thumbs'),
            'subfolders'       => $subfolders,
            'title'            => $this->title(),
            'tree_id'          => $tree_id,
            'xrefs'            => $xrefs
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

        $canvas_height = null;
        if (($params['save'] ?? null) === '1') {
            $canvas_height_input = $params['canvas-height'] ?? null;
            if (is_string($canvas_height_input) || is_int($canvas_height_input)) {
                $canvas_height = filter_var($canvas_height_input, FILTER_VALIDATE_INT, [
                    'options' => [
                        'min_range' => self::MIN_CANVAS_HEIGHT,
                        'max_range' => self::MAX_CANVAS_HEIGHT,
                    ],
                ]);
            }

            if ($canvas_height === false || $canvas_height === null) {
                FlashMessages::addMessage(
                    I18N::translate(
                        'The height must be an integer between %s and %s pixels.',
                        I18N::number(self::MIN_CANVAS_HEIGHT),
                        I18N::number(self::MAX_CANVAS_HEIGHT)
                    ),
                    'danger'
                );

                return redirect($this->getConfigLink());
            }
        }

        // store the preferences in the database when editing the form
        $tree_id = $params['tree-id'];
        $this->setPreference('last-tree-id', $tree_id);

        if ($params['switch'] === '1') {
            $this->setPreference($tree_id . '-media-folder', $params['media-folder']);
            $this->setPreference($tree_id . '-subfolders', $params['subfolders'] ?? '0');
            $this->setPreference($tree_id . '-media-type',  $params['media-type']);
            $this->setPreference($tree_id . '-display-all',  $params['display-all']);
        }

        if ($params['save'] === '1') {
            $this->setPreference($tree_id . '-canvas-height', (string) $canvas_height);
            $this->setPreference($tree_id . '-square-thumbs',  $params['square-thumbs']);
            $this->setPreference($tree_id . '-homepage-only',  $params['homepage-only']);
            $this->setPreference($tree_id . '-media-list', $params['media-list']);

            // remove previously cached images. The cache is recreated the first time the imagebar is loaded after the settings are changed
            $tree = $this->tree_service->find((int)$tree_id);
            $this->emptyCache($tree);

            $message = MoreI18N::xlate('The preferences for the module “%s” have been updated.', $this->title());
            FlashMessages::addMessage($message, 'success');
        }

        return redirect($this->getConfigLink());
    }

    /**
     * Raw content, to be added at the end of the <body> element.
     * Typically, this will be <script> elements.
     *
     * Script to put the fancy imagebar in place once it is created
     *
     * @return string
     */
    public function bodyContent(): string
    {
        $body = $this->fancyImagebar();
        $body .= '<script src="' . e($this->assetUrl('js/fancy-imagebar.js')) . '"></script>';
        $body .= '<script>';
        $body .= '$(".wt-main-wrapper").prepend($(".jc-fancy-imagebar"));';
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
        $request    = Registry::container()->get(ServerRequestInterface::class);
        $tree       = $request->getAttribute('tree');

        if ($tree === null) {
            return '';
        }

        $canvas_height = $this->canvasHeight($tree);
        $canvas_height_md = 0.85 * $canvas_height;
        $canvas_height_sm = 0.75 * $canvas_height;

        $url = $this->assetUrl('css/style.css');

        return '
            <style>
            .jc-fancy-imagebar img {
                height: ' . $canvas_height . 'px;
            }

            @media screen and (max-width: 992px) {
                .jc-fancy-imagebar img {
                    height: ' . $canvas_height_md . 'px;
                }
            }

            @media screen and (max-width: 768px) {
                .jc-fancy-imagebar img {
                    height: ' . $canvas_height_sm . 'px;
                }
            }
            </style>
            <link rel="stylesheet" href="' . e($url) . '">';
    }

    /**
     * Additional/updated translations.
     *
     * @param string $language
     *
     * @return string[]
     */
    public function customTranslations(string $language): array
    {
        $lang_dir   = $this->resourcesFolder() . 'lang/';
        $file       = $lang_dir . $language . '.mo';
        if (file_exists($file)) {
            return (new Translation($file))->asArray();
        } else {
            return [];
        }
    }

    /**
     * Generate the html for the Fancy imagebar
     *
     * @return string
     */
    public function fancyImagebar(): string
    {
        $request = Registry::container()->get(ServerRequestInterface::class);

        $tree = $request->getAttribute('tree');
        if ($tree === null) {
            return '';
        }

        $route = $request->getAttribute('route');
        $homepage_only = $this->getPreference($tree->id() . '-homepage-only', '0');
        if ($homepage_only === '1' && $route->name !== TreePage::class) {
           return '';
        }

        $data_folder = Registry::filesystem()->dataName();

        $wt_media_folder = $tree->getPreference('MEDIA_DIRECTORY', 'media/');

        if (!file_exists(self::CACHE_DIR)) {
			mkdir(self::CACHE_DIR);
		}

        // Set default values in case the settings are not stored in the database yet
        $subfolders      = $this->getPreference($tree->id() . '-subfolders', '1');
        $media_type      = $this->getPreference($tree->id() . '-media-type');
        $square_thumbs   = $this->getPreference($tree->id() . '-square-thumbs', '0');
        $canvas_height   = $this->canvasHeight($tree);

        // strip out the default media directory from the folder path. It is not stored in the database
        $folder = str_replace($wt_media_folder, "", $this->getPreference($tree->id() . '-media-folder'));

        // include or exclude subfolders
        $subfolders = $subfolders === '1' ? 'include' : 'exclude';

        // Pull the user defined medialist from the database module settings
        $media_list = $this->getMediaList($tree);

         // Get the collection of xrefs to work with
         if ($media_list->isEmpty()) {
            $xrefs = $this->getMediaXrefs($tree, $folder, $subfolders, $media_type);
        } else {
            $xrefs = $media_list->map(function ($xref) {
                return substr($xref, 0, (int)strpos($xref, "["));
            })->unique();
        }

        // randomize the collection
        $xrefs = $xrefs->shuffle();

        // We need to set a limit due to performance. We use a cookie based on the user's screen width to achieve that.
        // The default value (3840) is equal to a 4K screen.
        $cookie_width = $_COOKIE['FIB_WIDTH'] ?? null;
        $canvas_width = is_string($cookie_width) ? filter_var($cookie_width, FILTER_VALIDATE_INT) : false;

        if ($canvas_width === false || $canvas_width <= 0) {
            $canvas_width = 3840;
        } else {
            $canvas_width = min($canvas_width, 3840);
        }

        // add support for small screens (to be in sync with smaller height - see function HeadContent())
        switch ($canvas_width) {
            case $canvas_width < 768:
                $canvas_width = $canvas_width / 0.75;
                break;
            case $canvas_width < 992:
                $canvas_width = $canvas_width / 0.85;
                break;
        }

        // Get the thumbnail resources
        $resources = array();
        $calculated_width = 0;

        foreach ($xrefs as $xref) {

            $media = Registry::mediaFactory()->make($xref, $tree);

            if ($media && $media->canShow()) {
                $i = 0; // counter for multiple media files in a media object
                foreach ($media->mediaFiles() as $media_file) {
                    $i++;
                    $media_id = $xref . '[' . $i. ']';

                    $process_image = $media->mediaFiles()->count() > 1 ? $media_list->contains($media_id) : true;

                    if ($process_image && $this->isSupportedImage($media_file) && $media_file->fileExists()) {

                        $file       = $data_folder . $wt_media_folder . $media_file->filename();
                        $cache_file = $this->cacheFile($tree, $media_id, $file);

                        $fancy_thumb = is_file($cache_file) ? $this->loadImage($cache_file) : false;

                        if ($fancy_thumb === false) {
                            $fancy_thumb = $this->fancyThumb($file, $canvas_height, $square_thumbs === '1');
                            if (!is_null($fancy_thumb)) {
                                imagejpeg($fancy_thumb, $cache_file);
                            }
                        }

                        if ($fancy_thumb !== false && $fancy_thumb !== null) {
                            $calculated_width = $calculated_width + imagesx($fancy_thumb);
                            $resources[] = [
                                'image'     => $fancy_thumb,
                                'linked'    => $this->getLinkedObject($media)
                            ];
                        }
                    }
                }
            }

            // Stop when we have enough thumbnails to fill the entire width of the screen with the Fancy Imagebar.
            if ($calculated_width >= $canvas_width) {
                break;
            }
        }

        if (count($resources) === 0) {
            return '';
        }

        // prevent media files from one media object always stick together
        shuffle($resources);

        // Repeat items if neccessary to fill up the Fancy Imagebar
        if ($calculated_width < $canvas_width) {
            $average_thumb_width = (float)$calculated_width / count($resources);
            $num_thumbs = (int)ceil($canvas_width / $average_thumb_width);
            // see: https://stackoverflow.com/questions/2963777/how-to-repeat-an-array-in-php
            $resources = array_merge(...array_fill(0, (int)ceil($num_thumbs/count($resources)), $resources));
        }

        try {
            return $this->createFancyImagebar($resources, $canvas_width, $canvas_height);
        } finally {
            $released_images = [];
            foreach ($resources as $resource) {
                $image = $resource['image'];
                $image_id = is_object($image) ? spl_object_id($image) : (int) $image;

                if (!isset($released_images[$image_id])) {
                    imagedestroy($image);
                    $released_images[$image_id] = true;
                }
            }

            unset($fancy_thumb, $image, $resource, $resources);
        }
    }

    private function getMediaList(Tree $tree): Collection
    {
        return collect(explode(',', $this->getPreference($tree->id() . '-media-list')))->filter(function (string $value) {
            return $value <> "";
        });
    }

    private function canvasHeight(Tree $tree): int
    {
        $height = $this->getPreference($tree->id() . '-canvas-height', (string) self::DEFAULT_CANVAS_HEIGHT);
        if (!is_string($height) && !is_int($height)) {
            return self::DEFAULT_CANVAS_HEIGHT;
        }

        $height = filter_var($height, FILTER_VALIDATE_INT, [
            'options' => [
                'min_range' => self::MIN_CANVAS_HEIGHT,
                'max_range' => self::MAX_CANVAS_HEIGHT,
            ],
        ]);

        return $height === false ? self::DEFAULT_CANVAS_HEIGHT : $height;
    }

    /**
     * @param Tree $tree
     * @param string $folder
     * @param string $subfolders
     * @param string $type
     *
     * @return Collection
     */
    private function getMediaXrefs(Tree $tree, string $folder, string $subfolders, string $type): Collection
    {
        $query = DB::table('media_file')
            ->join('media', function ($join): void {
                $join
                    ->on('media.m_id', '=', 'media_file.m_id')
                    ->on('media.m_file', '=', 'media_file.m_file');
            })
            ->where('media_file.m_file', '=', $tree->id())
            ->where('multimedia_file_refn', 'LIKE', $folder . '%')
            ->where(function ($query): void {
                // GEDCOM 5.5.1: the format is a file extension
                $query->whereIn(new Expression('lower(multimedia_format)'), self::SUPPORTED_FORMATS);

                // GEDCOM 7: the format is a media type, which webtrees truncates to 'IMAG'.
                // A missing format is allowed as well. Use the file extension to select jpg and png images.
                // Each file is checked again with isSupportedImage() before it is used.
                $query->orWhere(function ($query): void {
                    $query
                        ->whereIn(new Expression('upper(multimedia_format)'), [self::GEDCOM7_IMAGE_FORMAT, ''])
                        ->where(function ($query): void {
                            foreach (self::SUPPORTED_FORMATS as $extension) {
                                $query->orWhere(new Expression('lower(multimedia_file_refn)'), 'LIKE', '%.' . $extension);
                            }
                        });
                });
            });

        if ($subfolders === 'exclude') {
            $query->where('multimedia_file_refn', 'NOT LIKE', $folder . '%/%');
        }

        if ($type) {
            $query->where(function ($query) use ($type): void {
                // GEDCOM 5.5.1: FILE:FORM:TYPE, stored by webtrees in the media_file table
                $query->where(new Expression('upper(source_media_type)'), '=', $type);

                // GEDCOM 7: FILE:FORM:MEDI, which webtrees does not store in the media_file table
                $query
                    ->orWhere('media.m_gedcom', 'LIKE', "%\n3 MEDI " . $type . "\n%")
                    ->orWhere('media.m_gedcom', 'LIKE', "%\n3 MEDI " . $type);
            });
        }

        return $query->pluck('media_file.m_id')->unique();
    }

    /**
     * Is this media file a local jpg or png image that can be used in the Fancy Imagebar?
     * Supports both the GEDCOM 5.5.1 format (FORM jpg) and the GEDCOM 7 media type (FORM image/jpeg).
     *
     * @param MediaFile $media_file
     *
     * @return bool
     */
    public function isSupportedImage(MediaFile $media_file): bool
    {
        if ($media_file->isExternal()) {
            return false;
        }

        $format = strtolower(trim($media_file->format()));

        if (in_array($format, self::SUPPORTED_FORMATS, true) || in_array($format, self::SUPPORTED_MEDIA_TYPES, true)) {
            return true;
        }

        // No format given: use the file extension, like webtrees does
        return $format === '' && in_array($media_file->mimeType(), ['image/jpeg', 'image/png'], true);
    }

    /**
     * Create the Fancy Imagebar + html output
     *
     * @param array<int, array{image: \GdImage|resource, linked: GedcomRecord|null}> $source_images
     * @param int|float $canvas_width
     * @param int $canvas_height
     *
     * @return string
     */
    private function createFancyImagebar(array $source_images, $canvas_width, int $canvas_height): string
    {
        // create the FancyImagebar canvas to put the thumbs on
        $fancy_imagebar_canvas = imagecreatetruecolor((int) $canvas_width, (int) $canvas_height);

        try {
            $fancy_map = [];
            $pos = 0;
            foreach ($source_images as $source) {

                $image  = $source['image'];
                $linked = $source['linked'];

                $x1  = $pos;
                $x2  = $x1 + imagesx($image);
                $pos = $pos + imagesx($image);

                // copy the images (thumbnails) to the canvas
                // imagecopy (resource $dst_im , resource $src_im , int $dst_x , int $dst_y , int $src_x , int $src_y , int $src_w , int $src_h)
                imagecopy($fancy_imagebar_canvas, $image, $x1, 0, 0, 0, imagesx($image), (int) $canvas_height);

                // prepare the map
                if ($linked !== null) {
                    $lifespan = $linked instanceof Individual ? ' (' . $linked->lifespan() . ')' : '';
                    $fancy_map[] = [
                        'coords' => [
                            'x1' => $x1,
                            'y1' => '0',
                            'x2' => $x2,
                            'y2' => $canvas_height
                        ],
                        'title' => strip_tags($linked->fullName() . $lifespan),
                        'url'   => $linked->url()
                    ];
                }
            }

            // Output
            $buffer_level = ob_get_level();
            ob_start();
            try {
                imagejpeg($fancy_imagebar_canvas);
                $fancy_imagebar = ob_get_contents();
            } finally {
                while (ob_get_level() > $buffer_level) {
                    ob_end_clean();
                }
            }
        } finally {
            if ($fancy_imagebar_canvas !== false) {
                imagedestroy($fancy_imagebar_canvas);
            }
        }

        return view($this->name() . '::fancy-imagebar', [
            'fancy_imagebar' => $fancy_imagebar,
            'fancy_map'      => $fancy_map,
            'canvas_width'   => $canvas_width,
            'canvas_height'  => $canvas_height
        ]);
    }

    /**
     * Create a thumbnail
     *
     * @param string $file
     * @param int    $canvas_height
     * @param bool   $square_thumbs
     *
     * @return \GdImage|resource|null
     *
     * https://www.jveweb.net/en/archives/2010/09/how-to-create-cropped-and-scaled-thumbnails-in-php.html
     */
    private function fancyThumb(string $file, int $canvas_height, bool $square_thumbs)
    {
        $image_size = getimagesize($file);
        if ($image_size === false) {
            return null;
        }

        $source_image = $this->loadImage($file, $image_size['mime']);
        if ($source_image === false) {
            return null;
        }

        [$source_width, $source_height] = $image_size;
        $source_ratio = $source_width / $source_height;

        $source_x = 0;
        $source_y = 0;

        // if square thumbnails are wanted then resize and crop the original image
        if ($square_thumbs) {
            $thumb_width = $thumb_height = $canvas_height;

            if ($source_ratio < 1) {
                $source_y = ceil(($source_height - $source_width) / 2);
                $source_height = $source_width;
            }

            if ($source_ratio > 1) {
                $source_x = ceil(($source_width - $source_height) / 2);
                $source_width = $source_height;
            }
        } else {
            $thumb_width  = $canvas_height * $source_ratio;
            $thumb_height = $canvas_height;
        }

        $thumb = ImageCreateTrueColor((int)$thumb_width, (int)$thumb_height);
        if ($thumb === false) {
            imagedestroy($source_image);

            return null;
        }

        try {
            imagecopyresampled($thumb, $source_image, 0, 0, (int)$source_x, (int)$source_y, (int)$thumb_width, (int)$thumb_height, (int)$source_width, (int)$source_height);
        } finally {
            imagedestroy($source_image);
        }

        return $thumb;
    }

    /**
     * Load a supported image from a file.
     *
     * @param string $file
     *
     * @return \GdImage|resource|false
     */
    private function loadImage(string $file, ?string $mime_type = null)
    {
        if ($mime_type === null) {
            $size = getimagesize($file);
            if ($size === false) {
                return false;
            }

            $mime_type = $size['mime'];
        }

        switch ($mime_type) {
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
	 * Get the filename of the cached image
	 *
     * @param Tree $tree
     * @param string $media_id
     * @param string $file
     *
	 * @return string
	 */
	private function cacheFile(Tree $tree, string $media_id, string $file): string {
		return self::CACHE_DIR . $tree->id() . '-' . $media_id . '-' . filemtime($file) . '.jpg';
	}

    /**
	 * remove all old cached files for this tree after changing settings in control panel
	 */
	protected function emptyCache(Tree $tree): void {
		foreach (glob(self::CACHE_DIR . '*') as $cache_file) {
			if (is_file($cache_file)) {
				$tmp	 = explode('-', basename($cache_file));
				$tree_id = intval($tmp[0]);
				if ($tree_id === $tree->id()) {
					unlink($cache_file);
				}
			}
		}
	}

    /**
     * Which objects are linked to this file?
     * return the first linked object (indi, fam or source)
     *
     * @param Media $media
     * @return GedcomRecord|null
     */
    private function getLinkedObject(Media $media): ?GedcomRecord
    {
        // return the first link found
        return
            $this->linked_record_service->linkedIndividuals($media)->first() ??
            $this->linked_record_service->linkedFamilies($media)->first() ??
            $this->linked_record_service->linkedSources($media)->first();
    }
};
