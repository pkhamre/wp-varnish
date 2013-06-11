=== WordPress Varnish ===
Contributors: pkhamre, wfelipe, eitch
Donate link: http://github.com/pkhamre/wp-varnish
Tags: cache, caching, performance, varnish, purge, speed
Requires at least: 2.9.2
* Tested up to: 3.5.1
* Stable tag: 0.8

WordPress Varnish is a simple plugin that purges new and edited content.

== Description ==

This plugin purges your varnish cache when content is added or edited. This
includes when a new post is added, a post is updated or when a comment is
posted to your blog.

To keep widgets like "Recent posts", "Recent comments" and such up to date,
you should consider using ESI and include them through a text widget for
arbitrary text or HTML.

== Installation ==

This section describes how to install the plugin and get it working.

1. Install the plugin 'WordPress Varnish' through the 'Plugins' menu in
WordPress
2. Activate the plugin through the 'Plugins' menu in WordPress

== Frequently Asked Questions ==

= Does this just work? =

Yes.

= But how should my varnish configuration file look like? =

I have provided a simple VCL that can be used as a reference.

= Does it work for Multi-Site (or WPMU)? =

Yes. Activating the plugin site-wide will provide the functionality to all
blogs. Configuration can be done on the blogs individually, or can be global.
If you want to configure Varnish servers globally, edit wp-config.php and
include these lines just before "That's all, stop editing!" message:

global $varnish_servers;
$varnish_servers = array('192.168.0.1:80:secret','192.168.0.2:80:secret');
define('VARNISH_SHOWCFG',1);

The varnish servers array will configure multiple servers for sending the
purges. If VARNISH_SHOWCFG is defined, configuration will be shown to all
users who access the plugin configuration page (but they can't edit it).

= My Plugins are seeing the Varnish server's IP rather than the websurfer IP =

You could install Apache's mod_rpaf module: http://stderr.net/apache/rpaf/

or, in wp-config.php, near the top, put the following code:

    $temp_ip = explode(',', isset($_SERVER['HTTP_X_FORWARDED_FOR'])
    ? $_SERVER['HTTP_X_FORWARDED_FOR'] :
    (isset($_SERVER['HTTP_CLIENT_IP']) ?
    $_SERVER['HTTP_CLIENT_IP'] : $_SERVER['REMOTE_ADDR']));
    $remote_addr = trim($temp_ip[0]);
    $_SERVER['REMOTE_ADDR'] = preg_replace('/[^0-9.:]/', '', $remote_addr );

The code takes some of the common headers and replaces the REMOTE_ADDR
variable, allowing plugins that use the surfer's IP address to see the
surfer's IP rather than the server's IP.

== Screenshots ==

1. Screenshot of the adminstration interface.

== Changelog ==

= 0.8 =
* Added secret handling to WPVarnishPurgeObject, Thanks Kit Westneat

= 0.7 =
* Added purge when post changes from future to publish, Thanks Marcin Pietrzak
* Added purge when theme switched, Thanks dupuis
* Fixed multisite domain mapping purge, Thanks jasonheffner
* Updated Finnish Translation, Thanks timoleinio

= 0.6 =
* Removed plugins_loaded action as it doesnt do what was expected re: Issue
  #12. Thank you Ben Favre, Pothi Kalimuthu and allinwonder

= 0.5 =
* New .vcl to fix purge as per Issue #39, Thanks Ed Cooper

= 0.4 =

* added rule to skip caching 404s in vcl
* WordPress-Varnish UserAgent as per ticket #23
* document use of mod_rpaf or code fix for remote IP, Ticket #36
* clean data rather than reject to fix ticket #31
* added plugins_loaded hook to fix ticket #12

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

= 0.8 =
* Added secret handling to WPVarnishPurgeObject, Thanks Kit Westneat

= 0.7 =
* Added purge when post changes from future to publish, Thanks Marcin Pietrzak
* Added purge when theme switched, Thanks dupuis

= 0.6 =
* Removed plugins_loaded action as it doesnt do what was expected re: Issue
  #12. Thank you Ben Favre, Pothi Kalimuthu and allinwonder

= 0.5 =
* New .vcl to fix purge as per Issue #39, Thanks Ed Cooper

= 0.3 =
* Varnish PURGE configuration must support regex. wp-varnish will
sometimes request with regex for special purges like refreshing
all blog cache and refreshing comments.
