=== WordPress Varnish ===
Contributors: pkhamre, wfelipe
Donate link: http://github.com/pkhamre/wp-varnish
Tags: cache, caching, performance, varnish, purge, speed
Requires at least: 2.9.2
Tested up to: 2.9.2
Stable tag: 0.2

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

== Screenshots ==

1. Screenshot of the adminstration interface.

== Changelog ==

= 0.1 =
* Initial release.

== Upgrade Notice ==

= 0.1 =
* Lorem ipsum.
