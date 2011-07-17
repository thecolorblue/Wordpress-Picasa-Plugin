=== Plugin Name ===
Contributors: wott
Donate link: http://wott.info/donate/
Tags: picasa, photo, gallery, image, album, highslide, lightbox, private, wpmu, user, sidewide
Requires at least: 2.8
Tested up to: 3.2
Stable tag: trunk

Browse, search and select photos from any Picasa Web album and add them to your post/pages.

== Description ==

You can browse Picasa Web album and select image to insert or select several images to the gallery

*	Use Picasa user for get albums ( username can be stored in settings )
*	Show albums cover and name for get images.
*	Images from album with caption or filename for selection
* 	Select and insert single image or banch for gallery. **Enhanced**
*	**Private** Picasa albums after granting access via Google service
*	**Wordpress MU** support - sidewide activation, users, roles
*   Gallery shortcodes for selected images or for get all images from Picasa album

Additionaly setting is managing: 

*	Image link: none, direct, Picasa image page, **thickbox**,**lightbox** and **highslide** with gallery 
*	Sorting images in dialog and in inserted gallery
*	Caption under image or/and in image/link title
*	Alignment for images and gallery 
*	Additional style or CSS classes for images and gallery
*	Define **Roles** which capable to use the plugin
*	Switch from blog to **user level** for store Picasa user and private access token  

And by design:

*	Support native Wordpress image and link dialog for alignment, caption, description, style and CSS class
*	Thumbnail images size defined in Wordpress native properties
*	Multilanguage support

== Installation ==

1. Upload `picasa-express` folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Use Setting link under plugin name or menu option in the 'Settings' -> 'Picasa Express x2' and set user name and other parameters. If you need **private albums** use link under username to request access from Google.
4. In the post or page edit page find icon in the 'Upload/Insert' section above edit window and click it for dialog
5. Select album, select image and press 'Insert' button. Find image or html code in the edit window.

== Frequently Asked Questions ==

= How I can select several images for gallery =

In the album's images you have to press button with 'Image' button. The 'Gallery' will appear on the button and you can select several images. This can be happen if you use Thickbox, Lightbox or Highslide external libraries.

By default images inserted in the displayed order. If you need control the order in gallery - enable 'Selection order'.

= I want private albums browsing =

On Setting page press link under username. You will be redirected to Google for grant access. If you press "Grant access" button you will be returned to settings page, but access will be granted.

After you will see in dialog albums including public and private.

= I try to grant access to my blog on Google site but receive error 'The site "..." has not been registered' =

It's Google Auth system bug. I raise the ticket, but still not receive the answer.

Good news that I know the workaround. You have to register your site on [Google ManageDomains](https://www.google.com/accounts/ManageDomains) - fill 'Target URL path prefix' by `http://your.site/wp-content/plugins/picasa-express-x2/picasa-express-2.php?authorize` ( do not forget adopt domain and add path to the blog if your blog is not on the root of domain ), put the description of your site and save. You will receive OAuth Consumer Key/Secret - don't worry about. Just repeat the authorization process again.

= I revoke access to my server on Google site, and I plugin doesn't work =

On revoking access Google will not inform the server. If you revoke access you have to clean the option 'pe2_token' in the DB.

*	for blog level access - clear token in options, fe by SQL: `delete from wp_options where option_name='pe2_token'`. Take care about 'wp_' prefix for table name.
*	for user level access - just change Picasa username to something and return back. User's token cleared by user changing. 

= I want another feature =

Go to my page [Garbage Collector 2.0](http://wott.info/picasa-express/ "Picasa Express x2 home page") and write in the comment you wishes. I will review it under next release planning. Of course if you donate me I make feature faster and take attention for your message, but I build plugin for my wife then for community.

= I start dialog but user always 'invalid' =

Open the Picasa and select album. In the URL you can find you username like 'http://picasaweb.google.com/username/...'.

If you are sure about correct Picasa username, please check you site PHP preferences - some of the hosters disable connect functions and Wordpress can not request another sites. 

If you revoke access to private albums via Google site - see message above 
 
= Plugin has a bug. How I can fix it ? =

Contact me on [Garbage Collector 2.0](http://wott.info/picasa-express/ "Picasa Express x2 home page")

== Screenshots ==

1. Albums thumbnail
you can change Picasa user or select the album
2. Images from selected album
you can return to the Picasa albums, select image to insert or switch to gallery and select images to the gallery
3. Plugin settings


== Changelog ==

= 1.0 =
* First public release

= 1.1 =
* Remove STATIC - should work with old PHP version. Optimize hooks and setting. Keep settings after deactivation.
* Change Picasa request method. Remove WP cache - changes displayed immediatelly.
* Add sorting ( date, title and file name , asc and desc ) for images in the dialog in settings. Without sorting should be displayed as in PicasaWeb.
* Add ordering for images in gallery ( by clicking )
* Add **Highslide** support

= 1.2 =
* Finally make compatible with old PHP versions
* Access to private albums via granting on Google page. See FAQ for more details 
* Reload button in dialog to retreive last changes

= 1.3 =
* Define roles which capable to use the plugin
* Switch from blog to user level for store Picasa user and private access token 
* Wordpress MU support - sidewide activation, users, roles

= 1.4 =
* Some code was re-factored to be more extensible
* Warning for non standard image library. Help and links. 
* Donate banner and "power by" link in the footer (of course you can disable link and banner in the settings)
* New smart size for thumbnails
* Options on fly
* Insert Picasa album by shortcode

= 1.5 =
* Save last state
* Can limit the big size of images
* Revoke access to private albums from settings

* avoid some warnings for PHP4
* Add error handling in several cases
* remove SSL verification ( some hosts report the problem with ssl verification - thanks to streetdaddy)
* increase timeout for connection with Google Picasa
* add Picasa username test in settings
* Envelop html special chars in titles

= 1.5.2 =
Wordpress 3.2 fixes

= todo =
* Selection enhancement - use mouse click mods: Shift (for lines), Ctrl (for columns) and Ctrl-A for all images
* Recently uploaded images
* Add the button to Visual toolbar of Tiny editor in full screen mode 

== Upgrade Notice ==

= 1.0 =
This is first public release. 

= 1.1 =
New options is in settings, but defaults make behaviour as before.

= 1.2 = 
Access to private albums via AuthSub.
Reload button in dialog

= 1.3 = 
By this version new capability is added to manage plugin access. By updating, take a look into setting and check roles who need the plugin access and **save settings** in any case.

You can additionally use user level access to Picasa albums - when Picasa username ( and private album access token ) defined for every user. Users who have administartive privelegies automatically get blog Picasa username and token on **first profile access**.

Works with Wordpress MU - can be activated sidewide or per blog. In both cases every blog has own data as described above.

= 1.4 =
Some code is refactored to be more correct and extensible. Please let me know if you find something wrong. Also I change the URLs for thumnails, but can't find documentations for this changes. Let me know if your thumbnails will not shows.

A lot of enhacement is in. I hope so you will work easely and fast with images on your blog. The full feature list and description is in [plugin version page](http://wott.info/picasa-express/new-version-1-4-of-picasa-express-plugin-for-wordpress/ "Picasa Express x2 v1.4")

Developers can add button for use plugin in form like custom_posts. For details please visit [my site](http://wott.info/picasa-express/new-version-1-4-of-picasa-express-plugin-for-wordpress/ "Picasa Express x2 v1.4")

= 1.5 =
Originally I plan to release Save Last State feature.
But I receive a lot of questions and have to add several small changes to prevent most issued problems.

= 1.5.2 =
Some fixes for WP 3.2
Remove depricated function and so on



