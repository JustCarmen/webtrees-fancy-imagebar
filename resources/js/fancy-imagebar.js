// Keep the server-side image width in sync with the browser viewport for the next request.
function setFibWidthCookie() {
    const viewportWidth = Math.max(
        window.innerWidth || 0,
        document.documentElement ? document.documentElement.clientWidth || 0 : 0,
        320
    );

    const expires = new Date(Date.now() + (30 * 24 * 60 * 60 * 1000)).toUTCString();
    document.cookie = `FIB_WIDTH=${encodeURIComponent(viewportWidth)}; expires=${expires}; path=/; SameSite=Lax`;
}

// Script to keep map coordinates in sync with the rendered image.
function mapcoords() {
    const $container = $('.jc-fancy-imagebar');
    const $img = $('.jc-fancy-imagebar img');
    const $areas = $('.jc-fancy-imagebar-map area');

    if (!$img.length || !$areas.length) {
        return;
    }

    const originalWidth = Number($container.attr('data-width')) || $img[0].naturalWidth || $img.width();
    const originalHeight = Number($container.attr('data-height')) || $img[0].naturalHeight || $img.height();
    const renderedWidth = $img[0].getBoundingClientRect().width || $img.width();
    const renderedHeight = $img[0].getBoundingClientRect().height || $img.height();

    const ratioX = originalWidth > 0 ? renderedWidth / originalWidth : 1;
    const ratioY = originalHeight > 0 ? renderedHeight / originalHeight : 1;

    $areas.each(function () {
        const $area = $(this);
        const dataCoords = $area.attr('data-coords') || $area.attr('coords') || '';

        if (!$area.attr('data-coords')) {
            $area.attr('data-coords', dataCoords);
        }

        const coords = ($area.attr('data-coords') || '').split(/[\s,]+/).filter(Boolean).map(Number);

        if (coords.length < 4 || coords.some(Number.isNaN)) {
            return;
        }

        const newCoords = [
            Math.round(coords[0] * ratioX),
            Math.round(coords[1] * ratioY),
            Math.round(coords[2] * ratioX),
            Math.round(coords[3] * ratioY),
        ];

        $area.attr('coords', newCoords.join(','));
    });
}

const onPageReady = function () {
    setFibWidthCookie();
    mapcoords();
};

// Map coordinates as soon as the HTML is parsed and again after the image has loaded.
document.addEventListener('DOMContentLoaded', onPageReady);
window.addEventListener('load', onPageReady);

// Cookie updates are only needed for subsequent requests, so keep resizing throttled.
let fibResizeTimer;
$(window).on('resize orientationchange', function () {
    clearTimeout(fibResizeTimer);
    fibResizeTimer = setTimeout(setFibWidthCookie, 250);
    mapcoords();
});
