<?php
/*  
    Copyright (c) 2009 Matt Weber

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

#set defaults if not initialized
if (s4w_get_option('s4w_solr_initialized') != '1') {
    update_site_option('s4w_index_all_sites', '0');
    s4w_update_option('s4w_solr_host', 'localhost');
    s4w_update_option('s4w_solr_port', '8983');
    s4w_update_option('s4w_solr_path', '/solr');
    s4w_update_option('s4w_index_pages', '1');
    s4w_update_option('s4w_index_posts', '1');
    s4w_update_option('s4w_delete_page', '1');
    s4w_update_option('s4w_delete_post', '1');
    s4w_update_option('s4w_private_page', '1');
    s4w_update_option('s4w_private_post', '1');
    s4w_update_option('s4w_output_info', '1');
    s4w_update_option('s4w_output_pager', '1');
    s4w_update_option('s4w_output_facets', '1');
    s4w_update_option('s4w_exclude_pages', array());
    s4w_update_option('s4w_num_results', '5');
    s4w_update_option('s4w_cat_as_taxo', '1');
    s4w_update_option('s4w_solr_initialized', '1');
    s4w_update_option('s4w_max_display_tags', '10');
    s4w_update_option('s4w_facet_on_categories', '1');
    s4w_update_option('s4w_facet_on_tags', '1');
    s4w_update_option('s4w_facet_on_author', '1');
    s4w_update_option('s4w_facet_on_type', '1');
    s4w_update_option('s4w_enable_dym', '1');
    s4w_update_option('s4w_index_comments', '1');
    s4w_update_option('s4w_connect_type', 'powcloud');
}

wp_reset_vars(array('action'));

# save form settings if we get the update action
# we do saving here instead of using options.php because we need to use
# s4w_update_option instead of update option.
if ($_POST['action'] == 'update') {
    $options = array('s4w_solr_host', 's4w_solr_port', 's4w_solr_path', 's4w_index_pages',
                     's4w_index_posts', 's4w_delete_page', 's4w_delete_post', 's4w_private_page',
                     's4w_private_post', 's4w_output_info', 's4w_output_pager', 's4w_output_facets',
                     's4w_exclude_pages', 's4w_num_results', 's4w_cat_as_taxo', 's4w_max_display_tags',
                     's4w_facet_on_categories', 's4w_facet_on_tags', 's4w_facet_on_author', 's4w_facet_on_type',
                     's4w_enable_dym', 's4w_index_comments', 's4w_connect_type', 's4w_index_all_sites');
    
    foreach ( $options as $option ) {
        $option = trim($option);
        $value = null;
        if ( isset($_POST[$option]) )
            $value = $_POST[$option];
            
        if ( !is_array($value) ) $value = trim($value);
        $value = stripslashes_deep($value);
        
        if ( $option == 's4w_index_all_sites') {   
            update_site_option($option, $value);
        } else {
            s4w_update_option($option, $value);
        }
    }
    
    ?>
    <div id="message" class="updated fade"><p><strong><?php _e('Success!', 'solr4wp') ?></strong></p></div>
    <?php
}

# checks if we need to check the checkbox
function s4w_checkCheckbox( $theFieldname ) {
    if ($theFieldname == 's4w_index_all_sites') {
        if (get_site_option($theFieldname) == '1') {
            echo 'checked="checked"';
        }
    } else {
	    if( s4w_get_option( $theFieldname ) == '1'){
		    echo 'checked="checked"';
	    }
	}
}

function s4w_checkConnectOption($connectType) {
    if ( s4w_get_option('s4w_connect_type') === $connectType ) {
        echo 'checked="checked"';
    }
}

# check for any POST settings
if ($_POST['s4w_ping']) {
    if (s4w_get_solr(true)) {
?>
<div id="message" class="updated fade"><p><strong><?php _e('Ping Success!', 'solr4wp') ?></strong></p></div>
<?php
    } else {
?>
    <div id="message" class="updated fade"><p><strong><?php _e('Ping Failed!', 'solr4wp') ?></strong></p></div>
<?php
    }
} else if ($_POST['s4w_deleteall']) {
    s4w_delete_all();
?>
    <div id="message" class="updated fade"><p><strong><?php _e('All Indexed Pages Deleted!', 'solr4wp') ?></strong></p></div>
<?php
} else if ($_POST['s4w_optimize']) {
    s4w_optimize();
?>
    <div id="message" class="updated fade"><p><strong><?php _e('Index Optimized!', 'solr4wp') ?></strong></p></div>
<?php
}
?>

<style type="text/css">
.powcloud_info {
	background-color:#FFFFE0; 
	border-color:#E6DB55;
}

.powcloud_error {
	background-color: #CF7D6B;
	border-color: #FFFFE0;
}

.powcloud_fade_out {
	background-color: white;
	border-color: white;
}

</style>

<script type="text/javascript">

function powcloudSetMessage(msg, cssClass) {
	if(!msg) {
		return;
	}
	
	//jQuery('#powcloud_msg').addClass(cssClass);
	jQuery("#powcloud_msg").html(msg);	
	jQuery("#powcloud_msg").fadeIn("slow", function() {
		jQuery("#powcloud_msg").removeClass(cssClass);
		jQuery("#powcloud_msg").addClass("powcloud_fade_out");
	});	
}

function powcloudApiCallback(data) {
	
	if(data.success === false) {
		 powcloudSetMessage("Error: " + data.error);
	}
	else {
	     //console.log(data);
	     powcloudSetMessage("Success! Your slice [" + data.index  + "] has been activated.", "powcloud_info");

	     var solr_host = data.solr_url.split("/")[2];
	     var solr_port = 80;

	     var portParts = solr_host.split(":");
	     if(portParts.length == 2) {
	          solr_port = portParts[1];
	     }

	     var solr_path = data.solr_url.substring(data.solr_url.indexOf(solr_host) + solr_host.length);
	     jQuery("#s4w_solr_host").val(solr_host);
	     jQuery("#s4w_solr_port").val(solr_port);
	     jQuery("#s4w_solr_path").val(solr_path);
	     jQuery("#settingsbutton").click();		
	}
}



jQuery(document).ready(function(){
	
	jQuery("#powcloud_api_key").blur(function() {
		// todo - verify the api key format. it shouldn't be empty.
	});
	
	jQuery("#btn_powcloud_activate").click(function(){
		jQuery("#powcloud_msg").fadeOut();
        var apikey = jQuery("#powcloud_api_key").val();

		// TODO - BEGIN test mode
        // TODO - add correct api url
		var powcloudApiUrl = "http://www.powcloud.com/api/v1/keys/" + apikey;
        if(document.location.toString().indexOf("localhost") > -1) {
			powcloudApiUrl = "http://localhost:3000/api/v1/keys/" + apikey;
		}
		// TODO - END test mode

        jQuery.ajax({
             url: powcloudApiUrl,
             dataType: "jsonp",
             success: powcloudApiCallback
        });
	});	
	
	connect_type = jQuery('[name=s4w_connect_type]').val();
	
	if(connect_type == "powcloud") {
		solr_host = jQuery("#s4w_solr_host").val();
		if(solr_host && solr_host.toLowerCase().indexOf("powcloud.com") > 0) {
			// repopulate the API field
			solr_path = jQuery("#s4w_solr_path").val();
			pathParts = solr_path.replace(/\/?search\//,"").split("/");			
			if(pathParts.length > 2) {
				jQuery("#powcloud_api_key").val(pathParts[1]);	
				powcloudSetMessage("Your PowCloud slice [" + pathParts[0]  + "] is ready!", "powcloud_info");			
			} 
		}
	}

});
</script>

<div class="wrap">
<h2><?php _e('Solr For WordPress', 'solr4wp') ?></h2>

<form method="post" action="options-general.php?page=solr-for-wordpress/solr-for-wordpress.php" id="s4w_options_form">
<h3><?php _e('Configure Solr', 'solr4wp') ?></h3>

<div class="solr_admin clearfix">
	<div class="solr_adminR">
		<div class="solr_adminR1" id="solr_admin_tab1">
			
			<label>Enter your PowCloud API Key</label>
			<p><input name="powcloud_api_key" id="powcloud_api_key" type="text" /><input class="button-primary" type="button" value="Activate" id="btn_powcloud_activate" /></p>
			<p>Don't have a PowCloud account? <a href="http://www.powcloud.com/signup" target="_blank" >Get one now!</a></p>

			<div style="display:none;" class="fade powcloud_info" id="powcloud_msg"></div>
	
		</div>

		<div class="solr_adminR2" id="solr_admin_tab2">
			<label><?php _e('Solr Host', 'solr4wp') ?></label>
			<p><input type="text" id="s4w_solr_host" name="s4w_solr_host" value="<?php _e(s4w_get_option('s4w_solr_host'), 'solr4wp'); ?>" /></p>
			<label><?php _e('Solr Port', 'solr4wp') ?></label>
			<p><input type="text" id="s4w_solr_port" name="s4w_solr_port" value="<?php _e(s4w_get_option('s4w_solr_port'), 'solr4wp'); ?>" /></p>
			<label><?php _e('Solr Path', 'solr4wp') ?></label>
			<p><input type="text" id="s4w_solr_path" name="s4w_solr_path" value="<?php _e(s4w_get_option('s4w_solr_path'), 'solr4wp'); ?>" /></p>
		</div>
	</div>
	<ol>
		<li id="solr_admin_tab1_btn" class="solr_admin_tab1">
			<h4><input id="powconnect" name="s4w_connect_type" type="radio" value="powcloud" <?php s4w_checkConnectOption('powcloud'); ?> onclick="switch1();" /> Hosted by <a href="">PowCloud</a></h4>
			<ol>
				<li>Plug-and-play hosted power search for your blog</li>
				<li>5 minute setup, save server bandwidth</li>
				<li>Supercharge your search performance</li>
				<li><a href="">Learn More >></a></li>
			</ol>
		</li>
		<li id="solr_admin_tab2_btn" class="solr_admin_tab2">
			<h4><input id="solrconnect" name="s4w_connect_type" type="radio" value="solr" <?php s4w_checkConnectOption('solr'); ?> onclick="switch1();" /> Self-managed Solr Server</h4>
			<ol>
				<li>Download, install and configure your own <a href="">Apache Solr</a> instance (Requires Java Runtime 1.6)</li>
			</ol>
		</li>
	</ol>
</div>
<hr />
<h3><?php _e('Indexing Options', 'solr4wp') ?></h3>
<table class="form-table">
    <tr valign="top">
        <th scope="row" style="width:200px;"><?php _e('Index Pages', 'solr4wp') ?></th>
        <td style="width:10px;float:left;"><input type="checkbox" name="s4w_index_pages" value="1" <?php echo s4w_checkCheckbox('s4w_index_pages'); ?> /></td>
        <th scope="row" style="width:200px;float:left;margin-left:20px;"><?php _e('Index Posts', 'solr4wp') ?></th>
        <td style="width:10px;float:left;"><input type="checkbox" name="s4w_index_posts" value="1" <?php echo s4w_checkCheckbox('s4w_index_posts'); ?> /></td>
    </tr>

    <tr valign="top">
        <th scope="row" style="width:200px;"><?php _e('Remove Page on Delete', 'solr4wp') ?></th>
        <td style="width:10px;float:left;"><input type="checkbox" name="s4w_delete_page" value="1" <?php echo s4w_checkCheckbox('s4w_delete_page'); ?> /></td>
        <th scope="row" style="width:200px;float:left;margin-left:20px;"><?php _e('Remove Post on Delete', 'solr4wp') ?></th>
        <td style="width:10px;float:left;"><input type="checkbox" name="s4w_delete_post" value="1" <?php echo s4w_checkCheckbox('s4w_delete_post'); ?> /></td>
    </tr>
    
    <tr valign="top">
        <th scope="row" style="width:200px;"><?php _e('Remove Page on Status Change', 'solr4wp') ?></th>
        <td style="width:10px;float:left;"><input type="checkbox" name="s4w_private_page" value="1" <?php echo s4w_checkCheckbox('s4w_private_page'); ?> /></td>
        <th scope="row" style="width:200px;float:left;margin-left:20px;"><?php _e('Remove Post on Status Change', 'solr4wp') ?></th>
        <td style="width:10px;float:left;"><input type="checkbox" name="s4w_private_post" value="1" <?php echo s4w_checkCheckbox('s4w_private_post'); ?> /></td>
    </tr>

    <tr valign="top">
        <th scope="row" style="width:200px;"><?php _e('Index Comments', 'solr4wp') ?></th>
        <td style="width:10px;float:left;"><input type="checkbox" name="s4w_index_comments" value="1" <?php echo s4w_checkCheckbox('s4w_index_comments'); ?> /></td>
    </tr>
        
    <?php
    //if (is_wpmu() && is_main_blog()) {
    if (is_wpmu() && is_main_blog() && false) {
    ?>
    
    <tr valign="top">
        <th scope="row" style="width:200px;"><?php _e('Index all Sites', 'solr4wp') ?></th>
        <td style="width:10px;float:left;"><input type="checkbox" name="s4w_index_all_sites" value="1" <?php echo s4w_checkCheckbox('s4w_index_all_sites'); ?> /></td>
    </tr>
    <?php
    }
    ?>
    <tr valign="top">
        <th scope="row"><?php _e('Excludes (see readme.txt for format)', 'solr4wp') ?></th>
        <td><input type="text" name="s4w_exclude_pages" value="<?php _e(s4w_filter_list2str(s4w_get_option('s4w_exclude_pages')), 'solr4wp'); ?>" /></td>
    </tr>
</table>
<hr />
<h3><?php _e('Result Options', 'solr4wp') ?></h3>
<table class="form-table">
    <tr valign="top">
        <th scope="row" style="width:200px;"><?php _e('Output Result Info', 'solr4wp') ?></th>
        <td style="width:10px;float:left;"><input type="checkbox" name="s4w_output_info" value="1" <?php echo s4w_checkCheckbox('s4w_output_info'); ?> /></td>
        <th scope="row" style="width:200px;float:left;margin-left:20px;"><?php _e('Output Result Pager', 'solr4wp') ?></th>
        <td style="width:10px;float:left;"><input type="checkbox" name="s4w_output_pager" value="1" <?php echo s4w_checkCheckbox('s4w_output_pager'); ?> /></td>
    </tr>
 
    <tr valign="top">
        <th scope="row" style="width:200px;"><?php _e('Output Facets', 'solr4wp') ?></th>
        <td style="width:10px;float:left;"><input type="checkbox" name="s4w_output_facets" value="1" <?php echo s4w_checkCheckbox('s4w_output_facets'); ?> /></td>
        <th scope="row" style="width:200px;float:left;margin-left:20px;"><?php _e('Category Facet as Taxonomy', 'solr4wp') ?></th>
        <td style="width:10px;float:left;"><input type="checkbox" name="s4w_cat_as_taxo" value="1" <?php echo s4w_checkCheckbox('s4w_cat_as_taxo'); ?> /></td>
    </tr>

    <tr valign="top">
        <th scope="row" style="width:200px;"><?php _e('Categories as Facet', 'solr4wp') ?></th>
        <td style="width:10px;float:left;"><input type="checkbox" name="s4w_facet_on_categories" value="1" <?php echo s4w_checkCheckbox('s4w_facet_on_categories'); ?> /></td>
        <th scope="row" style="width:200px;float:left;margin-left:20px;"><?php _e('Tags as Facet', 'solr4wp') ?></th>
        <td style="width:10px;float:left;"><input type="checkbox" name="s4w_facet_on_tags" value="1" <?php echo s4w_checkCheckbox('s4w_facet_on_tags'); ?> /></td>
    </tr>
    
    <tr valign="top">
        <th scope="row" style="width:200px;"><?php _e('Author as Facet', 'solr4wp') ?></th>
        <td style="width:10px;float:left;"><input type="checkbox" name="s4w_facet_on_author" value="1" <?php echo s4w_checkCheckbox('s4w_facet_on_author'); ?> /></td>
        <th scope="row" style="width:200px;float:left;margin-left:20px;"><?php _e('Type as Facet', 'solr4wp') ?></th>
        <td style="width:10px;float:left;"><input type="checkbox" name="s4w_facet_on_type" value="1" <?php echo s4w_checkCheckbox('s4w_facet_on_type'); ?> /></td>
    </tr>

    <tr valign="top">
        <th scope="row" style="width:200px;"><?php _e('Enable Spellchecking', 'solr4wp') ?></th>
        <td style="width:10px;float:left;"><input type="checkbox" name="s4w_enable_dym" value="1" <?php echo s4w_checkCheckbox('s4w_enable_dym'); ?> /></td>
    </tr>
                   
    <tr valign="top">
        <th scope="row"><?php _e('Number of Results Per Page', 'solr4wp') ?></th>
        <td><input type="text" name="s4w_num_results" value="<?php _e(s4w_get_option('s4w_num_results'), 'solr4wp'); ?>" /></td>
    </tr>   
    
    <tr valign="top">
        <th scope="row"><?php _e('Max Number of Tags to Display', 'solr4wp') ?></th>
        <td><input type="text" name="s4w_max_display_tags" value="<?php _e(s4w_get_option('s4w_max_display_tags'), 'solr4wp'); ?>" /></td>
    </tr>
</table>
<hr />
<?php settings_fields('s4w-options-group'); ?>

<p class="submit">
<input type="hidden" name="action" value="update" />
<input id="settingsbutton" type="submit" class="button-primary" value="<?php _e('Save Changes', 'solr4wp') ?>" />
</p>

</form>
<hr />
<form method="post" action="options-general.php?page=solr-for-wordpress/solr-for-wordpress.php">
<h3><?php _e('Actions', 'solr4wp') ?></h3>
<table class="form-table">
    <tr valign="top">
        <th scope="row"><?php _e('Check Server Settings', 'solr4wp') ?></th>
        <td><input type="submit" class="button-primary" name="s4w_ping" value="<?php _e('Execute', 'solr4wp') ?>" /></td>
    </tr>
 
    <tr valign="top">
        <th scope="row"><?php _e('Load All Pages', 'solr4wp') ?></th>
        <td><input type="submit" class="button-primary" name="s4w_pageload" value="<?php _e('Execute', 'solr4wp') ?>" /></td>
    </tr>

    <tr valign="top">
        <th scope="row"><?php _e('Load All Posts', 'solr4wp') ?></th>
        <td><input type="submit" class="button-primary" name="s4w_postload" value="<?php _e('Execute', 'solr4wp') ?>" /></td>
    </tr>
    
    <tr valign="top">
        <th scope="row"><?php _e('Optimize Index', 'solr4wp') ?></th>
        <td><input type="submit" class="button-primary" name="s4w_optimize" value="<?php _e('Execute', 'solr4wp') ?>" /></td>
    </tr>
        
    <tr valign="top">
        <th scope="row"><?php _e('Delete All', 'solr4wp') ?></th>
        <td><input type="submit" class="button-primary" name="s4w_deleteall" value="<?php _e('Execute', 'solr4wp') ?>" /></td>
    </tr>
</table>
</form>

</div>
