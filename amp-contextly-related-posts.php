<?php
/*
  Related Posts for Accelerated Mobile Pages in WordPress
  Copyright (C) 2016 Bob Jackson, HandymanHowTo.com
  Contact: bob@handymanhowto.com

  This program is free software: you can redistribute it and/or modify
  it under the terms of the GNU Affero General Public License as
  published by the Free Software Foundation, either version 3 of the
  License, or (at your option) any later version.

  This program is distributed in the hope that it will be useful,
  but WITHOUT ANY WARRANTY; without even the implied warranty of
  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
  GNU Affero General Public License for more details.

  You should have received a copy of the GNU Affero General Public License
  along with this program. If not, see <http://www.gnu.org/licenses/>.
*/

class AmpContextlyRelatedPosts
{
	const CONTEXTLY_API_BASE_URL = 'https://rest.contextly.com/pagewidgets/get/?url=';
	const CONTEXTLY_API_LOAD_SNIPPET = '&load_snippet=true';
	const MUSTACHE_INSTALL_DIR = 'wp-content/lib/mustache/'; // mustache install folder
	const MUSTACHE_AUTOLOADER_FILE = 'src/Mustache/Autoloader.php'; // path under /lib/mustache/
	const MUSTACHE_TEMPLATE_DIR = '/amp/templates'; // subdirectory for Related Posts Mustache template
	const MUSTACHE_TEMPLATE_NAME = 'relatedposts';	// relatedposts.mustache template file
	const PRODUCTION_SITE_DOMAIN = 'handymanhowto.com'; // change this to your production domain
	const DEV_SITE_DOMAIN = 'handymanhowto.staging.wpengine.com'; // change this to your developement domain.
	const MAX_DISPLAY_POSTS = 5; // Max # of Related or Interesting posts to display.

	private $is_dev_site = false; 		// true if the post url contains the DEV_SITE_DOMAIN
	private $post_perma_link = false;	// Post to get  the Related & Interesting posts

	// Get the post permalink for the post ID arg or use the post ID of the current AMP page.
	function __construct( $post_id = null )
	{
		global $wp_query;
		
		if ( $post_id == null )
			$the_post_id = $wp_query->post->ID; // use the ID of the currently displayed post
		else
			$the_post_id = $post_id;	// else use the constructor post id

		// Must use the canonical URL instead of the Post ID for the Contextly API call 
		// because the $wp_query->post->ID value may be different than the production site 
		// if working on a development	site using different domain. 
		$this->post_perma_link = get_permalink( $the_post_id );		
		if ( $this->post_perma_link == false )
			return; // can't proceed without a valid permalink
		
		// check if running on the development domain
		if ( strpos( $this->post_perma_link, self::DEV_SITE_DOMAIN ) !== false )
			$this->is_dev_site = true;	// running on the development site. Will have to fixup domains later. 
	}
	
	function __destruct()
	{
		// nothing to do here.
	}

	public function getInitStatus() 
	{
		return ($this->post_perma_link == false ? false : true); // returns true if valid permalink 
	}

	public function ampRenderContextlyRelatedPosts()
	{
		$production_perma_link = $this->post_perma_link;
		
		// If running on the staging site the domain will be different vs the production site.
		// Because Contextly only knows the production domain name, must always use the
		// it for the REST API call. 
		if ( $this->is_dev_site == true )
	  	$production_perma_link = str_ireplace ( self::DEV_SITE_DOMAIN , self::PRODUCTION_SITE_DOMAIN , $this->post_perma_link );
	
		$api_data = $this->getContextlyAPIData( $production_perma_link );
		if ( $api_data == false )
			return; // abort. nothing will be displayed

		if ( $this->validateContextlyAPIData( $api_data ) == false )
			return; // abort. nothing will be displayed

		// Related Posts are in the 'previous' array.
		$this->ampDisplayContextualPosts(
				$this->getMustachePostData( $api_data->entry->snippets[0]->links->previous, 
																		$api_data->entry->snippets[0]->settings->previous_subhead ) 
		);
		
		// Interesting Posts are in the 'interesting' array.
		$this->ampDisplayContextualPosts( 
				$this->getMustachePostData( $api_data->entry->snippets[0]->links->interesting, 
																		$api_data->entry->snippets[0]->settings->interesting_subhead ) 
		);
	}

	// Build the Mustache data array for the Mustache template
	private function getMustachePostData( $post_links, $title )
	{
		// Related or Interesting Post entries to be rendered by the relatedposts.mustache template file.
		$mustache_array = array (
			'show_content' => true,
			'subhead_title' => $title,
			'content' => array (),
		);

		$display_count = 0;
		foreach( $post_links as $contextual_post ) {
			$nurl = $contextual_post->native_url;
			
			// Check if running on the WP Engine staging site and change the Contextly
			// native URL to the staging domain. This is needed for the url_to_postid()
			// return the staging site post ID.
			if ( $this->is_dev_site == true )
				$nurl = str_ireplace( self::PRODUCTION_SITE_DOMAIN, self::DEV_SITE_DOMAIN, $nurl );
			
			$post_id = url_to_postid( $nurl );	// for the call to amp_get_permalink()

			array_push ( $mustache_array['content'], array (
					'post_title' => $contextual_post->title,
					'post_thumbnail_url' => $contextual_post->thumbnail_url,
					'post_url' => amp_get_permalink( $post_id )	// link to the AMP version of the related post
				)
			);
			$display_count++;
			if ( $display_count == self::MAX_DISPLAY_POSTS )
				break;
		}

		return $mustache_array;	// data ready for rending in the Mustache template
	}

	// Call the Contextly API to retrieve the Related & Interesting posts
	private function getContextlyAPIData( $post_url )
	{
		// Contextly API Get request format: 
		// https://rest.contextly.com/pagewidgets/get/?url={url}&load_snippet=true"
		// The {url} parameter is % encoded.
		$api_request = self::CONTEXTLY_API_BASE_URL . urlencode( $post_url ) . self::CONTEXTLY_API_LOAD_SNIPPET;
		$api_response = wp_remote_get( $api_request  );

		if ( is_wp_error( $api_response ) )
			return false;

		$api_data = json_decode( wp_remote_retrieve_body( $api_response ) ); // JSON object format
		return $api_data;
	}

	// HTML output using Mustache templates.
	private function ampDisplayContextualPosts( $mustache_data )
	{
		if ( !is_array( $mustache_data ) )
			return;	// do nothing, nothing will be rendered on the web page.
		
		$mustache_dir = ABSPATH . self::MUSTACHE_INSTALL_DIR; 
		require_once( $mustache_dir . self::MUSTACHE_AUTOLOADER_FILE );
		Mustache_Autoloader::register();
		$mustache = new Mustache_Engine ( 
			array( 'loader' => new Mustache_Loader_FilesystemLoader( get_stylesheet_directory() . self::MUSTACHE_TEMPLATE_DIR ) )
		);

		// display the Related & Interesting posts
		echo $mustache->render( self::MUSTACHE_TEMPLATE_NAME, $mustache_data );
	}

	/*******************************************************************************************
		validateContextlyAPIData( $api_data )
		
		This validation function only examines the relevant properties in the Contextly API
		results needed to extract the Related & Interesting posts.
		
		$api_data parameter is the decoded JSON object from the API call. For reference should
		the Contextly API spec changes, the print_r( $api_data ) object structure is expected to 
		be as follows:

	stdClass Object (
		[api_name] => pagewidgets 
		[api_method] => get 
		[success] => 1 
		[success_code] => 200
		[page_id] => e285dccf42cd403ee98a3ad3eab46470 
		[entry] => stdClass Object ( 
			[snippets] => Array ( 
				[0] => stdClass Object ( 
					[type] => normal 
					[custom_id] => e285dccf42cd403ee98a3ad3eab46470 
					[settings] => stdClass Object ( 
						[previous_subhead] => Related 
						[interesting_subhead] => Explore HandymanHowto.com 
						[web_subhead] => Story Resources 
						[custom_subhead] => Custom 
						[custom2_subhead] => Custom 
						[display_type] => blocks2 
						[images_type] => square150x150 
						[display_thumbnails] => 1 
						[display_link_dates] => 
						[display_sections] => Array ( 
							[0] => previous 
							[1] => interesting 
						) 
						[utm_enable] => 1 
						[css] => stdClass Object ( 
							[title_font_family] => Montserrat 
							[title_font_size] => 19px 
							[title_color] => #6d6d6d 
							[links_font_family] => Tahoma,Geneva,sans-serif 
							[links_font_size] => 16px 
							[links_color] => #6d6d6d 
							[border_color] => #e3e3e3 
							[background_color] => transparent 
							[custom_code] => 
						) 
						[external_fonts] => Array ( [0] => Montserrat ) 
					) 
					[links] => stdClass Object ( 
						[previous] => Array ( 
							[0] => stdClass Object ( 
								[id] => 10197751 
								[id_seo] => Tsik2BNkrP 
								[title] => Delta Kitchen Faucet Water Line Connections 
								[type] => recent 
								[url] => http://contextly.com/redirect/?id=Tsik2BNkrP:e<snipped for brevity> 
								[algorithm_id] => 3 
								[native_url] => https://www.handymanhowto.com/delta-kitchen-faucet-water-line-connections/ 
								[thumbnail_url] => https://imgstorage1.contextly.com/thumbnails/handymanhowto/7393029/150x150.jpg 
							) 
							[1] => stdClass Object ( 
								[id] => 10197732 
								[id_seo] => EzWkVdkpne 
								[title] => How to Install a Delta Single Handle Kitchen Faucet 
								[type] => recent 
								[url] => http://contextly.com/redirect/?id=EzWkVdkpne:e<snipped for brevity>  
								[algorithm_id] => 3 
								[native_url] => https://www.handymanhowto.com/install-delta-single-handle-kitchen-faucet/ 
								[thumbnail_url] => https://imgstorage1.contextly.com/thumbnails/handymanhowto/7397498/150x150.jpg 
							)
							// etc. [previous] array elements for the remaining articles
						)
						[interesting] => Array ( 
							[0] => stdClass Object ( 
								[id] => 6175920 
								[id_seo] => iGjCebBjZf 
								[title] => How to Repair Drywall Ceiling Water Damage 
								[type] => interesting 
								[url] => http://contextly.com/redirect/?id=iGjC<snipped for brevity> 
								[algorithm_id] => 16 
								[native_url] => http://www.handymanhowto.com/how-to-repair-drywall-ceiling-water-damage-part-1/ 
								[thumbnail_url] => https://imgstorage1.contextly.com/thumbnails/handymanhowto/2608505/150x150.jpg 
							)
							[1] => stdClass Object ( 
								[id] => 6175780 
								[id_seo] => dyXNtpt9E8 
								[title] => How to Add an Air Duct to a Room 
								[type] => interesting 
								[url] => http://contextly.com/redirect/?id=dyX<snipped for brevity>
								[algorithm_id] => 16 
								[native_url] => http://www.handymanhowto.com/adding-a-room-air-duct-for-heating-cooling/ 
								[thumbnail_url] => https://imgstorage2.contextly.com/thumbnails/handymanhowto/5739553/150x150.jpg 
							) 
							// etc. [interesting] array elements for the remaining articles
						) 
					)
				)
			)
			[update] => 0 
		) 
		[guid] => 5794f4917954e0-85347730 
		[cookie_id] => iXo9T8r69ptVYO77NUkBT3yVT 
		[t0] => 1.69 
		[st] => 1 
		[t1] => 1.69 
		[v] => 4.0 
		[s] => 1 
	)
	*******************************************************************************************/
	private function validateContextlyAPIData( $api_data ) 
	{
		/* Work through the api response object to validate the expected 
		   structure, properties and success codes. Given the nested objects
		   and arrays using a foreach() loop isn't practical so each
		   required property is checked individually.
		   
		   JSON Schema Validation would be better, however Contextly hasn't
		   published a schema. Ref: http://json-schema.org/
		*/
		if( !is_object( $api_data ) )
	    return false;
		
		if ( !isset( $api_data->success ) )		
	    return false;

		if ( $api_data->success != '1' )
	    return false;
		
		if ( !isset( $api_data->success_code ) )
			return false;

		if ( $api_data->success_code != '200' ) 
			return false;

		if ( !isset( $api_data->entry->snippets ) ) 
			return false;

		if ( count( $api_data->entry->snippets ) == 0 )
			return false;
				
		if ( !isset( $api_data->entry->snippets[0]->settings->display_sections ) )
			return false;
			
		if ( count( $api_data->entry->snippets[0]->settings->display_sections ) == 0 )
			return false;
		
		if ( $api_data->entry->snippets[0]->settings->display_sections[0] != 'previous' &&
				 $api_data->entry->snippets[0]->settings->display_sections['1'] != 'interesting' ) 
			return false;

		if ( !isset( $api_data->entry->snippets[0]->settings->previous_subhead ) )
			return false;

		if ( !isset( $api_data->entry->snippets[0]->settings->interesting_subhead ) )
			return false;
		
		if ( !isset( $api_data->entry->snippets[0]->links ) )
			return false;
			
		if ( count( $api_data->entry->snippets[0]->links ) == 0 )
			return false;

		if ( !isset( $api_data->entry->snippets[0]->links->previous ) || 
		     !isset( $api_data->entry->snippets[0]->links->interesting ) )
			return false;

		if ( count( $api_data->entry->snippets[0]->links->previous ) == 0 )
			return false;

		if ( count( $api_data->entry->snippets[0]->links->interesting ) == 0 )
			return false;

		return true;	// API data structure is valid.
	}
}