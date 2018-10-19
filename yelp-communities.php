<?php
/**
 * Plugin Name: Yelp Communities
 * Description: Show nearby services, restaturants and nearby businesses.
 * Version: 1.0
 * Author: McNichols Design
 * Author URI: https://mcnichols.design
 *
 * @since October 18, 2018
 * @package yelp-communities
 */

defined( 'ABSPATH' ) || die( 'Say What?' );


/**
 * Class YelpCommunities
 *
 * This class creates the Yelp Communities plugin.
 *
 * @since: October 19, 2018
 */
class YelpCommunities {
	/**
	 * Initialize Dependencies and Functions
	 *
	 * @since: October 19, 2018
	 */
	public function __construct() {
		add_action( 'wp_enqueue_scripts', array( $this, 'yc_style' ) );
		add_action( 'wp_ajax_nopriv_yc_my_community', array( $this, 'yc_my_community' ) );
		add_action( 'wp_ajax_yc_my_community', array( $this, 'yc_my_community' ) );
		add_action( 'admin_menu', array( $this, 'yc_add_options_page' ) );
		add_action( 'admin_init', array( $this, 'yc_admin_settings' ) );
		add_shortcode( 'yelp', array( $this, 'yc_get_yelp' ) );
	}

	/**
	 * Add options page.
	 *
	 * @since October 19, 2018
	 */
	public function yc_add_options_page() {
		add_options_page( 'Yelp Communities Settings', 'Yelp Communities', 'manage_options', 'yelp-communities', array( $this, 'yc_admin_page' ) );
	}

	/**
	 * Registers settings needed for the plugin.
	 *
	 * @since October 19, 2018
	 */
	public function yc_admin_settings() {
		register_setting( 'yelp-communities-settings', 'yelp_keys' );
		register_setting( 'yelp-communities-settings', 'yelp_keys_api' );
	}

	/**
	 * Enqueue widget styles.
	 *
	 * @since October 19, 2018
	 */
	public function yc_style() {
		$css  = plugins_url( 'includes/css/style.css', __FILE__ );
		$form = plugins_url( 'includes/css/form.css', __FILE__ );
		wp_enqueue_style( 'yelp-communities', $css, array(), '1.0', 'all' );
		wp_enqueue_style( 'yelp-communities-form', $form, array(), '1.0', 'all' );
		wp_enqueue_script( 'jquery', 'https://ajax.googleapis.com/ajax/libs/jquery/3.1.1/jquery.min.js', array(), null, true );
		wp_localize_script( 'ajax-script', 'ajax_object', array( 'ajax_url' => admin_url( 'admin-ajax.php' ) ) );
	}

	/**
	 * Creates the settings form so the api key can be entered
	 *
	 * @since October 19, 2018
	 */
	public function yc_admin_page() {
		?>
			<div class="wrap">
				<form action="options.php" method="post">
					<?php
						settings_fields( 'yelp-communities-settings' );
						do_settings_sections( 'yelp-communities-settings' );
					?>
					<table>
						<tr>
							<th>Yelp API Key</th>
							<td><input type="text" placeholder="Your Yelp API Key" name="yelp_keys_api" value="<?php echo esc_attr( get_option( 'yelp_keys_api' ) ); ?>" size="50"/></td>
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

	/**
	 * Fetches API results from Yelp API
	 *
	 * @param array $atts = Array of specified search terms to submit in the API call.
	 * @return array $html = Returns an array of objects received from the API.
	 *
	 * @since October 19, 2018
	 */
	public function yc_get_yelp( $atts ) {
		if ( isset( $_GET['search'] ) ) {
			$term   = $_GET['search'];
			$term   = strtolower( $term );
			$key    = get_option( 'yelp_keys_api' );
			$method = 'GET';
			if ( isset( $atts['location'] ) ) {		
				$location = $atts['location'];
			} else {
				$location = 'Naples, FL';
			}
			if ( isset( $atts['radius'] ) ) {
				$radius = $atts['radius'];
				$radius = $radius * 1609;
			} else {
				$radius = 8046;
			}
			if ( isset( $atts['limit'] ) ) {
				$limit = $atts['limit'];
			} else {
				$limit = 20;
			}
			if ( 'schools' === $term ) {
				$html  = '';
				$html .= getSchools( $term, $location, $radius, 1 ); 
				return $html;
			} elseif ( 'listings' === $term ) {
				$html = '';
				$html .= '<style>.yelp-listings { display: unset !important; }</style>';
				return $html;
			}
		} else {
			if ( isset( $atts['term'] ) ) {
			$term   = $atts['term'];
			$key    = get_option( 'yelp_keys_api' );
			$method = 'GET';
			if ( isset( $atts['location'] ) ) {
				$location = $atts['location'];
			} else {
				$location = 'Eugene, OR';
			}
			if ( isset( $atts['radius'] ) ) {
				$radius = $atts['radius'];
				$radius = $radius * 1609;
			} else {
				$radius = 8046;
			}
			if ( isset( $atts['limit'] ) ) {
				$limit = $atts['limit'];
			} else {
				$limit = 20;
			}
			}
		}
			$url          = 'https://api.yelp.com/v3/businesses/search';
			$args         = array(
				'term'     => $term,
				'location' => $location,
				'radius'   => $radius,
				'limit'    => $limit,
			);
			$api_url      = add_query_arg( $args, $url );
			$access_token = $key;
			$api_endpoint = $api_url;
			$args         = array(
				'user-agent' => '',
				'headers'    => array(
					'Authorization' => 'Bearer ' . $access_token,
				),
			);
			$response     = wp_remote_get( $api_endpoint, $args );
			$html         = '';
			$html        .= $this->yc_results( json_decode( $response['body'], true ) );
			return $html;
	}

	/**
	 * Generates the showcase widget.
	 *
	 * @param string $data = the returned API data.
	 * @return string $html = the generated HTML markup for the showcase.
	 * @since October 19, 2018
	 */
	public function yc_results( $data ) {
		$dir   = plugin_dir_url( __FILE__ );
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
				a.yelp-branding img {
					border: none;
					max-height: 40px;
					box-shadow: none;
					border: none;
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
		foreach ( $data['businesses'] as $location ) {
				$url           = $location['url'];
				$img           = $location['image_url'];
				$biz_name      = $location['name'];
				$street        = $location['location']['address1'];
				$city          = $location['location']['city'];
				$state         = $location['location']['state'];
				$zip           = $location['location']['zip_code'];
				$phone         = $location['phone'];
				$phone_display = $location['display_phone'];
				$num_reviews   = $location['review_count'];
				$rating        = $location['rating'];
				switch ( $rating ) {
					case 0:
						$stars = plugin_dir_url( 'yelp-communities/includes/images/' ) . 'images/regular_0.png';
						break;
					case 1:
						$stars = plugin_dir_url( 'yelp-communities/includes/images/' ) . 'images/regular_1.png';
						break;
					case 1.5:
						$stars = plugin_dir_url( 'yelp-communities/includes/images/' ) . 'images/regular_1_half.png';
						break;
					case 2:
						$stars = plugin_dir_url( 'yelp-communities/includes/images/' ) . 'images/regular_2.png';
						break;
					case 2.5:
						$stars = plugin_dir_url( 'yelp-communities/includes/images/' ) . 'images/regular_2_half.png';
						break;
					case 3:
						$stars = plugin_dir_url( 'yelp-communities/includes/images/' ) . 'images/regular_3.png';
						break;
					case 3.5:
						$stars = plugin_dir_url( 'yelp-communities/includes/images/' ) . 'images/regular_3_half.png';
						break;
					case 4:
						$stars = plugin_dir_url( 'yelp-communities/includes/images/' ) . 'images/regular_4.png';
						break;
					case 4.5:
						$stars = plugin_dir_url( 'yelp-communities/includes/images/' ) . 'images/regular_4_half.png';
						break;
					case 5:
						$stars = plugin_dir_url( 'yelp-communities/includes/images/' ) . 'images/regular_5.png';
						break;
				}
				$html .= '<div class="yelp-item" style="background-image:url(\''.$img.'\');">';
				$html .= '<div class="yelp-overlay">';
				$html .= '<div class="more-info"><a target="_blank" href="'.$url.'"><span>More Info</span></a></div>';
				$html .= '<h3 class="yelp-title">';
				$html .= '<a target="_blank" href="'.$url.'" class="yelp-link">'.$biz_name.'</a>';
				$html .= '</h3>';
				$html .= '<p>';
				$html .= '<img class="rating" src="'.$stars.'" alt="'.$biz_name.' Yelp Rating" title="'.$biz_name.' Yelp Rating" />';
				$html .= '<span class="review-count">'.$num_reviews.' reviews</span>';
				$html .= '<a class="yelp-branding" href="'.$url.'" target="_blank"><img src="'.plugins_url( 'yelp-communities/includes/images/yelp.png',$css,array(),'1.0','all').'" alt="Powered by Yelp"></a>';
				$html .= '</p>';
				$html .= '<div class="yelp-address-wrap">';
				$html .= '<address>';
				$html .= $street. '<br>'.$city.', '.$state.' '.$zip;
				$html .= '</address>';
				$html .= '</div>';
				$html .= '<div class="yelp-phone">';
				$html .= '<a href="tel:'.$phone.'">'.$phone_display.'</a>';
				$html .= '</div>';
				$html .= '</div>';
				$html .= '</div>';
		}
		$html .= '</div>';
		return $html;
	}

	/**
	 * Needs nonce verification and output needs to be sanitized.
	 *
	 * @since October 19, 2018
	 */
	public function yc_my_community() {
		$term     = $_POST['search'];
		$term     = strtolower($term);
		$location = $_POST['location'];
		$location = strtolower($location);
		$radius   = $_POST['radius'];
		$limit    = $_POST['limit'];
		$show     = do_shortcode( '[yelp term="' . $term . '" location="' . $location . '" radius="' . $radius . '" limit="' . $limit . '"]' );
		echo $show;
	}
}
new YelpCommunities();
