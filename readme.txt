=== Terrific Integration ===
Contributors: LZurbriggen
Tags: terrific, micro, integration, frontend, modules, skins
Tested up to: 3.8
Stable tag: trunk
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Integrates the Terrific concept (http://terrifically.org) into WordPress.

== Description ==

Note: This plugin won't work on WordPress-instances hosted by WordPress.com.

This plugin integrates the [Terrific concept](http://terrifically.org) into WordPress and is based on [Roger Dudler's Terrific micro](https://github.com/rogerdudler/terrific-micro).
Views as well as partials were removed from the integration, use WordPress functionality (templates/template parts) instead.

## Features

* Create a new theme or add a structure to your active theme to be able to use Terrific micro in your project
* Provides you the option to cache and minify the concatenated styles and scripts for production use
* Provides an interface to create new modules and skins based on file templates
* Inspect mode to get an overview of the modules on a page
* Option to use WordPress' own jQuery

## Usage

You can render a module in your template files using the 'module'-function, e.g.:

`<?php module('TeaserBox'); ?>`

You can provide more parameters to the function to control the output:

`module(Module Name, Module Template Name (optional, default: null), Skin Name (optional, default: null), Additional Attributes (optional, default: array()))`

As this function is part of Terrific micro, you'll find a more detailed description [on Github](https://github.com/rogerdudler/terrific-micro).

You can flush the Terrific cache manually as well as start the inspect mode via the admin bar (see screenshot).

The plugin automatically includes WordPress' own jQuery version as the first script file. You can disable this option in the admin page and include your own jQuery or Zepto version in the assets.json as usual.

There's not much to know about the integration, I recommend you to let the plugin create a new theme to get an idea on how things could be done. If you have problems using Terrific, check out [terrifically.org](http://terrifically.org), the [Terrific micro github page](https://github.com/rogerdudler/terrific-micro) and the [Terrific google group](https://groups.google.com/forum/#!forum/terrific-frontend).

## Dependencies

* [Terrific micro](https://github.com/rogerdudler/terrific-micro)
* [Cssmin](https://code.google.com/p/cssmin)
* [Jsmin](https://github.com/eriknyk/jsmin-php)
* [Lessphp](http://leafo.net/lessphp)
* [Phpsass](https://github.com/richthegeek/phpsass)
* [jQuery](http://jquery.com)

== Installation ==

1. Upload the `terrific` directory to the `/wp-content/plugins/` directory or download the plugin via the WordPress plugin system
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Access the 'Terrific' menu in WordPress an let it generate a new theme/create the basic structure for you
4. Create modules and skins using the same menu

== Screenshots ==

1. If your active theme isn't Terrific enabled, the plugin will give you the option to do so
2. Configure caching behaviour and create new modules/skins
3. Inspect mode

== Changelog ==

= 1.0 =

* Initial release

= 1.1 =

* Adjusted for WordPress 3.8
* Fixed major bug: Path from css and js are now relative to the theme root instead of the active page