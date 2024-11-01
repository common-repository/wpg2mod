=== WPG2Mod ===
Contributors: edobees
Tags: wordpress gallery, modula image gallery, classicpress, conversion, import, tool
Requires at least: 4.6
Tested up to: 5.1
Requires PHP: 5.6
Stable tag: trunk
License: GPL-3
License URI: http://www.gnu.org/licenses/gpl-3.0.txt

WPG2Mod converts WordPress Galleries (WPG) into Modula galleries.
Optionally WPG shortcodes can be handled as if they were Modula shortcodes.

== Description ==
**WPG2Mod** is **not** an image gallery plugin - but merely a *gallery converter*.

WPG2Mod scans your WordPress blog looking for 'native' WPG shortcodes inside your posts and pages. Based on the WPG attributes (i.e. image IDs) WPG2Mod creates equivalent [Modula galleries](https://wordpress.org/plugins/modula-best-grid-gallery/). The import process starts as soon as you press the '`Import WPG`' button.

= Excluding Post Types =
Specific post types can be excluded from scan and conversion. Usually it makes sense to exclude '`attachment, nav_menu_item, and revisions`' from the conversion process.

= Identifying imported Galleries =
Modula Galleries imported by WPG2Mod can easily be identified by their titles. They start with the prefix '`GEN-MG-`' followed by the post ID of the original WPG post. The status of WPG2Mod generated Modula galleries (=custom post type) is '`draft`'.

= Modula Gallery Settings =
WPG2Mod either uses default settings for the generated galleries or the settings of a specific Modula gallery named '*`setting-template`*'. This specific Modula gallery must have been created by the user before it can be referenced by the import process.

= Supporting the Gallery Migration =
In addition to Modula galleries WPG2Mod creates a so-called '*`Migration Support Post (MSP)`*' having the status '`draft`'. This post is a standard WordPress post that can be located easily on your admin post page. The MSP consists of hyperlinks to all source posts with their associated Modula gallery shortcode. The MSP is intended to support the (manual) replacement of WPG shortcodes by Modula shortcodes inside the source post. 

The MSP will be re-generated with every '`Import WPG`' action.

= On-the-fly Conversion =
As soon as the WPG have been imported WPG2Mod can expand the WPG shortcodes in your posts on-the-fly to a corresponding Modula image gallery. If the '*`On-the-fly WP Gallery Conversion`*' option has been selected all WPG shortcodes will be unfolded as Modula galleries.

= Coexistence of WordPress and Modula Galleries =
Actually, WPG and Modula image galleries can coexist in the same blog. The '`On-the-Fly-Setting`' determines the default behaviour. If set all WPG will be displayed as Modula galleries. 

More fine-tuning can be achieved by an additional attribute for the WPG shortcode introduced by WPG2Mod. A typical WPG shortcode might look like this: \[`gallery ids="109,98,99,100,101,102"  link="file" columns="4"`\]

Adding the attribute '*`mod`*' controls if a Modula gallery should be displayed instead. The value '*`yes`*' enables gallery conversion and '*`no`*' disables it.
The following example \[`gallery ids="109,98,99,100,101,102"  link="file" columns="4" mod="no"`\] **disables** on-the-fly gallery conversion for this WPG.

= Caution =
*'`Import WPG`' can be used several times. Previously generated Modula galleries and the '`Migration Support Post`' will be overwitten, though. If you want to keep a previously generated and fine-tuned Modula gallery you should rename it (give it a new title) before running '`Import WPG`' again.*

== Installation ==
The WPG2Mod plugin can be installed like any other WordPress plugin. No specific procedures apply. 

After a succesful installation you should find the submenu item '*`WPG2Mod`*' inside the '`Modula`' main menu. 

If the Modula gallery plugin should not have been installed (which does not make too much sense) the submenu item '*`WPG2Mod`*' will be located inside the '`Settings`' menu.

= Note =
*WPG2Mod has been tested with Modula Image Gallery versions 2.x.*

== Frequently Asked Questions ==

= Why does WPG2Mod not display a gallery? =

WPG2Mod is **not** an image gallery plugin but merely a tool to convert existing WordPress image galleries into Modula galleries.
After converting WordPress galleries with WPG2Mod you need the free (or premium) version of Modula to display your galleries.

= Does WPG2Mod work with ClassicPress? =

Yes, absolutely! WPG2Mod has been developed on [ClassicPress](https://www.classicpress.net/).

= Does WPG2Mod work with Gutenberg Gallery Blocks? =

No. Please let me know, if there is an interest. 

== Screenshots ==

1. WPG2Mod's options and settings page.

2. WPG2Mod generated Modula Galleries with 'setting-template'.

3. The Migration Support Post.

== Changelog ==
= 1.0.1 =
* Allow editors (no more admins only) to use WPG2Mod and modify settings.
* Introduced on-the-fly creation of Modula Galleriies from WPG shortcodes that have not been imported before.
= 1.0.0 =
* Initial Release 
