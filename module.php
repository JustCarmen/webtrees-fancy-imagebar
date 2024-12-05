<?php

declare(strict_types=1);

namespace JustCarmen\Webtrees\Module\FancyImagebar;

use Fisharebest\Webtrees\Registry;

require __DIR__ . '/FancyImagebarModule.php';

return Registry::container()->get(FancyImagebarModule::class);

