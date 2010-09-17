=== WordPress Varnish ===
Contributors: pkhamre, wfelipe, eitch
Donate link: http://github.com/pkhamre/wp-varnish
Tags: cache, caching, performance, varnish, purge, speed
Requires at least: 2.9.2
Tested up to: 2.9.2
Stable tag: 0.3

WordPress Varnish is a simple plugin that purges new and edited content.

== Description ==

This plugin purges your varnish cache when content is added or edited. This includes when a new post is
added, a post is updated or when a comment is posted to your blog.

To keep widgets like "Recent posts", "Recent comments" and such up to date, you should consider using ESI
and include them through a text widget for arbitrary text or HTML.

== Installation ==

This section describes how to install the plugin and get it working.

1. Upload `wp-varnish/` to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress

== Frequently Asked Questions ==

= Does this just work? =

Yes.

= But how should my varnish configuration file look like? =

I have provided a simple VCL that can be used as a reference.

= Does it work for Multi-Site (or WPMU)?

Yes. Activating the plugin site-wide will provide the functionality to all
blogs. Configuration can be done on the blogs individually, or can be global.
If you want to configure Varnish servers globally, edit wp-config.php and
include these lines just before "That's all, stop editing!" message:

global $varnish_servers;
$varnish_servers = array('192.168.0.1:80','192.168.0.2:80');
define('VARNISH_SHOWCFG',1);

The varnish servers array will configure multiple servers for sending the
purges. If VARNISH_SHOWCFG is defined, configuration will be shown to all
users who access the plugin configuration page (but they can't edit it).

== Screenshots ==

1. Screenshot of the adminstration interface.

== Changelog ==

= 0.3 =
* Added internationalization code. Included pt_BR translation.
* Support to Multi-Site and WPMU with global configuration.
* Fix on URL purges for multiple domains and blogs on sub-directories.
* Code clean up on some functions.
* Added configuration for purging all blog, page and comments navigation.

= 0.2 =
* Added multiple servers support and timeout configuration.

= 0.1 =
* Initial release.

== Upgrade Notice ==

= 0.3 =
* Varnish PURGE configuration must support regex. wp-varnish will
sometimes request with regex for special purges like refreshing
all blog cache and refreshing comments.
