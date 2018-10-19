<?php
/*	
*	Plugin Name: Yelp Communities
*	Description: Show nearby schools, restaurants reviews,  nearby shops and entertainment
*	Version: 1.0
*	Author: McNichols Design
*	Author URI: https://mcnichols.design	
*/

defined('ABSPATH') or die("Say What?");
remove_filter( 'the_content', 'wpautop' );
remove_filter( 'the_excerpt', 'wpautop' );
wp_register_script('ajax-script', plugins_url( 'my_query.js', __FILE__ ));
wp_localize_script( 'ajax-script', 'ajax_object', array( 'ajax_url' => admin_url( 'admin-ajax.php' ) ) );
wp_enqueue_script( 'ajax-script' );
add_action( 'wp_ajax_nopriv_my_community', 'my_community' );
add_action( 'wp_ajax_my_community', 'my_community' );

function my_community() {
	$term = $_POST['search'];
	$term = strtolower($term);
	$location = $_POST['location'];
	$location = strtolower($location);
	$radius = $_POST['radius'];
	$limit = $_POST['limit'];
	$show = do_shortcode('[yelp term="'.$term.'" location="'.$location.'" radius="'.$radius.'" limit="'.$limit.'"]');
	echo $show;
	wp_die();
}
function yelp_communities_style() {
	$css = plugins_url('includes/css/style.css',__FILE__);
	$form = plugins_url('includes/css/form.css',__FILE__);
	wp_enqueue_style('yelp-communities',$css,array(),'1.0','all');
	wp_enqueue_style('yelp-communities-form',$form,array(),'1.0','all');
	wp_enqueue_script('jquery', 'https://ajax.googleapis.com/ajax/libs/jquery/3.1.1/jquery.min.js', array(), null, true);
}

add_action('wp_enqueue_scripts','yelp_communities_style',PHP_INT_MAX);
add_action('admin_menu', function() {
	
    add_options_page('Yelp Communities Initial Settings','Yelp Communities','manage_options','yelp-communities','yelp_communities');
	
});

add_action('admin_init', function() {
	$home = home_url();
	register_setting('yelp-communities-settings', 'yelp_keys');
	register_Setting('yelp-communities-settings', 'yelp_keys_api');
	
	
});
function yelp_search() {
	?>
		<script>
			jQuery("#yelpForm").on('change', function(e) {
				var term = jQuery("#yelpForm").val();
				jQuery(".frankcity-container").css({"display":"none"});
				jQuery('.community-info').fadeOut('fast');
				var post_id = jQuery('#post_id').val();
				var city = jQuery('#city').val();
				var state = jQuery('#state').val();
				jQuery.ajax({
					url: '',
					async:false,
					data: {
						action  :'community_request',
						term    : term,
						city    : city,
						state   : state,
						post_id : post_id
					},
					success:function(data) {
						// This outputs the result of the ajax request
						jQuery('.community-info').html(data).fadeIn('fast');

					},
					error: function(errorThrown){
						console.log(errorThrown);
					}
				});
			});
		</script>
	<?php
}
function yelp_communities() {
	
	?>
		<div class="wrap">
			<form action="options.php" method="post">
				<?php
					settings_fields('yelp-communities-settings');
					do_settings_sections('yelp-communities-settings');
				?>
				<table>
					<tr>
						<th>Yelp API Key</th>
						<td><input type="text" placeholder="Your Yelp API Key" name="yelp_keys_api" value="<?php echo esc_attr( get_option('yelp_keys_api') ); ?>" size="50"/></td>
					</tr>
					<tr>
						<td><?php submit_button(); ?></td>
					</tr>
				</table>
			</form>
			<p>Usage: [yelp term="Entertainment" location="City name, State" radius="5" limit="6"]</p>
		</div>
	<?php
}

function GetYelp($atts) {
	if(isset($_GET['search'])) {
		$term = $_GET['search'];
		$term = strtolower($term);
		$key = get_option('yelp_keys_api');
		$method = 'GET';
		if(isset($atts['location'])) {		
			$location = $atts['location'];
		} else {
			$location = "Naples, FL";
		}
		if(isset($atts['radius'])) {
			$radius = $atts['radius'];
			$radius = $radius * 1609;
		} else {
			$radius = 8046;
		}
		if(isset($atts['limit'])) {
			$limit = $atts['limit'];
		} else {
			$limit = 20;
		}
		if($term == "schools") {
			$html = "";
			$html .= getSchools($term,$location,$radius,1); 
			return $html;
		} else if($term == "listings") {
			$html = "";
			$html .= "<style>.yelp-listings { display: unset !important; }</style>";
			return $html;
		}		
	} else {
		if(isset($atts['term'])) {
		$term = $atts['term'];
		$key = get_option('yelp_keys_api');
		$method = 'GET';
		if(isset($atts['location'])) {		
			$location = $atts['location'];
		} else {
			$location = "Eugene, OR";
		}
		if(isset($atts['radius'])) {
			$radius = $atts['radius'];
			$radius = $radius * 1609;
		} else {
			$radius = 8046;
		}
		if(isset($atts['limit'])) {
			$limit = $atts['limit'];
		} else {
			$limit = 20;
		}
		}
	}
		$url = "https://api.yelp.com/v3/businesses/search";
		$args = array(
			'term' => $term,
			'location' => $location,
			'radius' => $radius,
			'limit' => $limit
		);
		$api_url = add_query_arg( $args, $url );
		$access_token = $key;
		$api_endpoint = $api_url;

		$args = array(
			'user-agent' => '',
			'headers' => array(
				'Authorization' => 'Bearer ' . $access_token
			)
		);

		$response = wp_remote_get( $api_endpoint, $args );
		$html = "";
		$html .= YelpResults(json_decode($response['body'],true));
		return $html;
}	

function YelpResults($data) {
	$dir = plugin_dir_url(__FILE__);
	$html .= "
			<style>
			.yelp-listings {
				display: none;
			}
			.yelp-communities {
				display: flex;
				flex-wrap: wrap;
				width: 100%;
			}
			.yelp-item {
				background-size: cover;
				background-repeat: no-repeat;
				height: auto;
				float: left;
				position: relative;
				margin: 0 auto;
				display: flex;
				flex-direction: column;
			}
			.yelp-overlay {
				background: rgba(0,0,0,0.6);
				min-height: 100%;
			}
			.yelp-title {
				margin: 0;
				padding: 0;
			}
			.yelp-link {
				color: #fff;
			}
			img.rating {
				border: 0px;
			}
			.review-count {
				color: #fff;
			}
			.yelp-branding {
				position: absolute;
				bottom: 0;
				right: 0;
			}
			.yelp-branding img {
				border: none;
				height: 40px;
			}
			.yelp-address-wrap {
				color: #fff;
			}
			.yelp-phone a {
				color: #fff;
			}
			@media only screen and (min-width:1024px) {
				.yelp-item {
					width: calc(100% / 3);
					max-height: 180px;
				}
			}
			@media only screen and (max-width: 1024px) {
				.yelp-item {
					width: calc(100% / 2);
					max-height: 180px;
				}
			}
			@media only screen and (max-width: 640px) {
				.yelp-item {
					width: calc(100% / 1);
					max-height: 180px;
				}
			}
			@media only screen and (max-width: 480) {
				.yelp-item {
					width: 100%;
					max-height: 180px;
				}
			}
		</style>
		<div class='yelp-communities'>
	";
	foreach($data['businesses'] as $location) {
			$url = $location['url'];
			$img = $location['image_url'];
			$bizName = $location['name'];
			$street = $location['location']['address1'];
			$city = $location['location']['city'];
			$state = $location['location']['state'];
			$zip = $location['location']['zip_code'];
			$phone = $location['phone'];
			$phoneDisplay = $location['display_phone'];
			$numReviews = $location['review_count'];
			$rating = $location['rating'];
			switch ($rating) {
				case 0:
					$stars = plugin_dir_url('yelp-communities/includes/images/').'images/regular_0.png';
					break;
				case 1:
					$stars = plugin_dir_url('yelp-communities/includes/images/').'images/regular_1.png';
					break;
				case 1.5:
					$stars = plugin_dir_url('yelp-communities/includes/images/').'images/regular_1_half.png';
					break;
				case 2:
					$stars = plugin_dir_url('yelp-communities/includes/images/').'images/regular_2.png';
					break;
				case 2.5:
					$stars = plugin_dir_url('yelp-communities/includes/images/').'images/regular_2_half.png';
					break;
				case 3:
					$stars = plugin_dir_url('yelp-communities/includes/images/').'images/regular_3.png';
					break;
				case 3.5:
					$stars = plugin_dir_url('yelp-communities/includes/images/').'images/regular_3_half.png';
					break;
				case 4:
					$stars = plugin_dir_url('yelp-communities/includes/images/').'images/regular_4.png';
					break;
				case 4.5:
					$stars = plugin_dir_url('yelp-communities/includes/images/').'images/regular_4_half.png';
					break;
				case 5:
					$stars = plugin_dir_url('yelp-communities/includes/images/').'images/regular_5.png';
					break;
			}
			$html .= '<div class="yelp-item" style="background-image:url(\''.$img.'\');">';
			$html .= '<div class="yelp-overlay">';
			$html .= '<div class="more-info"><a target="_blank" href="'.$url.'"><span>More Info</span></a></div>';
			$html .= '<h3 class="yelp-title">';
			$html .= '<a target="_blank" href="'.$url.'" class="yelp-link">'.$bizName.'</a>';
			$html .= '</h3>';
			$html .= '<p>';
			$html .= '<img class="rating" src="'.$stars.'" alt="'.$bizName.' Yelp Rating" title="'.$bizName.' Yelp Rating" />';
			$html .= '<span class="review-count">'.$numReviews.' reviews</span>';
			$html .= '<a class="yelp-branding" href="'.$url.'" target="_blank"><img src="'.plugins_url('yelp-communities/includes/images/yelp.png',$css,array(),'1.0','all').'" alt="Powered by Yelp"></a>';
			$html .= '</p>';
			$html .= '<div class="yelp-address-wrap">';
			$html .= '<address>';
			$html .= $street. '<br>'.$city.', '.$state.' '.$zip;
			$html .= '</address>';
			$html .= '</div>';
			$html .= '<div class="yelp-phone">';
			$html .= '<a href="tel:'.$phone.'">'.$phoneDisplay.'</a>';
			$html .= '</div>';
			$html .= '</div>';
			$html .= '</div>';
	}
	$html .= '</div>';
	return $html;
}
function getSchools($term, $location, $radius, $limit) {
		$url = "https://api.yelp.com/v3/businesses/search";
		$args = array(
			'term' => $term,
			'location' => $location,
			'radius' => $radius,
			'limit' => $limit
		);
		$key = get_option('yelp_keys_api');
		$method = 'GET';
		$api_url = add_query_arg( $args, $url );
		$access_token = $key;
		$api_endpoint = $api_url;

		$args = array(
			'user-agent' => '',
			'headers' => array(
				'Authorization' => 'Bearer ' . $access_token
			)
		);

		$response = wp_remote_get( $api_endpoint, $args );
		$response = json_decode($response['body'],true);
		$city = $response['businesses'][0]['location']['city'];
		$state = $response['businesses'][0]['location']['state'];
		$lat = $response['businesses'][0]['coordinates']['latitude'];
		$long = $response['businesses'][0]['coordinates']['longitude'];
		$html .= "<div id=\"yelp-communities-wrap\">
		<script type='text/javascript' src='http://localhost/transponder/wp-includes/js/jquery/jquery.js?ver=1.12.4'></script>
		<h2>Local Schools</h2>
		<div id=\"2903_widget\" class=\"schools-content\" style=\"width:100%; margin-bottom:10px;\"></div>
		<script>
			function adjustIframes2903_widget(){

				var widget_width = jQuery('#2903_widget').width();
				var widget_height = widget_width * .9;

				if(widget_width <= 600) {
					widget_height = widget_width * 1.5;
				}
				
				jQuery('#2903_widget').html('<iframe className=\"greatschools\" src=\"//www.greatschools.org/widget/map?searchQuery=&textColor=0066B8&borderColor=FFCC66&lat=$lat&lon=$long&cityName=$city&state=$state&normalizedAddress=$location%2C%20USA&width=' + widget_width + '&height=' + widget_height + '&zoom=14\" width=\"' + widget_width + '\" height=\"' + widget_height + '\" marginHeight=\"0\" marginWidth=\"0\" frameBorder=\"0\" scrolling=\"no\"></iframe>');

			}
			jQuery(window).on('resize load',adjustIframes2903_widget);
			window.dispatchEvent(new Event('resize'));
			jQuery( document ).ready(function() {
				var widget_width = jQuery('#2903_widget').width();
				var widget_height = widget_width * .9;

				if(widget_width <= 600) {
					widget_height = widget_width * 1.5;
				}
				
				jQuery('#2903_widget').html('<iframe className=\"greatschools\" src=\"//www.greatschools.org/widget/map?searchQuery=&textColor=0066B8&borderColor=FFCC66&lat=$lat&lon=$long&cityName=$city&state=$state&normalizedAddress=$location%2C%20USA&width=' + widget_width + '&height=' + widget_height + '&zoom=14\" width=\"' + widget_width + '\" height=\"' + widget_height + '\" marginHeight=\"0\" marginWidth=\"0\" frameBorder=\"0\" scrolling=\"no\"></iframe>');
			});
		</script></div>";
		return $html;
	}
add_shortcode("yelp","GetYelp");