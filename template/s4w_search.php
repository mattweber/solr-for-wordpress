<?php
/*
Template Name: Search
*/
?>

<?php get_header(); ?>
<div id="content">

<div class="solr clearfix">
	
<?php 
    $results = s4w_search_results(); 
    if (!isset($results['results']) || $results['results'] === NULL) {
        echo "<div class='solr_noresult'><h2>Sorry, search is unavailable right now</h2><p>Try again later?</p></div>";
    } 
    else {
    ?>

	<div class="solr1 clearfix">
		<div class="solr_search">
		    <?php if ($results['qtime']) {
                printf("<label class='solr_response'>Response time: <span id=\"qrytime\">{$results['qtime']}</span> s</label>");
            } 
            
            //if server id has been defined keep hold of it
            $server = $_GET['server'];
            if($server) {
              $serverval = '<input name="server" type="hidden" value="'.$server.'" />';
            }
            
            ?>

            <form name="searchbox" method="get" id="searchbox" action="">
			        <input id="qrybox" name="s" type="text" class="solr_field" value="<?php echo $results['query'] ?>"/>
			        <?php echo $serverval; ?>
			        <input id="searchbtn" type="submit" value="Search" />
            </form>
		</div>

		<?php if($results['dym']) {
			printf("<div class='solr_suggest'>Did you mean: <a href='%s'>%s</a> ?</div>", $results['dym']['link'], $results['dym']['term']);
		} ?>

	</div>

	<div class="solr2">

		<div class="solr_results_header clearfix">
			<div class="solr_results_headerL">

				<?php if ($results['hits'] && $results['query'] && $results['qtime']) {
				    if ($results['firstresult'] === $results['lastresult']) {
				        printf("Displaying result %s of <span id='resultcnt'>%s</span> hits", $results['firstresult'], $results['hits']);
				    } else {
				        printf("Displaying results %s-%s of <span id='resultcnt'>%s</span> hits", $results['firstresult'], $results['lastresult'], $results['hits']);
                    }
				} ?>

			</div>
			<div class="solr_results_headerR">
				<ol class="solr_sort2">
					<li class="solr_sort_drop"><a href="<?php echo $results['sorting']['scoredesc'] ?>">Relevance<span></span></a></li>					
					<li><a href="<?php echo $results['sorting']['datedesc'] ?>">Newest</a></li>					
					<li><a href="<?php echo $results['sorting']['dateasc'] ?>">Oldest</a></li>					
					<li><a href="<?php echo $results['sorting']['commentsdesc'] ?>">Most Comments</a></li>					
					<li><a href="<?php echo $results['sorting']['commentsasc'] ?>">Least Comments</a></li>					
				</ol>
				<div class="solr_sort">Sort by:</div>
			</div>
		</div>

		<div class="solr_results">
			
<?php 
                    
           
                    if ($results['hits'] === "0") {
					printf("<div class='solr_noresult'>
										<h2>Sorry, no results were found.</h2>
										<h3>Perhaps you mispelled your search query, or need to try using broader search terms.</h3>
										<p>For example, instead of searching for 'Apple iPhone 3.0 3GS', try something simple like 'iPhone'.</p>
									</div>\n");
			} else {
				printf("<ol>\n");
					foreach($results['results'] as $result) {
							printf("<li onclick=\"window.location='%s'\">\n", $result['permalink']);
							printf("<h2><a href='%s'>%s</a></h2>\n", $result['permalink'], $result['title']);
							printf("<p>%s <a href='%s'>(comment match)</a></p>\n", $result['teaser'], $result['comment_link']);
							printf("<label> By <a href='%s'>%s</a> in %s %s - <a href='%s'>%s comments</a></label>\n", 
							            $result['authorlink'], 
							            $result['author'], 
							            get_the_category_list( ', ', '', $result['id']), 
							            date('m/d/Y', strtotime($result['date'])), 
							            $result['comment_link'], 
							            $result['numcomments']);
							printf("</li>\n");
					}
				printf("</ol>\n");
			} ?>

			<?php if ($results['pager']) {
				printf("<div class='solr_pages'>");
				    $itemlinks = array();
				    $pagecnt = 0;
				    $pagemax = 10;
				    $next = '';
				    $prev = '';
				    $found = false;
					foreach($results['pager'] as $pageritm) {
						if ($pageritm['link']) {
						    if ($found && $next === '') {
						        $next = $pageritm['link'];
						    } else if ($found == false) {
						        $prev = $pageritm['link'];
						    }
						    
							$itemlinks[] = sprintf("<a href='%s'>%s</a>", $pageritm['link'], $pageritm['page']);
						} else {
						    $found = true;
							$itemlinks[] = sprintf("<a class='solr_pages_on' href='%s'>%s</a>", $pageritm['link'], $pageritm['page']);
						}
						
						$pagecnt += 1;
						if ($pagecnt == $pagemax) {
						    break;
						}
					}
					
					if ($prev !== '') {
					    printf("<a href='%s'>Previous</a>", $prev);
					}
					
					foreach ($itemlinks as $itemlink) {
					    echo $itemlink;
					}
					
					if ($next !== '') {
					    printf("<a href='%s'>Next</a>", $next);
					}
					
				printf("</div>\n");
			} ?>


		</div>	
	</div>

	<div class="solr3">
		<ul class="solr_facets">

			<li class="solr_active">
				<ol>
					<?php if ($results['facets']['selected']) {
					    foreach( $results['facets']['selected'] as $selectedfacet) {
					        printf("<li><span></span><a href=\"%s\">%s<b>x</b></a></li>", $selectedfacet['removelink'], $selectedfacet['name']);
					    }
					} ?>
				</ol>
			</li>

			<?php if ($results['facets'] && $results['hits'] != 1) {
					foreach($results['facets'] as $facet) {
					    if (sizeof($facet["items"]) > 1) { #don't display facets with only 1 value
  							printf("<li>\n<h3>%s</h3>\n", $facet['name']);
  							s4w_print_facet_items($facet["items"], "<ol>", "</ol>", "<li>", "</li>",
  																					"<li><ol>", "</ol></li>", "<li>", "</li>");
  							printf("</li>\n");
              }
					}
			} ?>

		</ul>
	</div>

</div>

</div>
<?php 
                
    } 
                get_footer(); ?>
