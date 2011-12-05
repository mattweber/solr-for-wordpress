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

//get the plugin settings
$s4w_settings = s4w_get_option('plugin_s4w_settings');
#set defaults if not initialized
if ($s4w_settings['s4w_solr_initialized'] != 1) {
  
  $options['s4w_index_all_sites'] = 0;
  $options['s4w_server']['info']['single']= array('host'=>'localhost','port'=>8983, 'path'=>'/solr');
  $options['s4w_server']['info']['master']= array('host'=>'localhost','port'=>8983, 'path'=>'/solr');
  $options['s4w_server']['type']['search'] = 'master';
  $options['s4w_server']['type']['update'] = 'master';
  
  $options['s4w_index_pages'] = 1;
  $options['s4w_index_posts'] = 1;
  $options['s4w_delete_page'] = 1;
  $options['s4w_delete_post'] = 1;
  $options['s4w_private_page'] = 1;
  $options['s4w_private_post'] = 1;
  $options['s4w_output_info'] = 1;
  $options['s4w_output_pager'] = 1;
  $options['s4w_output_facets'] = 1;
  $options['s4w_exclude_pages'] =  array();
  $options['s4w_exclude_pages'] = '';  
  $options['s4w_num_results'] = 5;
  $options['s4w_cat_as_taxo'] = 1;
  $options['s4w_solr_initialized'] = 1;
  $options['s4w_max_display_tags'] = 10;
  $options['s4w_facet_on_categories'] = 1;
  $options['s4w_facet_on_taxonomy'] = 1;
  $options['s4w_facet_on_tags'] = 1;
  $options['s4w_facet_on_author'] = 1;
  $options['s4w_facet_on_type'] = 1;
  $options['s4w_enable_dym'] = 1;
  $options['s4w_index_comments'] = 1;
  $options['s4w_connect_type'] = 'solr';
  $options['s4w_index_custom_fields'] =  array();
  $options['s4w_facet_on_custom_fields'] =  array();
  $options['s4w_index_custom_fields'] = '';  
  $options['s4w_facet_on_custom_fields'] = '';  
  
  //update existing settings from multiple option record to a single array
  //if old options exist, update to new system
  $delete_option_function = 'delete_option';
  if (is_multisite()) {
    $indexall = get_site_option('s4w_index_all_sites');
    $delete_option_function = 'delete_site_option';
  }
  //find each of the old options function
  //update our new array and delete the record.
  foreach($options as $key => $value ) {
    if( $existing = get_option($key)) {
      $options[$key] = $existing;
      $indexall = FALSE;
      //run the appropriate delete options function
      $delete_option_function($key);
    }
  }
  
  $s4w_settings = $options;
  //save our options array
  s4w_update_option($options);
}

wp_reset_vars(array('action'));

# save form settings if we get the update action
# we do saving here instead of using options.php because we need to use
# s4w_update_option instead of update option.
# As it stands we have 27 options instead of making 27 insert calls (which is what update_options does)
# Lets create an array of all our options and save it once.
if ($_POST['action'] == 'update') {   
  //lets loop through our setting fields $_POST['settings']
  foreach ($s4w_settings as $option => $old_value ) {
    $value = $_POST['settings'][$option];

    switch ($option) {
      case 's4w_solr_initialized':
        $value = trim($old_value);
        break;
    case 's4w_server':
      //remove empty server entries
      $s_value = &$value['info'];
      
      foreach ($s_value as $key => $v) {
        //lets rename the array_keys
        if(!$v['host']) unset($s_value[$key]);
      }
      break;

    }
    if ( !is_array($value) ) $value = trim($value); 
    $value = stripslashes_deep($value);
    $s4w_settings[$option] = $value;
  }
  // if we are in single server mode set the server types to master
  // and configure the master server to the values of the single server
  if ($s4w_settings['s4w_connect_type'] =='solr_single'){
    $s4w_settings['s4w_server']['info']['master']= $s4w_settings['s4w_server']['info']['single'];
    $s4w_settings['s4w_server']['type']['search'] = 'master';
    $s4w_settings['s4w_server']['type']['update'] = 'master';
  }
  // if this is a multi server setup we steal the master settings
  // and stuff them into the single server settings in case the user
  // decides to change it later 
  else {
    $s4w_settings['s4w_server']['info']['single']= $s4w_settings['s4w_server']['info']['master'];
  }
  //lets save our options array
  s4w_update_option($s4w_settings);

  //we need to make call for the options again 
  //as we need them to come out in an a sanitised format
  //otherwise fields that need to run s4w_filter_list2str will come up with nothin
  $s4w_settings = s4w_get_option('plugin_s4w_settings');

  ?>
  <div id="message" class="updated fade"><p><strong><?php _e('Success!', 'solr4wp') ?></strong></p></div>
  <?php
}

# checks if we need to check the checkbox
function s4w_checkCheckbox( $fieldValue ) {
  if( $fieldValue == '1'){
    echo 'checked="checked"';
  }
}

function s4w_checkConnectOption($optionType, $connectType) {
    if ( $optionType === $connectType ) {
        echo 'checked="checked"';
    }
}



# check for any POST settings
if ($_POST['s4w_ping']) {
    if (s4w_ping_server()) {
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
} else if ($_POST['s4w_init_blogs']) {
    s4w_copy_config_to_all_blogs();
  }  ?>
        <div id="message" class="updated fade"><p><strong><?php _e('Solr for Wordpress Configured for All Blogs!', 'solr4wp') ?></strong></p></div>


<div class="wrap">
<h2><?php _e('Solr For WordPress', 'solr4wp') ?></h2>

<form method="post" action="options-general.php?page=solr-for-wordpress/solr-for-wordpress.php">
<h3><?php _e('Configure Solr', 'solr4wp') ?></h3>

<div class="solr_admin clearfix">
	<div class="solr_adminR">
		<div class="solr_adminR2" id="solr_admin_tab2">
			<label><?php _e('Solr Host', 'solr4wp') ?></label>
			<input name="settings[s4w_server][type][update]" type="hidden" value="master" />
			<input name="settings[s4w_server][type][search]" type="hidden" value="master" />
			<p><input type="text" name="settings[s4w_server][info][single][host]" value="<?php echo $s4w_settings['s4w_server']['info']['single']['host']?>" /></p>
			<label><?php _e('Solr Port', 'solr4wp') ?></label>
			<p><input type="text" name="settings[s4w_server][info][single][port]" value="<?php echo $s4w_settings['s4w_server']['info']['single']['port']?>" /></p>
			<label><?php _e('Solr Path', 'solr4wp') ?></label>
			<p><input type="text" name="settings[s4w_server][info][single][path]" value="<?php echo $s4w_settings['s4w_server']['info']['single']['path']?>" /></p>
		</div>
		<div class="solr_adminR2" id="solr_admin_tab3">
		  <table>
  		  <tr>
  		  <?php 
  		    //we are working with multiserver setup so lets
  		    //lets provide an extra fields for extra host on the fly by appending an empty array
  		    //this will always give a count of current servers+1
  		    $serv_count = count($s4w_settings['s4w_server']['info']);
  		    $s4w_settings['s4w_server']['info'][$serv_count] = array('host'=>'','port'=>'', 'path'=>'');
  		    foreach ($s4w_settings['s4w_server']['info'] as $server_id => $server) { 
                      if ($server_id == "single")
                        continue;
  		      //lets set serverIDs
  		      $new_id =(is_numeric($server_id)) ? 'slave_'.$server_id : $server_id ;
  		  ?>
    		  <td>
    		  <label><?php _e('ServerID', 'solr4wp') ?>: <strong><?php echo $new_id; ?></strong></label>
    		  <p>Update Server: &nbsp;&nbsp;<input name="settings[s4w_server][type][update]" type="radio" value="<?php echo $new_id?>" <?php s4w_checkConnectOption($s4w_settings['s4w_server']['type']['update'], $new_id); ?> /></p>
    			<p>Search Server: &nbsp;&nbsp;<input name="settings[s4w_server][type][search]" type="radio" value="<?php echo $new_id?>" <?php s4w_checkConnectOption($s4w_settings['s4w_server']['type']['search'], $new_id); ?> /></p>
    		  <label><?php _e('Solr Host', 'solr4wp') ?></label>
    			<p><input type="text" name="settings[s4w_server][info][<?php echo $new_id ?>][host]" value="<?php echo $server['host'] ?>" /></p>
    			<label><?php _e('Solr Port', 'solr4wp') ?></label>
    			<p><input type="text" name="settings[s4w_server][info][<?php echo $new_id ?>][port]" value="<?php echo $server['port'] ?>" /></p>
    			<label><?php _e('Solr Path', 'solr4wp') ?></label>
    			<p><input type="text" name="settings[s4w_server][info][<?php echo $new_id ?>][path]" value="<?php echo $server['path'] ?>" /></p>		  
    			</td>
    			<?php 
    			  }
    			?>
  			</tr>
			</table>
		</div>		
	</div>
	<ol>
		<li id="solr_admin_tab1_btn" class="solr_admin_tab1">
		</li>
		<li id="solr_admin_tab2_btn" class="solr_admin_tab2">
			<h4><input id="solrconnect_single" name="settings[s4w_connect_type]" type="radio" value="solr_single" <?php s4w_checkConnectOption($s4w_settings['s4w_connect_type'], 'solr_single'); ?> onclick="switch1();" />Single Solr Server</h4>
			<ol>
				<li>Download, install and configure your own <a href="http://lucene.apache.org/solr/">Apache Solr</a> instance</li>
			</ol>
		</li>
		<li id="solr_admin_tab3_btn" class="solr_admin_tab3">
			<h4><input id="solrconnect_separated" name="settings[s4w_connect_type]" type="radio" value="solr_separated" <?php s4w_checkConnectOption($s4w_settings['s4w_connect_type'], 'solr_separated'); ?> onclick="switch1();" />Separated Solr Servers</h4>
			<ol>
				<li>Separate URL's for updates and searches.</li>
			</ol>
		</li>		
	</ol>
</div>
<hr />
<h3><?php _e('Indexing Options', 'solr4wp') ?></h3>
<table class="form-table">
    <tr valign="top">
        <th scope="row" style="width:200px;"><?php _e('Index Pages', 'solr4wp') ?></th>
        <td style="width:10px;float:left;"><input type="checkbox" name="settings[s4w_index_pages]" value="1" <?php echo s4w_checkCheckbox($s4w_settings['s4w_index_pages']); ?> /></td>
        <th scope="row" style="width:200px;float:left;margin-left:20px;"><?php _e('Index Posts', 'solr4wp') ?></th>
        <td style="width:10px;float:left;"><input type="checkbox" name="settings[s4w_index_posts]" value="1" <?php echo s4w_checkCheckbox($s4w_settings['s4w_index_posts']); ?> /></td>
    </tr>

    <tr valign="top">
        <th scope="row" style="width:200px;"><?php _e('Remove Page on Delete', 'solr4wp') ?></th>
        <td style="width:10px;float:left;"><input type="checkbox" name="settings[s4w_delete_page]" value="1" <?php echo s4w_checkCheckbox($s4w_settings['s4w_delete_page']); ?> /></td>
        <th scope="row" style="width:200px;float:left;margin-left:20px;"><?php _e('Remove Post on Delete', 'solr4wp') ?></th>
        <td style="width:10px;float:left;"><input type="checkbox" name="settings[s4w_delete_post]" value="1" <?php echo s4w_checkCheckbox($s4w_settings['s4w_delete_post']); ?> /></td>
    </tr>
    
    <tr valign="top">
        <th scope="row" style="width:200px;"><?php _e('Remove Page on Status Change', 'solr4wp') ?></th>
        <td style="width:10px;float:left;"><input type="checkbox" name="settings[s4w_private_page]" value="1" <?php echo s4w_checkCheckbox($s4w_settings['s4w_private_page']); ?> /></td>
        <th scope="row" style="width:200px;float:left;margin-left:20px;"><?php _e('Remove Post on Status Change', 'solr4wp') ?></th>
        <td style="width:10px;float:left;"><input type="checkbox" name="settings[s4w_private_post]" value="1" <?php echo s4w_checkCheckbox($s4w_settings['s4w_private_post']); ?> /></td>
    </tr>

    <tr valign="top">
        <th scope="row" style="width:200px;"><?php _e('Index Comments', 'solr4wp') ?></th>
        <td style="width:10px;float:left;"><input type="checkbox" name="settings[s4w_index_comments]" value="1" <?php echo s4w_checkCheckbox($s4w_settings['s4w_index_comments']); ?> /></td>
    </tr>
        
    <?php
    //is this a multisite installation
    if (is_multisite() && is_main_site()) {
    ?>
    
    <tr valign="top">
        <th scope="row" style="width:200px;"><?php _e('Index all Sites', 'solr4wp') ?></th>
        <td style="width:10px;float:left;"><input type="checkbox" name="settings[s4w_index_all_sites]" value="1" <?php echo s4w_checkCheckbox($s4w_settings['s4w_index_all_sites']); ?> /></td>
    </tr>
    <?php
    }
    ?>
    <tr valign="top">
        <th scope="row"><?php _e('Index custom fields (comma separated names list)') ?></th>
        <td><input type="text" name="settings[s4w_index_custom_fields]" value="<?php print( s4w_filter_list2str($s4w_settings['s4w_index_custom_fields'], 'solr4wp')); ?>" /></td>
    </tr>
    <tr valign="top">
        <th scope="row"><?php _e('Excludes Posts or Pages (comma separated ids list)') ?></th>
        <td><input type="text" name="settings[s4w_exclude_pages]" value="<?php print(s4w_filter_list2str($s4w_settings['s4w_exclude_pages'], 'solr4wp')); ?>" /></td>
    </tr>
</table>
<hr />
<h3><?php _e('Result Options', 'solr4wp') ?></h3>
<table class="form-table">
    <tr valign="top">
        <th scope="row" style="width:200px;"><?php _e('Output Result Info', 'solr4wp') ?></th>
        <td style="width:10px;float:left;"><input type="checkbox" name="settings[s4w_output_info]" value="1" <?php echo s4w_checkCheckbox($s4w_settings['s4w_output_info']); ?> /></td>
        <th scope="row" style="width:200px;float:left;margin-left:20px;"><?php _e('Output Result Pager', 'solr4wp') ?></th>
        <td style="width:10px;float:left;"><input type="checkbox" name="settings[s4w_output_pager]" value="1" <?php echo s4w_checkCheckbox($s4w_settings['s4w_output_pager']); ?> /></td>
    </tr>
 
    <tr valign="top">
        <th scope="row" style="width:200px;"><?php _e('Output Facets', 'solr4wp') ?></th>
        <td style="width:10px;float:left;"><input type="checkbox" name="settings[s4w_output_facets]" value="1" <?php echo s4w_checkCheckbox($s4w_settings['s4w_output_facets']); ?> /></td>
        <th scope="row" style="width:200px;float:left;margin-left:20px;"><?php _e('Category Facet as Taxonomy', 'solr4wp') ?></th>
        <td style="width:10px;float:left;"><input type="checkbox" name="settings[s4w_cat_as_taxo]" value="1" <?php echo s4w_checkCheckbox($s4w_settings['s4w_cat_as_taxo']); ?> /></td>
    </tr>

    <tr valign="top">
        <th scope="row" style="width:200px;"><?php _e('Categories as Facet', 'solr4wp') ?></th>
        <td style="width:10px;float:left;"><input type="checkbox" name="settings[s4w_facet_on_categories]" value="1" <?php echo s4w_checkCheckbox($s4w_settings['s4w_facet_on_categories']); ?> /></td>
        <th scope="row" style="width:200px;float:left;margin-left:20px;"><?php _e('Tags as Facet', 'solr4wp') ?></th>
        <td style="width:10px;float:left;"><input type="checkbox" name="settings[s4w_facet_on_tags]" value="1" <?php echo s4w_checkCheckbox($s4w_settings['s4w_facet_on_tags']); ?> /></td>
    </tr>
    
    <tr valign="top">
        <th scope="row" style="width:200px;"><?php _e('Author as Facet', 'solr4wp') ?></th>
        <td style="width:10px;float:left;"><input type="checkbox" name="settings[s4w_facet_on_author]" value="1" <?php echo s4w_checkCheckbox($s4w_settings['s4w_facet_on_author']); ?> /></td>
        <th scope="row" style="width:200px;float:left;margin-left:20px;"><?php _e('Type as Facet', 'solr4wp') ?></th>
        <td style="width:10px;float:left;"><input type="checkbox" name="settings[s4w_facet_on_type]" value="1" <?php echo s4w_checkCheckbox($s4w_settings['s4w_facet_on_type']); ?> /></td>
    </tr>

     <tr valign="top">
         <th scope="row" style="width:200px;"><?php _e('Taxonomy as Facet', 'solr4wp') ?></th>
         <td style="width:10px;float:left;"><input type="checkbox" name="settings[s4w_facet_on_taxonomy]" value="1" <?php echo s4w_checkCheckbox($s4w_settings['s4w_facet_on_taxonomy']); ?> /></td>
      </tr>
      
    <tr valign="top">
        <th scope="row"><?php _e('Custom fields as Facet (comma separated ordered names list)') ?></th>
        <td><input type="text" name="settings[s4w_facet_on_custom_fields]" value="<?php print( s4w_filter_list2str($s4w_settings['s4w_facet_on_custom_fields'], 'solr4wp')); ?>" /></td>
    </tr>

    <tr valign="top">
        <th scope="row" style="width:200px;"><?php _e('Enable Spellchecking', 'solr4wp') ?></th>
        <td style="width:10px;float:left;"><input type="checkbox" name="settings[s4w_enable_dym]" value="1" <?php echo s4w_checkCheckbox($s4w_settings['s4w_enable_dym']); ?> /></td>
    </tr>
                   
    <tr valign="top">
        <th scope="row"><?php _e('Number of Results Per Page', 'solr4wp') ?></th>
        <td><input type="text" name="settings[s4w_num_results]" value="<?php _e($s4w_settings['s4w_num_results'], 'solr4wp'); ?>" /></td>
    </tr>   
    
    <tr valign="top">
        <th scope="row"><?php _e('Max Number of Tags to Display', 'solr4wp') ?></th>
        <td><input type="text" name="settings[s4w_max_display_tags]" value="<?php _e($s4w_settings['s4w_max_display_tags'], 'solr4wp'); ?>" /></td>
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

    <?php if(is_multisite()) { ?>
    <tr valign="top">
        <th scope="row"><?php _e('Push Solr Configuration to All Blogs', 'solr4wp') ?></th>
        <td><input type="submit" class="button-primary" name="s4w_init_blogs" value="<?php _e('Execute', 'solr4wp') ?>" /></td>
    </tr>
    <?php } ?>
    
 
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
