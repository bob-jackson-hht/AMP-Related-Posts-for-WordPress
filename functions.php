// place this code snippet in your theme functions.php

// Contextly Related Posts for Google AMP
include_once( get_stylesheet_directory() . '/include/amp-contextly-related-posts.php' ); 

/* Called by <?php do_action( 'amp_get_contextly_related_posts' ); ?>
   in the AMP page template. 
*/
function ampDisplayContextlyRelatedPosts() {
	if ( !is_amp_endpoint() )
		return;

	$amp_related_posts = new AmpContextlyRelatedPosts();

	if ( $amp_related_posts->getInitStatus() == false )	
		return;	// class initialization failed. Nothing will be displayed on the AMP page
		
	// The Related & Interesting posts will be displayed if successful,
	// otherwise nothing is shown on the web page. 
	$amp_related_posts->ampRenderContextlyRelatedPosts();
}
add_action( 'amp_get_contextly_related_posts' , 'ampDisplayContextlyRelatedPosts' );