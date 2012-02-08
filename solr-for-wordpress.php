<?php
/*
Plugin Name: Solr for WordPress
Plugin URI: http://wordpress.org/extend/plugins/solr-for-wordpress/
Donate link: http://www.mattweber.org
Description: Indexes, removes, and updates documents in the Solr search engine.
Version: 0.5.1
Author: Matt Weber
Author URI: http://www.mattweber.org
*/
/*  
    Copyright (c) 2011 Matt Weber

    Permission is hereby granted, free of charge, to any person obtaining a copy
    of this software and associated documentation files (the "Software"), to deal
    in the Software without restriction, including without limitation the rights
    to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
    copies of the Software, and to permit persons to whom the Software is
    furnished to do so, subject to the following conditions:

    The above copyright notice and this permission notice shall be included in
    all copies or substantial portions of the Software.

    THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
    IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
    FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
    AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
    LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
    OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
    THE SOFTWARE.
*/

global $wp_version, $version;

$version = '0.5.1';

$errmsg = __('Solr for WordPress requires WordPress 3.0 or greater. ', 'solr4wp');
if (version_compare($wp_version, '3.0', '<')) {
    exit ($errmsg);
}

require_once(dirname(__FILE__) . '/SolrPhpClient/Apache/Solr/Service.php');

function s4w_get_option() {
    $indexall = FALSE;
    $option = 'plugin_s4w_settings';
    if (is_multisite()) {
        $plugin_s4w_settings = get_site_option($option);
        $indexall = $plugin_s4w_settings['s4w_index_all_sites'];
    }
    
    if ($indexall) {
        return get_site_option($option);
    } else {
        return get_option($option);
    }
}

function s4w_update_option($optval) {
    $indexall = FALSE;
    $option = 'plugin_s4w_settings';
    if (is_multisite()) {
       $plugin_s4w_settings = get_site_option($option);
       $indexall = $plugin_s4w_settings['s4w_index_all_sites'];
    }
    
    if ($indexall) {
        update_site_option($option, $optval);
    } else {
        update_option($option, $optval);
    }
}
/**
 * Connect to the solr service
 * @param $server_id string/int its either master or array index
 * @return solr service object
 */
function s4w_get_solr($server_id = NULL) {
  # get the connection options
  $plugin_s4w_settings = s4w_get_option();
  //if the provided server_id does not exist use the default id 'master'
  if(!$plugin_s4w_settings['s4w_server']['info'][$server_id]['host']) {
    $server_id = $plugin_s4w_settings['s4w_server']['type']['update'];
  }
  $host = $plugin_s4w_settings['s4w_server']['info'][$server_id]['host'];
  $port = $plugin_s4w_settings['s4w_server']['info'][$server_id]['port'];
  $path = $plugin_s4w_settings['s4w_server']['info'][$server_id]['path'];
  # double check everything has been set
  if ( ! ($host and $port and $path) ) {
    syslog(LOG_ERR,"host, port or path are empty, host:$host, port:$port, path:$path");
    return NULL;
  }

  # create the solr service object
  $solr = new Apache_Solr_Service($host, $port, $path);

  return $solr;
}


/**
 * check if the server by pinging it
 * @param server if wanting to ping a different 
 *        server than default provide name
 * @return boolean
 */
function s4w_ping_server($server_id = NULL) {
  $solr = s4w_get_solr($server);
  $ping = FALSE;
  # if we want to check if the server is alive, ping it
  if ($solr->ping()) {
    $ping = TRUE;
  }
  return $ping;
}

function s4w_build_document( $post_info, $domain = NULL, $path = NULL) {
    global $blog_id;
    global $current_blog;

    $doc = NULL;
    $plugin_s4w_settings = s4w_get_option();
    $exclude_ids = $plugin_s4w_settings['s4w_exclude_pages'];
    $categoy_as_taxonomy = $plugin_s4w_settings['s4w_cat_as_taxo'];
    $index_comments = $plugin_s4w_settings['s4w_index_comments'];
    $index_custom_fields = $plugin_s4w_settings['s4w_index_custom_fields'];
    
    if ($post_info) {
        
        # check if we need to exclude this document
        if (is_multisite() && in_array(substr(site_url(),7) . $post_info->ID, (array)$exclude_ids)) {
            return NULL;
        } else if ( !is_multisite() && in_array($post_info->ID, (array)$exclude_ids) ) {
            return NULL;
        }
        
        $doc = new Apache_Solr_Document();
        $auth_info = get_userdata( $post_info->post_author );
        
        # wpmu specific info
        if (is_multisite()) {
            // if we get here we expect that we've "switched" what blog we're running
            // as
            
            if ($domain == NULL)
                $domain = $current_blog->domain;
            
            if ($path == NULL)
                $path = $current_blog->path;
            
            
            $blogid = get_blog_id_from_url($domain, $path);
            $doc->setField( 'id', $domain . $path . $post_info->ID );
            $doc->setField( 'permalink', get_blog_permalink($blogid, $post_info->ID));
            $doc->setField( 'blogid', $blogid );
            $doc->setField( 'blogdomain', $domain );
            $doc->setField( 'blogpath', $path );
            $doc->setField( 'wp', 'multisite');
        } else {
            $doc->setField( 'id', $post_info->ID );
            $doc->setField( 'permalink', get_permalink( $post_info->ID ) );
            $doc->setField( 'wp', 'wp');
        }
        
        $numcomments = 0;
        if ($index_comments) {
            $comments = get_comments("status=approve&post_id={$post_info->ID}");
            foreach ($comments as $comment) {
                $doc->addField( 'comments', $comment->comment_content );
                $numcomments += 1;
            }
        }
           
        $doc->setField( 'title', $post_info->post_title );
        $doc->setField( 'content', strip_tags($post_info->post_content) );

        // rawcontent strips out characters lower than 0x20
        $doc->setField( 'rawcontent', strip_tags(preg_replace('/[^(\x20-\x7F)\x0A]*/','', $post_info->post_content)));

        // contentnoshortcodes also strips characters below 0x20 but also strips shortcodes
        // used in WP to add images or other content, useful if you're pulling this data
        // into another system
        //
        // For example
        //   [caption id="attachment_92495" align="alignright" width="160" caption="Duane Sand"][/caption] FARGO - Republican U.S. Senate...
        // 
        // Will become
        //   FARGO - Republican U.S. Senate...
        $doc->setField( 'contentnoshortcodes', strip_tags(preg_replace('/[^(\x20-\x7F)\x0A]*/','', strip_tags(strip_shortcodes($post_info->post_content)))));
        $doc->setField( 'numcomments', $numcomments );
        $doc->setField( 'author', $auth_info->display_name );
        $doc->setField( 'author_s', get_author_posts_url($auth_info->ID, $auth_info->user_nicename));
        $doc->setField( 'type', $post_info->post_type );
        $doc->setField( 'date', s4w_format_date($post_info->post_date_gmt) );
        $doc->setField( 'modified', s4w_format_date($post_info->post_modified_gmt) );
        $doc->setField( 'displaydate', $post_info->post_date );
        $doc->setField( 'displaymodified', $post_info->post_modified );

        $categories = get_the_category($post_info->ID);
        if ( ! $categories == NULL ) {
            foreach( $categories as $category ) {
                if ($categoy_as_taxonomy) {
                    $doc->addField('categories', get_category_parents($category->cat_ID, FALSE, '^^'));
                } else {
                    $doc->addField('categories', $category->cat_name);
                }
            }
        }
        
        //get all the taxonomy names used by wp
        $taxonomies = (array)get_taxonomies(array('_builtin'=>FALSE),'names');
        foreach($taxonomies as $parent) {
          $terms = get_the_terms( $post_info->ID, $parent );
          if ((array) $terms === $terms) {
            //we are creating *_taxonomy as dynamic fields using our schema
            //so lets set up all our taxonomies in that format
            $parent = $parent."_taxonomy";
            foreach ($terms as $term) {
              $doc->addField($parent, $term->name);
            }
          }
        }
        
        $tags = get_the_tags($post_info->ID);
        if ( ! $tags == NULL ) { 
            foreach( $tags as $tag ) {
                $doc->addField('tags', $tag->name);
            }
        }
        
        if (count($index_custom_fields)>0 && count($custom_fields = get_post_custom($post_info->ID))) {
        	foreach ((array)$index_custom_fields as $field_name ) {
          	$field = (array)$custom_fields[$field_name];
    				foreach ( $field as $key => $value ) {
    					$doc->addField($field_name . '_str', $value);
    					$doc->addField($field_name . '_srch', $value);
    				}
        	}
        }
    } else {
        // this will fire during blog sign up on multisite, not sure why
        _e('Post Information is NULL', 'solr4wp');
    }
    syslog(LOG_ERR, "built document for $blog_id - $domain$path with title " .  $post_info->post_title . 
      " and status of " . $post_info->post_status);
    return $doc;
}

function s4w_format_date( $thedate ) {
    $datere = '/(\d{4}-\d{2}-\d{2})\s(\d{2}:\d{2}:\d{2})/';
    $replstr = '${1}T${2}Z';
    return preg_replace($datere, $replstr, $thedate);
}

function s4w_post( $documents, $commit = TRUE, $optimize = FALSE) { 
    try {
        $solr = s4w_get_solr();
        if ( ! $solr == NULL ) {
            
            if ($documents) {
                syslog(LOG_ERR,"posting " . count($documents) . " documents for blog:" . get_bloginfo('wpurl'));
                $solr->addDocuments( $documents );
            }
            
            if ($commit) {
               syslog(LOG_ERR,"telling Solr to commit");
                $solr->commit();
            }
            
            if ($optimize) {
                $solr->optimize();
            }
        }
        else {
          syslog(LOG_ERR, "failed to get a solr instance created");
        }
    } catch ( Exception $e ) {
        syslog(LOG_ERR,"ERROR: " . $e->getMessage());
        //echo $e->getMessage();
    }
}

function s4w_optimize() {
    try {
        $solr = s4w_get_solr();
        if ( ! $solr == NULL ) {
            $solr->optimize();
        }
    } catch ( Exception $e ) {
        syslog(LOG_ERR,$e->getMessage());
    }
}

function s4w_delete( $doc_id ) {
    try {
        $solr = s4w_get_solr();
        if ( ! $solr == NULL ) {
            $solr->deleteById( $doc_id );
            $solr->commit();
        }
    } catch ( Exception $e ) {
        syslog(LOG_ERR,$e->getMessage());
    }
}

function s4w_delete_all() {
    try {
        $solr = s4w_get_solr();
        if ( ! $solr == NULL ) {
            $solr->deleteByQuery( '*:*' );
            $solr->commit();
        }
    } catch ( Exception $e ) {
        echo $e->getMessage();
    }
}

function s4w_delete_blog($blogid) {
    try {
        $solr = s4w_get_solr();
        if ( ! $solr == NULL ) {
            $solr->deleteByQuery( "blogid:{$blogid}" );
            $solr->commit();
        }
    } catch ( Exception $e ) {
        echo $e->getMessage();
    }
}

function s4w_load_blog_all($blogid) {
    global $wpdb;
    $documents = array();
    $cnt = 0;
    $batchsize = 10;
    
    $bloginfo = get_blog_details($blogid, FALSE);
   
    if ($bloginfo->public && !$bloginfo->archived && !$bloginfo->spam && !$bloginfo->deleted) {
        $postids = $wpdb->get_results("SELECT ID FROM {$wpdb->base_prefix}{$blogid}_posts WHERE post_status = 'publish';");
        for ($idx = 0; $idx < count($postids); $idx++) {
            $postid = $ids[$idx];
            $documents[] = s4w_build_document( get_blog_post($blogid, $postid->ID), $bloginfo->domain, $bloginfo->path );
            $cnt++;
            if ($cnt == $batchsize) {
                s4w_post($documents);
                $cnt = 0;
                $documents = array();
            }
        }
        
        if ($documents) {
            s4w_post($documents);
        }
    }
}

function s4w_handle_modified( $post_id ) {
    global $current_blog;
    $post_info = get_post( $post_id );
    $plugin_s4w_settings = s4w_get_option();
    $index_pages = $plugin_s4w_settings['s4w_content']['index']['page'];
    $index_posts = $plugin_s4w_settings['s4w_content']['index']['post'];
    
    s4w_handle_status_change( $post_id, $post_info );

    if (($index_pages && $post_info->post_type == 'page' && $post_info->post_status == 'publish') || 
        ($index_posts && $post_info->post_type == 'post' && $post_info->post_status == 'publish')) {
        
        # make sure this blog is not private or a spam if indexing on a multisite install
        if (is_multisite() && ($current_blog->public != 1 || $current_blog->spam == 1 || $current_blog->archived == 1)) {
            return;
        }
        
        $docs = array();
        $doc = s4w_build_document( $post_info , $current_blog->domain , $current_blog->path );
        if ( $doc ) {
            $docs[] = $doc;
            s4w_post( $docs );
        }
    }
}

function s4w_handle_status_change( $post_id, $post_info = null ) {
    global $current_blog;
	
	if ( ! $post_info ){
    	$post_info = get_post( $post_id );
	}
	
    $plugin_s4w_settings = s4w_get_option();
    $private_page = $plugin_s4w_settings['s4w_private_page'];
    $private_post = $plugin_s4w_settings['s4w_private_post'];
    
    if ( ($private_page && $post_info->post_type == 'page') || ($private_post && $post_info->post_type == 'post') ) {
	 	/**
		 * We need to check if the status of the post has changed.
	     * Inline edits won't have the prev_status of original_post_status,
	     * instead we check of the _inline_edit variable is present in the $_POST variable
	    */
	    if ( ($_POST['prev_status'] == 'publish' || $_POST['original_post_status'] == 'publish' || 
				( isset( $_POST['_inline_edit'] ) && !empty( $_POST['_inline_edit']) ) )  && 
				($post_info->post_status == 'draft' || $post_info->post_status == 'private') ) {
	
			if (is_multisite()) {
                s4w_delete( $current_blog->domain . $current_blog->path . $post_info->ID );
          	} else {
         		s4w_delete( $post_info->ID );
          	}
        }
    }
}


function s4w_handle_delete( $post_id ) {
    global $current_blog;
    $post_info = get_post( $post_id );
    syslog(LOG_ERR,"deleting post titled '" . $post_info->post_title . "' for " . $current_blog->domain . $current_blog->path);
    $plugin_s4w_settings = s4w_get_option();
    $delete_page = $plugin_s4w_settings['s4w_delete_page'];
    $delete_post = $plugin_s4w_settings['s4w_delete_post'];
    
    if ( ($delete_page && $post_info->post_type == 'page') || ($delete_post && $post_info->post_type == 'post') ) {
        if (is_multisite()) {
            s4w_delete( $current_blog->domain . $current_blog->path . $post_info->ID );
        } else {
            s4w_delete( $post_info->ID );
        }
    }
}

function s4w_handle_deactivate_blog($blogid) {
    s4w_delete_blog($blogid);
}

function s4w_handle_activate_blog($blogid) {
    s4w_apply_config_to_blog($blogid);
    s4w_load_blog_all($blogid);
}

function s4w_handle_archive_blog($blogid) {
    s4w_delete_blog($blogid);
}

function s4w_handle_unarchive_blog($blogid) {
    s4w_apply_config_to_blog($blogid);
    s4w_load_blog_all($blogid);
}

function s4w_handle_spam_blog($blogid) {
    s4w_delete_blog($blogid);
}

function s4w_handle_unspam_blog($blogid) {
    s4w_apply_config_to_blog($blogid);
    s4w_load_blog_all($blogid);
}

function s4w_handle_delete_blog($blogid) {
    s4w_delete_blog($blogid);
}

function s4w_handle_new_blog($blogid) {
    s4w_apply_config_to_blog($blogid);
    s4w_load_blog_all($blogid);
}

/**
 * This function indexes all the different content types.
 * This does not include attachments and revisions
 *
 * @param $prev 
 * @param $type what content to index: post type machine name or all content.
 * @return string (json reply)
 */
function s4w_load_all_posts($prev, $type = 'all') {
    global $wpdb, $current_blog, $current_site;
    $documents = array();
    $cnt = 0;
    $batchsize = 250;
    $last = "";
    $found = FALSE;
    $end = FALSE;
    $percent = 0;
    
    //multisite logic is decided s4w_get_option
    $plugin_s4w_settings = s4w_get_option();
    $blog_id = $blog->blog_id;
    
    //retrieve the post types that can be indexed
    $indexable_content = $plugin_s4w_settings['s4w_content']['index'];
    $indexable_type = array_keys($indexable_content);
    //if the provided $type is not allowed to be index, lets stop
    if (!in_array($type,$indexable_type) && $type != 'all') { 
      return false;
    }
    //lets setup our where clause to find the appropriate posts
    $where_and = ($type == 'all') ?"AND post_type IN ('".implode("', '", $indexable_type). "')" : " AND post_type = '$type'";
    if ($plugin_s4w_settings['s4w_index_all_sites']) {

        // there is potential for this to run for an extended period of time, depending on the # of blgos
        syslog(LOG_ERR,"starting batch import, setting max execution time to unlimited"); 
        ini_set('memory_limit', '1024M');
        set_time_limit(0);

        // get a list of blog ids
        $bloglist = $wpdb->get_col("SELECT * FROM {$wpdb->base_prefix}blogs WHERE spam = 0 AND deleted = 0", 0);
        syslog(LOG_ERR,"pushing posts from " . count($bloglist) . " blogs into Solr");
        foreach ($bloglist as $bloginfo) {

            // for each blog we need to import we get their id 
            // and tell wordpress to switch to that blog
            $blog_id = trim($bloginfo);

            syslog(LOG_ERR,"switching to blogid $blog_id");

            // attempt to save some memory by flushing wordpress's cache
            wp_cache_flush();

            // everything just works better if we tell wordpress
            // to switch to the blog we're using, this is a multi-site
            // specific function
            switch_to_blog($blog_id);

            // now we actually gather the blog posts
            
            $postids = $wpdb->get_results("SELECT ID FROM {$wpdb->base_prefix}{$bloginfo}_posts WHERE post_status = 'publish' $where_and ORDER BY ID;");
            $postcount = count($postids);
            syslog(LOG_ERR,"building $postcount documents for " . substr(get_bloginfo('wpurl'),7));
            for ($idx = 0; $idx < $postcount; $idx++) {
                
                $postid = $postids[$idx]->ID;
                $last = $postid;
                $percent = (floatval($idx) / floatval($postcount)) * 100;
                if ($prev && !$found) {
                    if ($postid === $prev) {
                        $found = TRUE;
                    }
                    
                    continue;
                }
                
                if ($idx === $postcount - 1) {
                    $end = TRUE;
                }
                
                // using wpurl is better because it will return the proper
                // URL for the blog whether it is a subdomain install or otherwise
                $documents[] = s4w_build_document( get_blog_post($blog_id, $postid), substr(get_bloginfo('wpurl'),7), $current_site->path );
                $cnt++;
                if ($cnt == $batchsize) {
                    s4w_post( $documents, false, false);
                    s4w_post(false, true, false);
                    wp_cache_flush();
                    $cnt = 0;
                    $documents = array();
                }
            }
            // post the documents to Solr
            // and reset the batch counters
            s4w_post( $documents, false, false);
            s4w_post(false, true, false);
            $cnt = 0;
            $documents = array();
            syslog(LOG_ERR,"finished building $postcount documents for " . substr(get_bloginfo('wpurl'),7));
            wp_cache_flush();
        }

        // done importing so lets switch back to the proper blog id
       restore_current_blog();
    } else {
        $posts = $wpdb->get_results("SELECT ID FROM $wpdb->posts WHERE post_status = 'publish' $where_and ORDER BY ID;" );
        $postcount = count($posts);
        for ($idx = 0; $idx < $postcount; $idx++) {
            $postid = $posts[$idx]->ID;
            $last = $postid;
            $percent = (floatval($idx) / floatval($postcount)) * 100;
            if ($prev && !$found) {
                if ($postid === $prev) {
                    $found = TRUE;
                }
                continue;
            }
            
            if ($idx === $postcount - 1) {
                $end = TRUE;
            }
            $documents[] = s4w_build_document( get_post($postid) );
            $cnt++;
            if ($cnt == $batchsize) {
                s4w_post( $documents, FALSE, FALSE);
                $cnt = 0;
                $documents = array();
                wp_cache_flush();
                break;
            }
        }
    }
    
    if ( $documents ) {
        s4w_post( $documents , FALSE, FALSE);
    }
    
    if ($end) {
        s4w_post(FALSE, TRUE, FALSE);
        printf("{\"type\": \"%s\", \"last\": \"%s\", \"end\": true, \"percent\": \"%.2f\"}", $type, $last, $percent);
    } else {
        printf("{\"type\": \"%s\", \"last\": \"%s\", \"end\": false, \"percent\": \"%.2f\"}", $type, $last, $percent);
    }
}

function s4w_search_form() {
    $sort = $_GET['sort'];
    $order = $_GET['order'];
    $server = $_GET['server'];
    
    
    if ($sort == 'date') {
        $sortval = __('<option value="score">Score</option><option value="date" selected="selected">Date</option><option value="modified">Last Modified</option>');
    } else if ($sort == 'modified') {
        $sortval = __('<option value="score">Score</option><option value="date">Date</option><option value="modified" selected="selected">Last Modified</option>');
    } else {
        $sortval = __('<option value="score" selected="selected">Score</option><option value="date">Date</option><option value="modified">Last Modified</option>');
    }
    
    if ($order == 'asc') {
        $orderval = __('<option value="desc">Descending</option><option value="asc" selected="selected">Ascending</option>');
    } else {
        $orderval = __('<option value="desc" selected="selected">Descending</option><option value="asc">Ascending</option>');
    }
    //if server id has been defined keep hold of it
    if($server) {
      $serverval = '<input name="server" type="hidden" value="'.$server.'" />';
    }
    $form = __('<form name="searchbox" method="get" id="searchbox" action=""><input type="text" id="qrybox" name="s" value="%s"/><input type="submit" id="searchbtn" /><label for="sortselect" id="sortlabel">Sort By:</label><select name="sort" id="sortselect">%s</select><label for="orderselect" id="orderlabel">Order By:</label><select name="order" id="orderselect">%s</select>%s</form>');
    
    printf($form, htmlspecialchars(stripslashes($_GET['s'])), $sortval, $orderval,$serverval);
}

function s4w_search_results() {
    $qry = stripslashes($_GET['s']);
    $offset = $_GET['offset'];
    $count = $_GET['count'];
    $fq = $_GET['fq'];
    $sort = $_GET['sort'];
    $order = $_GET['order'];
    $isdym = $_GET['isdym'];
    $server = $_GET['server'];
    
    $plugin_s4w_settings = s4w_get_option();
    $output_info = $plugin_s4w_settings['s4w_output_info'];
    $output_pager = $plugin_s4w_settings['s4w_output_pager'];
    $output_facets = $plugin_s4w_settings['s4w_output_facets'];
    $results_per_page = $plugin_s4w_settings['s4w_num_results'];
    $categoy_as_taxonomy = $plugin_s4w_settings['s4w_cat_as_taxo'];
    $dym_enabled = $plugin_s4w_settings['s4w_enable_dym'];
    $out = array();
    
    if ( ! $qry ) {
        $qry = '';
    }
    //if server value has been set lets set it up here 
    // and add it to all the search urls henceforth
    if ($server) {
      $serverval = '&server='.$server;
    }
    # set some default values
    if ( ! $offset ) {
        $offset = 0;
    }
    
    # only use default if not specified in post information
    if ( ! $count ) {
        $count = $results_per_page;
    }
        
    if ( ! $fq ) {
        $fq = '';
    }
    
    if ( $sort && $order ) {
        $sortby = $sort . ' ' . $order;
    } else {
        $sortby = '';
        $order = '';
    }
    
    if ( ! $isdym ) {
        $isdym = 0;
    }
    
    $fqstr = '';
    $fqitms = split('\|\|', stripslashes($fq));
    $selectedfacets = array();
    foreach ($fqitms as $fqitem) {
        if ($fqitem) {
            $splititm = split(':', $fqitem, 2);
            $selectedfacet = array();
            $selectedfacet['name'] = sprintf(__("%s:%s"), ucwords(preg_replace('/_str$/i', '', $splititm[0])), str_replace("^^", "/", $splititm[1]));
            $removelink = '';
            foreach($fqitms as $fqitem2) {
                if ($fqitem2 && !($fqitem2 === $fqitem)) {
                    $splititm2 = split(':', $fqitem2, 2);
                    $removelink = $removelink . urlencode('||') . $splititm2[0] . ':' . urlencode($splititm2[1]);
                }
            }
            
            if ($removelink) {
                $selectedfacet['removelink'] = htmlspecialchars(sprintf(__("?s=%s&fq=%s"), urlencode($qry), $removelink));
            } else {
                $selectedfacet['removelink'] = htmlspecialchars(sprintf(__("?s=%s"), urlencode($qry)));
            }
            //if server is set add it on the end of the url
            $selectedfacet['removelink'] .=$serverval;
            
            $fqstr = $fqstr . urlencode('||') . $splititm[0] . ':' . urlencode($splititm[1]);
            
            $selectedfacets[] = $selectedfacet;
        }
    }

    if ($qry) {
        $results = s4w_query( $qry, $offset, $count, $fqitms, $sortby, $server);

        if ($results) {
            $response = $results->response;
            $header = $results->responseHeader;
            $teasers = get_object_vars($results->highlighting);
            $didyoumean = $results->spellcheck->suggestions->collation;
            
            if ($output_info) {
                $out['hits'] = sprintf(__("%d"), $response->numFound);
                $out['qtime'] = sprintf(__("%.3f"), $header->QTime/1000);
                
                if ($didyoumean && !$isdym && $dym_enabled) {
                    $dymout = array();
                    $dymout['term'] = htmlspecialchars($didyoumean);
                    $dymout['link'] = htmlspecialchars(sprintf(__("?s=%s&isdym=1"), urlencode($didyoumean)));
                    //if server is set add it on the end of the url
                    $selectedfacet['removelink'] .=$serverval;
                    $out['dym'] = $dymout.$serverval;
                }   
            }
                        
            if ($output_pager) {      
                # calculate the number of pages
                $numpages = ceil($response->numFound / $count);
                $currentpage = ceil($offset / $count) + 1;
                $pagerout = array();
                
                if ($numpages == 0) {
                    $numpages = 1;
                }
                
                foreach (range(1, $numpages) as $pagenum) {
                    if ( $pagenum != $currentpage ) {
                        $offsetnum = ($pagenum - 1) * $count;
                        $pageritm = array();
                        $pageritm['page'] = sprintf(__("%d"), $pagenum);
                        $pagerlink = sprintf(__("?s=%s&offset=%d&count=%d"), urlencode($qry), $offsetnum, $count);
                        if($fqstr) $pagerlink .= '&fq=' . $fqstr;
                        $pageritm['link'] = htmlspecialchars($pagerlink);
                        //if server is set add it on the end of the url
                        $selectedfacet['removelink'] .=$serverval;
                        $pagerout[] = $pageritm;
                    } else {
                        $pageritm = array();
                        $pageritm['page'] = sprintf(__("%d"), $pagenum);
                        $pageritm['link'] = "";
                        $pagerout[] = $pageritm;
                    }
                }
                
                $out['pager'] = $pagerout;
            }
            
            if ($output_facets) {
                # handle facets
                $facetout = array();

                if($results->facet_counts) {
                    foreach ($results->facet_counts->facet_fields as $facetfield => $facet) {
                        if ( ! get_object_vars($facet) ) {
                            continue;
                        }
                        
                        $facetinfo = array();
                        $facetitms = array();
                        $facetinfo['name'] = ucwords(preg_replace('/_str$/i', '', $facetfield));
                        
                        # categories is a taxonomy
                        if ($categoy_as_taxonomy && $facetfield == 'categories') {
                            # generate taxonomy and counts
                            $taxo = array();
                            foreach ($facet as $facetval => $facetcnt) {
                                $taxovals = explode('^^', rtrim($facetval, '^^'));
                                $taxo = s4w_gen_taxo_array($taxo, $taxovals);
                            }
                            
                            $facetitms = s4w_get_output_taxo($facet, $taxo, '', $fqstr.$serverval, $facetfield);
                            
                        } else {
                            foreach ($facet as $facetval => $facetcnt) {
                                $facetitm = array();
                                $facetitm['count'] = sprintf(__("%d"), $facetcnt);
                                $facetitm['link'] = htmlspecialchars(sprintf(__('?s=%s&fq=%s:%s%s', 'solr4wp'), urlencode($qry), $facetfield, urlencode('"' . $facetval . '"'), $fqstr));
                                //if server is set add it on the end of the url
                                $facetitm['link'] .=$serverval;
                                $facetitm['name'] = $facetval;
                                $facetitms[] = $facetitm; 
                            }
                        }
                        
                        $facetinfo['items'] = $facetitms;
                        $facetout[$facetfield] = $facetinfo;
                    }
                }
                
                $facetout['selected'] = $selectedfacets;
                $out['facets'] = $facetout;
            }
            
            $resultout = array();

            if ($response->numFound != 0) {                 
                foreach ( $response->docs as $doc ) {
                    $resultinfo = array();
                    $docid = strval($doc->id);
                    $resultinfo['permalink'] = $doc->permalink;
                    $resultinfo['title'] = $doc->title;
                    $resultinfo['author'] = $doc->author;
                    $resultinfo['authorlink'] = htmlspecialchars($doc->author_s);
                    $resultinfo['numcomments'] = $doc->numcomments;
                    $resultinfo['date'] = $doc->displaydate;

                    if ($doc->numcomments === 0) { 
                        $resultinfo['comment_link'] = $doc->permalink . "#respond";
                    } else {
                        $resultinfo['comment_link'] = $doc->permalink . "#comments";
                    }

                    $resultinfo['score'] = $doc->score;
                    $resultinfo['id'] = $docid;
                    $docteaser = $teasers[$docid];
                    if ($docteaser->content) {
                        $resultinfo['teaser'] = sprintf(__("...%s..."), implode("...", $docteaser->content));
                    } else {
                        $words = split(' ', $doc->content);
                        $teaser = implode(' ', array_slice($words, 0, 30));
                        $resultinfo['teaser'] = sprintf(__("%s..."), $teaser);
                    }
                    $resultout[] = $resultinfo;
                }
            }
            $out['results'] = $resultout;
        }
    } else {
        $out['hits'] = "0";
    }

    # pager and results count helpers
    $out['query'] = htmlspecialchars($qry);
    $out['offset'] = strval($offset);
    $out['count'] = strval($count);
    $out['firstresult'] = strval($offset + 1);
    $out['lastresult'] = strval(min($offset + $count, $out['hits']));
    $out['sortby'] = $sortby;
    $out['order'] = $order;
    $out['sorting'] = array(
                        'scoreasc' => htmlspecialchars(sprintf('?s=%s&fq=%s&sort=score&order=asc%s', urlencode($qry), stripslashes($fq), $serverval)),
                        'scoredesc' => htmlspecialchars(sprintf('?s=%s&fq=%s&sort=score&order=desc%s', urlencode($qry), stripslashes($fq), $serverval)),
                        'dateasc' => htmlspecialchars(sprintf('?s=%s&fq=%s&sort=date&order=asc%s', urlencode($qry), stripslashes($fq), $serverval)),
                        'datedesc' => htmlspecialchars(sprintf('?s=%s&fq=%s&sort=date&order=desc%s', urlencode($qry), stripslashes($fq), $serverval)),
                        'modifiedasc' => htmlspecialchars(sprintf('?s=%s&fq=%s&sort=modified&order=asc%s', urlencode($qry), stripslashes($fq), $serverval)),
                        'modifieddesc' => htmlspecialchars(sprintf('?s=%s&fq=%s&sort=modified&order=desc%s', urlencode($qry), stripslashes($fq), $serverval)),
                        'commentsasc' => htmlspecialchars(sprintf('?s=%s&fq=%s&sort=numcomments&order=asc%s', urlencode($qry), stripslashes($fq), $serverval)),
                        'commentsdesc' => htmlspecialchars(sprintf('?s=%s&fq=%s&sort=numcomments&order=desc%s', urlencode($qry), stripslashes($fq), $serverval))
                    );
    
    return $out;
}

function s4w_print_facet_items($items, $pre = "<ul>", $post = "</ul>", $before = "<li>", $after = "</li>",
                                $nestedpre = "<ul>", $nestedpost = "</ul>", $nestedbefore = "<li>", $nestedafter = "</li>") {
    if (!$items) {
        return;
    }
    printf(__("%s\n"), $pre);
    foreach ($items as $item) {
        printf(__("%s<a href=\"%s\">%s (%s)</a>%s\n"), $before, $item["link"], $item["name"], $item["count"], $after);
        $item_items = isset($item["items"]) ? true : false;
        
        if ($item_items) {
            s4w_print_facet_items($item["items"], $nestedpre, $nestedpost, $nestedbefore, $nestedafter, 
                                                  $nestedpre, $nestedpost, $nestedbefore, $nestedafter);
        }
    }
    printf(__("%s\n"), $post);
}

function s4w_get_output_taxo($facet, $taxo, $prefix, $fqstr, $field) {
    $qry = stripslashes($_GET['s']);
    
    if (count($taxo) == 0) {
        return;
    } else {
        $facetitms = array();
        foreach ($taxo as $taxoname => $taxoval) {                    
            $newprefix = $prefix . $taxoname . '^^';
            $facetvars = get_object_vars($facet);
            $facetitm = array();
            $facetitm['count'] = sprintf(__("%d"), $facetvars[$newprefix]);
            $facetitm['link'] = htmlspecialchars(sprintf(__('?s=%s&fq=%s:%s%s', 'solr4wp'), $qry, $field,  urlencode('"' . $newprefix . '"'), $fqstr));
            $facetitm['name'] = $taxoname;
            $outitms = s4w_get_output_taxo($facet, $taxoval, $newprefix, $fqstr, $field);
            if ($outitms) {
                $facetitm['items'] = $outitms;
            }
            $facetitms[] = $facetitm;
        }
        
        return $facetitms;
    }
}

function s4w_gen_taxo_array($in, $vals) {
    if (count($vals) == 1) {
        if ( ! $in[$vals[0]]) {
            $in[$vals[0]] = array();
        }
        return $in;
    } else {
        $in[$vals[0]] = s4w_gen_taxo_array($in[$vals[0]], array_slice($vals, 1));
        return $in;
    }
}

/**
 * Query the required server
 * passes all parameters to the appropriate function based on the server name
 * This allows for extensible server/core based query functions.
 * TODO allow for similar theme/output function
 */
function s4w_query( $qry, $offset, $count, $fq, $sortby, $server = NULL) {
  //NOTICE: does this needs to be cached to stop the db being hit to grab the options everytime search is being done.
  $plugin_s4w_settings = s4w_get_option();
  //if no server has been provided use the default server
  if(!$server) {
    $server = $plugin_s4w_settings['s4w_server']['type']['search'];
  }
  $solr = s4w_get_solr($server);
  if (!function_exists($function = 's4w_'.$server.'_query')) {
    $function = 's4w_master_query';
  }
  
  return $function($solr, $qry, $offset, $count, $fq, $sortby, $plugin_s4w_settings);
}

function s4w_master_query($solr, $qry, $offset, $count, $fq, $sortby, &$plugin_s4w_settings) {
    $response = NULL;
    $facet_fields = array();
    $number_of_tags = $plugin_s4w_settings['s4w_max_display_tags'];
    
    if ($plugin_s4w_settings['s4w_facet_on_categories']) {
      $facet_fields[] = 'categories';
    }
    
    $facet_on_tags = $plugin_s4w_settings['s4w_facet_on_tags'];
    if ($facet_on_tags) {
      $facet_fields[] = 'tags';
    }
    
    if ($plugin_s4w_settings['s4w_facet_on_author']) {
      $facet_fields[] = 'author';
    }
    
    if ($plugin_s4w_settings['s4w_facet_on_type']) {
      $facet_fields[] = 'type';
    }
    
    
    $facet_on_custom_taxonomy = $plugin_s4w_settings['s4w_facet_on_taxonomy'];
    if (count($facet_on_custom_taxonomy)) {
      $taxonomies = (array)get_taxonomies(array('_builtin'=>FALSE),'names');
      foreach($taxonomies as $parent) {
        $facet_fields[] = $parent."_taxonomy";
      }
    }
    
    $facet_on_custom_fields = $plugin_s4w_settings['s4w_facet_on_custom_fields'];
    if (count($facet_on_custom_fields)) {
        foreach ( $facet_on_custom_fields as $field_name ) {
        	$facet_fields[] = $field_name . '_str';
        }
    }   	
    
    if ( $solr ) {
        $params = array();
        $params['defType'] = 'dismax';
        $params['qf'] = 'tagssrch^5 title^10 categoriessrch^5 content^3.5 comments^1.5'; // TODO : Add "_srch" custom fields ?
        $params['pf'] = 'title^15 text^10';
        $params['facet'] = 'true';
        $params['facet.field'] = $facet_fields;
        $params['facet.mincount'] = '1';
        $params['fq'] = $fq;
        $params['fl'] = '*,score';
        $params['hl'] = 'on';
        $params['hl.fl'] = 'content';
        $params['hl.snippets'] = '3';
        $params['hl.fragsize'] = '50';
        $params['sort'] = $sortby;
        $params['spellcheck.onlyMorePopular'] = 'true';
        $params['spellcheck.extendedResults'] = 'false';
        $params['spellcheck.collate'] = 'true';
        $params['spellcheck.count'] = '1';
        $params['spellcheck'] = 'true';
        
        if ($facet_on_tags) {
            $params['f.tags.facet.limit'] = $number_of_tags;
        }

        try { 
            $response = $solr->search($qry, $offset, $count, $params);
            if ( ! $response->getHttpStatus() == 200 ) { 
                $response = NULL; 
            }
        }
        catch(Exception $e) {
            syslog(LOG_ERR, "failed to query solr for " . print_r($qry, true) . print_r($params,true));
            $response = NULL;
        }
    }
    
    return $response;
}

function s4w_options_init() {
    
    $method = $_POST['method'];
    if ($method === "load") {
        $type = $_POST['type'];
        $prev = $_POST['prev'];
        
        if ($type) {
            s4w_load_all_posts($prev, $type);
            exit;
         } else {
            return;
        }
    }
    register_setting('s4w-options-group', 'plugin_s4w_settings', 's4w_sanitise_options' );   
}

/**
 * Sanitises the options values
 * @param $options array of s4w settings options
 * @return $options sanitised values
 */
function s4w_sanitise_options($options) {
  $options['s4w_solr_host'] = wp_filter_nohtml_kses($options['s4w_solr_host']);
  $options['s4w_solr_port'] = absint($options['s4w_solr_port']);
  $options['s4w_solr_path'] = wp_filter_nohtml_kses($options['s4w_solr_path']);
  $options['s4w_solr_update_host'] = wp_filter_nohtml_kses($options['s4w_solr_update_host']);
  $options['s4w_solr_update_port'] = absint($options['s4w_solr_update_port']);
  $options['s4w_solr_update_path'] = wp_filter_nohtml_kses($options['s4w_solr_update_path']);  
  $options['s4w_index_pages'] = absint($options['s4w_index_pages']);
  $options['s4w_index_posts'] = absint($options['s4w_index_posts']);
  $options['s4w_index_comments'] = absint($options['s4w_index_comments']); 
  $options['s4w_delete_page'] = absint($options['s4w_delete_page']); 
  $options['s4w_delete_post'] = absint($options['s4w_delete_post']); 
  $options['s4w_private_page'] = absint($options['s4w_private_page']); 
  $options['s4w_private_post'] = absint($options['s4w_private_post']);
  $options['s4w_output_info'] = absint($options['s4w_output_info']); 
  $options['s4w_output_pager'] = absint($options['s4w_output_pager']); 
  $options['s4w_output_facets'] = absint($options['s4w_output_facets']);
  $options['s4w_exclude_pages'] = s4w_filter_str2list($options['s4w_exclude_pages']);
  $options['s4w_num_results'] = absint($options['s4w_num_results']);
  $options['s4w_cat_as_taxo'] = absint($options['s4w_cat_as_taxo']);
  $options['s4w_max_display_tags'] = absint($options['s4w_max_display_tags']);
  $options['s4w_facet_on_categories'] = absint($options['s4w_facet_on_categories']);
  $options['s4w_facet_on_tags'] = absint($options['s4w_facet_on_tags'] );
  $options['s4w_facet_on_author'] = absint($options['s4w_facet_on_author']);
  $options['s4w_facet_on_type'] = absint($options['s4w_facet_on_type']);
  $options['s4w_index_all_sites'] = absint($options['s4w_index_all_sites']);
  $options['s4w_enable_dym'] = absint($options['s4w_enable_dym'] );
  $options['s4w_connect_type'] = wp_filter_nohtml_kses($options['s4w_connect_type']);
  $options['s4w_index_custom_fields'] = s4w_filter_str2list($options['s4w_index_custom_fields']);
  $options['s4w_facet_on_custom_fields'] = s4w_filter_str2list($options['s4w_facet_on_custom_fields']);    
  return $options;
}

function s4w_filter_str2list_numeric($input) {
    $final = array();
    if ($input != "") {
        foreach( split(',', $input) as $val ) {
            $val = trim($val);
            if ( is_numeric($val) ) {
                $final[] = $val;
            }
        }
    }

    return $final;
}

function s4w_filter_str2list($input) {
    $final = array();
    if ($input != "") {
        foreach( split(',', $input) as $val ) {
            $final[] = trim($val);
        }
    }

    return $final;
}

function s4w_filter_list2str($input) {
	if (!is_array($input)) {
		return "";
	}
	
    $outval = implode(',', $input);
    if (!$outval) {
        $outval = ""; 
    }
    
    return $outval;
}

function s4w_add_pages() {
    $addpage = FALSE;
    
    if (is_multisite() && is_site_admin()) {
        $plugin_s4w_settings = s4w_get_option();
        $indexall = $plugin_s4w_settings['s4w_index_all_sites'];
        if (($indexall && is_main_blog()) || !$indexall) {
            $addpage = TRUE;
        }
    } else if (!is_multisite() && is_admin()) {
        $addpage = TRUE;
    }
    
    if ($addpage) {
        add_options_page('Solr Options', 'Solr Options', 8, __FILE__, 's4w_options_page');
    }
}

function s4w_options_page() {
    if ( file_exists ( dirname(__FILE__) . '/solr-options-page.php' )) {
        include( dirname(__FILE__) . '/solr-options-page.php' );
    } else {
        _e("<p>Couldn't locate the options page.</p>", 'solr4wp');
    }
}

function s4w_admin_head() {
    // include our default css 
    if (file_exists(dirname(__FILE__) . '/template/search.css')) {
        printf(__("<link rel=\"stylesheet\" href=\"%s\" type=\"text/css\" media=\"screen\" />\n"), plugins_url('/template/search.css', __FILE__));
    }
?>
<script type="text/javascript">
    var $j = jQuery.noConflict();
    
    function switch1() {
        if ($j('#solrconnect_single').is(':checked')) {
            $j('#solr_admin_tab2').css('display', 'block');
            $j('#solr_admin_tab2_btn').addClass('solr_admin_on');         
            $j('#solr_admin_tab3').css('display', 'none');
            $j('#solr_admin_tab3_btn').removeClass('solr_admin_on');            
        }
        if ($j('#solrconnect_separated').is(':checked')) {
            $j('#solr_admin_tab2').css('display', 'none');
            $j('#solr_admin_tab2_btn').removeClass('solr_admin_on');  
            $j('#solr_admin_tab3').css('display', 'block');
            $j('#solr_admin_tab3_btn').addClass('solr_admin_on');                   
        }        
    }
 
    
    function doLoad($type, $prev) {
        if ($prev == null) {
            $j.post("options-general.php?page=solr-for-wordpress/solr-for-wordpress.php", {method: "load", type: $type}, handleResults, "json");
        } else {
            $j.post("options-general.php?page=solr-for-wordpress/solr-for-wordpress.php", {method: "load", type: $type, prev: $prev}, handleResults, "json");
        }
    }
    
    function handleResults(data) {
        $j('#percentspan').text(data.percent + "%");
        if (!data.end) {
            doLoad(data.type, data.last);
        } else {
            $j('#percentspan').remove();
            enableAll();
        }
    }
    
    function disableAll() {
        $j("input[name^='s4w_content_load']").attr('disabled','disabled');
        $j('[name=s4w_deleteall]').attr('disabled','disabled');
        $j('[name=s4w_init_blogs]').attr('disabled','disabled');
        $j('[name=s4w_optimize]').attr('disabled','disabled');
        $j('[name=s4w_ping]').attr('disabled','disabled');
        $j('#settingsbutton').attr('disabled','disabled');
    }
    
    function enableAll() {
        $j("input[name^='s4w_content_load']").removeAttr('disabled');
        $j('[name=s4w_deleteall]').removeAttr('disabled');
        $j('[name=s4w_init_blogs]').removeAttr('disabled');
        $j('[name=s4w_optimize]').removeAttr('disabled');
        $j('[name=s4w_ping]').removeAttr('disabled');
        $j('#settingsbutton').removeAttr('disabled');
    }
    
    $percentspan = '<span style="font-size:1.2em;font-weight:bold;margin:20px;padding:20px" id="percentspan">0%</span>';
    
    $j(document).ready(function() {
       switch1();
       $j("input[name^='s4w_content_load']").click(function(event){ 
          event.preventDefault();
          var regex = /\b[a-z]+\b/;
          var match = regex.exec(this.name);
          var post_type = match[0];
          $j(this).after($percentspan);
          disableAll();
          doLoad(post_type, null);
        });
     });
    
</script> <?php
}

function s4w_default_head() {
    // include our default css 
    if (file_exists(dirname(__FILE__) . '/template/search.css')) {
        printf(__("<link rel=\"stylesheet\" href=\"%s\" type=\"text/css\" media=\"screen\" />\n"), plugins_url('/template/search.css', __FILE__));
    }
}

function s4w_autosuggest_head() {
    if (file_exists(dirname(__FILE__) . '/template/autocomplete.css')) {
        printf(__("<link rel=\"stylesheet\" href=\"%s\" type=\"text/css\" media=\"screen\" />\n"), plugins_url('/template/autocomplete.css', __FILE__));
    }
?>
<script type="text/javascript">
    jQuery(document).ready(function($) {
        $("#s").suggest("?method=autocomplete",{});
        $("#qrybox").suggest("?method=autocomplete",{});
    });
</script>
<?php
}

function s4w_template_redirect() {
    wp_enqueue_script('suggest');

    // not a search page; don't do anything and return
    // thanks to the Better Search plugin for the idea:  http://wordpress.org/extend/plugins/better-search/
    $search = stripos($_SERVER['REQUEST_URI'], '?s=');
    $autocomplete = stripos($_SERVER['REQUEST_URI'], '?method=autocomplete');

    if ( ($search || $autocomplete) == FALSE ) {
        return;
    }
    
    if ($autocomplete) {
        $q = stripslashes($_GET['q']);
        $limit = $_GET['limit'];

        s4w_autocomplete($q, $limit);
        exit;
    }
    
	// If there is a template file then we use it
    if (locate_template( array( 's4w_search.php' ), FALSE, TRUE)) {
        // use theme file
        locate_template( array( 's4w_search.php' ), TRUE, TRUE);
    } else if (file_exists(dirname(__FILE__) . '/template/s4w_search.php')) {
        // use plugin supplied file
        add_action('wp_head', 's4w_default_head');
        include_once(dirname(__FILE__) . '/template/s4w_search.php');
    } else {
        // no template files found, just continue on like normal
        // this should get to the normal WordPress search results
        return;
    }
    
    exit;
}

function s4w_mlt_widget() {
    register_widget('s4w_MLTWidget');
}

class s4w_MLTWidget extends WP_Widget {

    function s4w_MLTWidget() {
        $widget_ops = array('classname' => 'widget_s4w_mlt', 'description' => __( "Displays a list of pages similar to the page being viewed") );
        $this->WP_Widget('mlt', __('Similar'), $widget_ops);
    }

    function widget( $args, $instance ) {
        
        extract($args);
        $title = apply_filters('widget_title', empty($instance['title']) ? __('Similar') : $instance['title']);
        $count = empty($instance['count']) ? 5 : $instance['count'];
        if (!is_numeric($count)) {
            $count = 5;
        }
        
        $showauthor = $instance['showauthor'];

        $solr = s4w_get_solr();
        $response = NULL;

        if ((!is_single() && !is_page()) || !$solr) {
            return;
        }
        
        $params = array();
        $qry = 'permalink:' . $solr->escape(get_permalink());
        $params['fl'] = 'title,permalink,author';
        $params['mlt'] = 'true';
        $params['mlt.count'] = $count;
        $params['mlt.fl'] = 'title,content';

        $response = $solr->search($qry, 0, 1, $params);
        if ( ! $response->getHttpStatus() == 200 ) { 
            return;
        }
    
        echo $before_widget;
        if ( $title )
            echo $before_title . $title . $after_title;
        
        $mltresults = $response->moreLikeThis;
        foreach ($mltresults as $mltresult) {
            $docs = $mltresult->docs;
            echo "<ul>";
            foreach($docs as $doc) {
                if ($showauthor) {
                    $author = " by {$doc->author}";
                }
                echo "<li><a href=\"{$doc->permalink}\" title=\"{$doc->title}\">{$doc->title}</a>{$author}</li>";
            }
            echo "</ul>";
        }
        
        echo $after_widget;
    }

    function update( $new_instance, $old_instance ) {
        $instance = $old_instance;
        $new_instance = wp_parse_args( (array) $new_instance, array( 'title' => '', 'count' => 5, 'showauthor' => 0) );
        $instance['title'] = strip_tags($new_instance['title']);
        $cnt = strip_tags($new_instance['count']);
        $instance['count'] = is_numeric($cnt) ? $cnt : 5;
        $instance['showauthor'] = $new_instance['showauthor'] ? 1 : 0;
        
        return $instance;
    }

    function form( $instance ) {
        $instance = wp_parse_args( (array) $instance, array( 'title' => '', 'count' => 5, 'showauthor' => 0) );
        $title = strip_tags($instance['title']);
        $count = strip_tags($instance['count']);
        $showauthor = $instance['showauthor'] ? 'checked="checked"' : '';
?>
            <p>
                <label for="<?php echo $this->get_field_id('title'); ?>"><?php _e('Title:'); ?></label>
                <input class="widefat" id="<?php echo $this->get_field_id('title'); ?>" name="<?php echo $this->get_field_name('title'); ?>" type="text" value="<?php echo esc_attr($title); ?>" />
            </p>
            <p>
                <label for="<?php echo $this->get_field_id('count'); ?>"><?php _e('Count:'); ?></label>
                <input class="widefat" id="<?php echo $this->get_field_id('count'); ?>" name="<?php echo $this->get_field_name('count'); ?>" type="text" value="<?php echo esc_attr($count); ?>" />
            </p>
            <p>
                <label for="<?php echo $this->get_field_id('showauthor'); ?>"><?php _e('Show Author?:'); ?></label>
                <input class="checkbox" type="checkbox" <?php echo $showauthor; ?> id="<?php echo $this->get_field_id('showauthor'); ?>" name="<?php echo $this->get_field_name('showauthor'); ?>" />
            </p>
<?php
    }
}

function s4w_autocomplete($q, $limit) {
    $solr = s4w_get_solr();
    $response = NULL;

    if (!$solr) {
        return;
    }
    
    $params = array();
    $params['terms'] = 'true';
    $params['terms.fl'] = 'spell';
    $params['terms.lower'] = $q;
    $params['terms.prefix'] = $q;
    $params['terms.lower.incl'] = 'false';
    $params['terms.limit'] = $limit;
    $params['qt'] = '/terms';

    $response = $solr->search($q, 0, $limit, $params);
    if ( ! $response->getHttpStatus() == 200 ) { 
        return;
    }
    
    $terms = get_object_vars($response->terms->spell);
    foreach($terms as $term => $count) {
        printf("%s\n", $term);
    }
}

// copies config settings from the main blog
// to all of the other blogs
function s4w_copy_config_to_all_blogs() {
  global $wpdb;

  $blogs = $wpdb->get_results("SELECT blog_id FROM $wpdb->blogs WHERE spam = 0 AND deleted = 0");

  $plugin_s4w_settings = s4w_get_option();
  foreach($blogs as $blog) {
    switch_to_blog($blog->blog_id);
    wp_cache_flush();
    syslog(LOG_ERR,"pushing config to {$blog->blog_id}");
    s4w_update_option($plugin_s4w_settings);
  }

  wp_cache_flush();
  restore_current_blog();
}

function s4w_apply_config_to_blog($blogid) {
  syslog(LOG_ERR,"applying config to blog with id $blogid");
  if (!is_multisite())
    return;

  wp_cache_flush();
  $plugin_s4w_settings = s4w_get_option();
  switch_to_blog($blogid);
  wp_cache_flush();
  s4w_update_option($plugin_s4w_settings);
  restore_current_blog();
  wp_cache_flush();
}

/**
 * Retrieve a list of post types that exists
 * @return array
 */
function s4w_get_all_post_types() {
  global $wpdb;
  //remove the defualt attachment/revision and menu from the returned types.
  $query = $wpdb->get_results("SELECT DISTINCT(post_type) FROM $wpdb->posts WHERE post_type NOT IN('attachment', 'revision', 'nav_menu_item') ORDER BY post_type");
  if ($query) {
    $types = array();
    foreach ( $query as $type ) {
      $types[] = $type->post_type;
    }       
    return $types;
  }
}

add_action( 'template_redirect', 's4w_template_redirect', 1 );
add_action( 'publish_post', 's4w_handle_modified' );
add_action( 'publish_page', 's4w_handle_modified' );
add_action( 'save_post', 's4w_handle_modified' );
add_action( 'delete_post', 's4w_handle_delete' );
add_action( 'trash_post', 's4w_handle_delete' );
add_action( 'admin_menu', 's4w_add_pages');
add_action( 'admin_init', 's4w_options_init');
add_action( 'widgets_init', 's4w_mlt_widget');
add_action( 'wp_head', 's4w_autosuggest_head');
add_action( 'admin_head', 's4w_admin_head');

if (is_multisite()) {
    add_action( 'deactivate_blog', 's4w_handle_deactivate_blog');
    add_action( 'activate_blog', 's4w_handle_activate_blog');
    add_action( 'archive_blog', 's4w_handle_archive_blog');
    add_action( 'unarchive_blog', 's4w_handle_unarchive_blog');
    add_action( 'make_spam_blog', 's4w_handle_spam_blog');
    add_action( 'unspam_blog', 's4w_handle_unspam_blog');
    add_action( 'delete_blog', 's4w_handle_delete_blog');
    add_action( 'wpmu_new_blog', 's4w_handle_new_blog');
}

?>
