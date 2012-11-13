=== Mailer ===
Tags: mail, email, throttling, hostgator, dreamhost, godaddy, mass email, smtp, sendgrid
Requires at least: 3.0.0
Tested up to: 3.4.2
Stable tag: trunk
Donate link: http://www.satollo.net/donations

Mailer throttles emails from WordPress to keep the email flow within provider limits. SMTP ready.

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

= 1.0.0 =

* First release

