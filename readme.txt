=== Solr for WordPress ===
Contributors: mattweber
Author URI: http://www.mattweber.org
Plugin URI: https://github.com/mattweber/solr-for-wordpress
Donate link: http://www.mattweber.org
Tags: solr, search, search results, search integration, custom search 
Requires at least: 3.0
Tested up to: 3.2.1
Stable tag: 0.5.1

A WordPress plugin that replaces the default WordPress search with Solr.

== Description ==

A WordPress plugin that replaces the default WordPress search with Solr.  Features include:

*	Index pages and posts
*	Enable faceting on fields such as tags, categories, author, and page type.
* Indexing and faceting on custom fields
* Multisite support
*	Treat the category facet as a taxonomy
*	Add special template tags so you can create your own custom result pages to match your theme.
* Completely replaces default WordPress search, just install and configure.
* Completely integrated into default WordPress theme and search widget.
*	Configuration options allow you to select pages to ignore, features to enable/disable, and what type of result information you want output.
* i18n Support
* Multi server/core support

    Note that this plugin requires you to have an instance of Solr using a schema with the following fields: id, permalink, title, content, numcomments, categories, categoriessrch, tags, tagssrch, author, type, and text.  The facet fields (categories, tags, author, and type) should be string fields.  You can make tagssrch and categoriessrch of any type you want as they are used for general searching.  The plugin is distributed with a Solr schema you can use at `solr-for-wordpress/schema.xml`.


== Installation ==

1. Upload the `solr-for-wordpress` folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Configure the plugin with the hostname, port, and URI path to your Solr installation.
4. Load all your posts and/or pages via the "Load All Posts" button in the settings page.

= Custom Theme Integration =
1. Create a new theme file called "s4w_search.php".
2. Insert your markup, use template methods s4w_search_form() and s4w_search_results() to insert the search box and results respectively.
3. Add result styling to your theme css file, see `solr-for-wordpress/template/search.css` for an example.
4. You can use the search widget in your sidebar for search, or use a custom search box that submits the query in the parameter "s".


== Frequently Asked Questions ==

= What version of WordPress does Solr for WordPress work with? =

Solr for WordPress works with WordPress 3.0 and greater.

= What version of Solr is required. =

Solr 1.4 or greater.

= Can I enable/disable specific facets. =

Yes, from the settings page.  Uncheck the "Facet on FIELD" option, for the FIELD you don't want a facet for.

= Do I need to run my own Solr server to use this plugin? =

No, there are Solr hosting providers that will setup and manager Solr for you.  As long as they allow custom schema's and support the 
standard Solr http api, then the plugin should work.  Some sites:

http://www.mysolrserver.com/
http://www.websolr.com/
http://powcloud.com/

= Can I run a local instance of Solr for testing? =

Yes, you may grab a copy from the apache website: http://www.apache.org/dist/lucene/solr/
Since it runs on java solr is platform independent, just make sure you have java install So go ahead and Download the .tgz/.zip file (there is no difference).
1) Extract the files you have downloaded
2) Copy the schema file (schema.xml) from this plugin to the folder you extracted 'apache-solr-*/examples/solr/conf/' (yes overwrite the file there)
3) Open up terminal and then go to where you extracted the files and inside './apache-solr-*/examples' run the command "java -jar start.jar"

For any further information please the README that comes with solr.

= What is a taxonomy =

A taxonomy is a hierarchal facet.  This means that your subcategories will be treated as a child to the parent category instead of an individual facet.  

Here is what an example category taxonomy look like:

`
-States
---California
------Los Angeles
---New York
------New York
`

Here is that same category not treating it as a taxonomy:

`
-States
-California
-Los Angeles
-New York
-New York
`

As you can see, treating it as a taxonomy is much better.

= I was treating the category as a taxonomy disabled it, now my category facet looks weird =

You need to delete and re-index your data.  

= How do I know what html tags to style in my custom theme =

See `solr-for-wordpress/template/search.css` for an example, or view the source of the search results.

= Can I create a custom search page such as http://www.myblog.com/search/? =

Yes, it is fairly trivial as well.  Follow the steps for custom theme integration.  At the top of that page, insert a comment similar to the following:

<?php
/*
Template Name: Search
*/
?>

Login to the WordPress admin GUI, select add new page, set the title of the page to "Search".  On the right hand side you will see a drop-down box that says "Template", click that and select "Search".  Leave the content of the page blank and publish the page.  

You will have have your "/search/" page.

= I need the output of this plugin in a different language =

Solr for WordPress is internationalized.  There is supplied .pot file that you can use for translations.  See http://codex.wordpress.org/Translating_WordPress for more details.

= How do I find out the page or post id to exclude? =

Login to the WordPress admin, select pages, click the page you want to exclude.  The post id is what you are looking for, you can find it in the titlebar as the &post= parameter.  In this example, the page id is 22, http://www.yourblog.com/wp-admin/page.php?action=edit&post=22.


== Screenshots ==

1. Configuration Page
2. Example of results page in default WordPress Theme

== Credits ==

Dominique Bejean for custom field support and testing.
Eric Pugh multi server support.
Dustin Rue - fixes for batch import and multisite.

