Fancy Imagebar for webtrees
===========================

[![Latest Release](https://img.shields.io/github/release/JustCarmen/webtrees-fancy-imagebar.svg)][1]
[![webtrees major version](https://img.shields.io/badge/webtrees-v2.2.x-green)][2]
[![Downloads](https://img.shields.io/github/downloads/JustCarmen/webtrees-fancy-imagebar/total.svg)]()

## 💖 Support my work

I develop webtrees modules in my free time and make them available to the webtrees community for free.
If you find my work valuable and would like to support further development, you can make a contribution via PayPal:

[![Donate](https://www.paypalobjects.com/en_US/i/btn/btn_donateCC_LG.gif)](https://www.paypal.com/donate?business=XPBC2W85M38AS&currency_code=EUR)

Every contribution — big or small — helps me keep the modules up to date and build new features.

Thank you for your support.

Introduction
------------
The Fancy Imagebar adds a stylish image strip between the header and the main content of your webtrees website.

The images in the imagebar are clickable. When a visitor clicks an image, they are taken directly to the related individual, family, or source page. If an image contains multiple links, the first valid link is used.

Features
--------
- Display a gallery-style image strip between the header and main content
- Choose one or more folders containing the images to show
- Filter images by type, or leave empty to show all supported files
- Set the imagebar height and enable square thumbnails
- Configure separate settings for each tree
- Each image is linked to the related individual, family, or source page

Translations
------------
You can help translate this module. The language files are available on [POEditor][3] where you can contribute updates. Alternatively, use a local editor such as Poedit or Notepad++ to edit the translations, then send them back to me via pull request or [email][5]. Updated translations will be included in the next module release.

Installation
------------
Install using [Custom Module Manager][6] for an easy and convenient way to install webtrees custom modules.
Open the Custom Module Manager in webtrees, scroll to “Fancy Research Links”, and click “Install Module”.

### Manual installation
Download the [latest release][1] of the module. Unpack the ZIP file and place the folder `jc-fancy-research-links` in the `modules_v4` folder of webtrees. Upload the new folder to your server. The module is enabled by default. Go to the control panel to adjust the options. You can find the Fancy Research Links configuration page in the Sidebar section and on the module page.

### Install using Composer
If you are using the webtrees source code, you can install this module with Composer:

```bash
composer require justcarmen/jc-fancy-imagebar
```
After installation you should run composer update to install the neccessary vendor files.

Configuration
-------------
The module is easy to configure from the control panel. You can choose one or more folders containing the images to display, filter by image type (leave empty to show all), set the imagebar height, and enable square thumbnails if desired. External images are not supported, and you can configure separate settings for each tree.

Bugs and feature requests
-------------------------
If you are experiencing bugs or have a feature request for this module, please [create a new issue][4].

 [1]: https://github.com/JustCarmen/webtrees-fancy-imagebar/releases/latest
 [2]: https://webtrees.github.io/download/
 [3]: https://poeditor.com/join/project?hash=kH2euLO811
 [4]: https://github.com/JustCarmen/webtrees-fancy-imagebar/issues?state=open
 [5]: mailto:carmen@justcarmen.nl
 [6]: https://github.com/Jefferson49/CustomModuleManager

