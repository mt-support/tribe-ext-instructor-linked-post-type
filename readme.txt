=== The Events Calendar Extension: Instructor Linked Post Type ===
Contributors: ModernTribe
Donate link: http://m.tri.be/29
Tags: events, calendar
Requires at least: 4.7.0
Tested up to: 4.9.4
Requires PHP: 5.2.4
Stable tag: 1.0.1
License: GPL version 3 or any later version
License URI: https://www.gnu.org/licenses/gpl-3.0.html

A boilerplate/starter extension to implement a custom Linked Post Type for you to use as-is or fork. See this plugin file's code comments for forking instructions.

== Description ==

A boilerplate/starter extension for you to use as-is or fork.

Used as-is, an "Instructor" custom post type will be created and linked to The Events Calendar's Events, like Organizers are, and basic output will be added to the Single Event Page. See this plugin file's code comments for forking instructions.

== Installation ==

Install and activate like any other plugin!

* You can upload the plugin zip file via the *Plugins ‣ Add New* screen
* You can unzip the plugin and then upload to your plugin directory (typically _wp-content/plugins)_ via FTP
* Once it has been installed or uploaded, simply visit the main plugin list and activate it

== Frequently Asked Questions ==

= Where can I find more extensions? =

Please visit our [extension library](https://theeventscalendar.com/extensions/) to learn about our complete range of
extensions for The Events Calendar and its associated plugins.

= What if I experience problems? =

We're always interested in your feedback and our [premium forums](https://theeventscalendar.com/support-forums/) are the
best place to flag any issues. Do note, however, that the degree of support we provide for extensions like this one
tends to be very limited.

== Changelog ==

= 1.0.1 2018-04-13 =

* Added `GitHub Plugin URI` to the plugin header to enable automatic updates in the future. REMOVE THIS IF YOU FORK this extension instead of using it as-is
* Linked posts now output in the same order as the wp-admin drag-and-drop order instead of alphabetically
* This functionality requires The Events Calendar (TEC) version 4.6.14 or later, but this extension can still be used with TEC 4.3.1+
* Events with Instructors from prior to this update will need to be re-saved in order to set the meta key that handles the ordering

= 1.0.0 2018-03-16 =

* Initial release
* Known issue: The Single Instructor pages do not have Previous Events or Next Events navigation. This is the same as Single Organizer pages.