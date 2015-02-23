=== Mailer ===
Tags: mail, email, throttling, mass email, smtp, queue
Requires at least: 3.0.0
Tested up to: 4.1
Stable tag: trunk
Donate link: http://www.satollo.net/donations
Contributors: satollo

Mailer queues and throttles emails from WordPress to keep the email flow within provider limits. SMTP ready.

== Description ==

Mailer intercepts emails sent from your blog, stores them on disk and
delivers them slowly to never hit the provider limits and risk a ban.

Mailer is compatible with WordPress and every plugin that use the wp_mail()
function.

Some features:

* configurable delivering speed
* alternative sender email and name settings
* SMTP ready
* email grouping (extension for 3rd party plugins)
* email priority (extension for 3rd party plugins)

Offial page: [Mailer](http://www.satollo.net/plugins/mailer).

== Installation ==

1. Put the plugin folder into [wordpress_dir]/wp-content/plugins/
2. Go into the WordPress admin interface and activate the plugin
3. Optional: go to the options page and configure the plugin

== Frequently Asked Questions ==

See [Mailer official page](http://www.satollo.net/plugins/mailer).

== Screenshots ==

None.

== Changelog ==

= 1.4.4 =

* Fix for PHP 5.4+

= 1.4.3 =

* Fixes

= 1.4.2 =

* Fixed few notices

= 1.4.1 =

* Compatibility checks
* Removed the jQuery cookie on jQuery UI tabs

= 1.4.0 =

* Ported all the pro features

= 1.3.3 =

* new admin panel

= 1.3.2 =

* a little fix to avoid double email when the blog is called from synchronized crons
* new mailer_set and mailer_reset functions to drive the Mailer behavior

= 1.3.1 =

* removed unused code

= 1.3.0 =

* unserializable exception managed
* new php mailer exceptions (php 5) controlled

= 1.2.0 =

* made compatible with new phpmailer library which is mo more compatible with its older version...
* removed the experimental bounce checking, it was not realiable

= 1.1.0 =

* now free!

= 1.0.4 =

* new detection rule

= 1.0.3 =

* fixed a detection rule

= 1.0.2 =

* added a new detection rule

= 1.0.1 =

* Email extraction: removed the greater than and lower than characters
