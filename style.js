/*
// Inline styles for the Fancy ImageBar module
//
// webtrees: Web based Family History software
// Copyright (C) 2014 webtrees development team.
// Copyright (C) 2014 JustCarmen.
//
// This program is free software; you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation; either version 2 of the License, or
// (at your option) any later version.
//
// This program is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with this program; if not, write to the Free Software
// Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA
*/

var $theme = WT_THEME_DIR.split("/")[1];

jQuery('#fancy_imagebar').css({"clear":"both","overflow":"hidden"});
if ($theme === 'clouds') {
	jQuery('#fancy_imagebar').css({"margin":"10px 10px 0 10px","border":"1px solid #003399"});
	jQuery('#fancy_imagebar img').css({"margin-bottom":"-2px"});
}

if ($theme === 'colors') {
	jQuery('#fancy_imagebar').append('<div class="divider" style="background-color:#999;height:1px;margin-top:1px">');
}

if ($theme === 'fab') {
	jQuery('#fancy_imagebar').css({"border":"#A9A9A9 1px solid","border-radius":"3px","margin":"0 3px"});
	jQuery('#fancy_imagebar img').css({"margin-bottom":"-3px"});
}
if ($theme === 'justblack') {
	jQuery('#fancy_imagebar').css({"margin-top":"-1px"}).append('<div class="divider" style="margin-top:3px">');
}

if ($theme === 'justlight') {
	jQuery('#fancy_imagebar img').css({"border-top":"5px solid #428bca", "border-bottom":"5px solid #428bca"});
}

if ($theme === 'minimal') {
	jQuery('#fancy_imagebar').css({"padding-top":"2px"}).append('<div class="divider" style="background-color:#555555;height:1px">');
}

if ($theme === 'webtrees') {
	jQuery('#fancy_imagebar').append('<div class="divider" style="background-color:#81A9CB;height:2px;margin-top:3px">');
}

if ($theme === 'xenea') {
	jQuery('#fancy_imagebar').append('<div class="divider" style="background-color:#0073CF;height:2px;margin:7px 0 15px">');
}