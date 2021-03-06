<?php
/* 
Plugin Name: Simple Badges
Plugin URI: http://wordpress.org/extend/plugins/simple-badges
Description: Create and award badges to your users manually, or based on what they accomplish.
Version: 0.1-alpha20120703
Author: Ryan Imel
Author URI: http://wpcandy.com
License: GPL
License URI: http://www.gnu.org/licenses/gpl-2.0.html
Text Domain: simple-badges
*/

class SimpleBadges {

	/* 
	 * Static property to hold our singleton instance
	 * @var SimpleBadges
	 */
	 
	static $instance = false;
	 
	 
	/*
	 * This is our constructor, which is private to force the use of
	 * getInstance() to make this a singleton
	 * 
	 * @return SimpleBadges
	*/
	
	private function __construct() {
		
		// Translations
		load_plugin_textdomain( 'simple-badges', false, basename( dirname( __FILE__ ) ) . '/languages' );
		
		// Actions and filters
		add_action( 'init', array( $this, 'post_types' ) );
		// to make sure the thumbnail option displays for our badge post type
		// via http://codex.wordpress.org/Function_Reference/add_theme_support
		add_theme_support( 'post-thumbnails', array( 'simplebadges_badge' ) );		
		add_action( 'admin_menu', array( $this, 'metabox_add' ) );
		add_action( 'save_post', array( $this, 'metabox_save' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'scripts' ) );
		add_filter( 'the_content', array( $this, 'badge_post_display' ) );
	}
	
	
	/*
	 * If an instance exists, this returns it. If not, it creates one and 
	 * returns it.
	 *
	 * @return SimpleBadges
	 */
	 
	 public static function getInstance() {
	 	if ( !self::$instance )
	 		self::$instance = new self;
	 	return self::$instance;
	 }
	 
	
	/*
	 * Javascript for this plugin.
	 *
	 */
	 
	public function scripts() {
		global $typenow, $pagenow;
		if( ($pagenow == 'post-new.php' || $pagenow == 'post.php') && $typenow == 'simplebadges_badge' )
		
		wp_enqueue_script( 'simplebadges-admin-scripts', plugins_url( '/js/simplebadges-admin.js', __FILE__ ) , array( 'jquery' ), 0.2, true );
	
	}
	 
	 
	 /*
	  * Spin up a new custom post type.
	  *
	  */
	 
	public function post_types() {
	 	
		// Badges post type
		register_post_type( 'simplebadges_badge',
			array(
	 			
				// TODO: Translations! Check http://plugins.svn.wordpress.org/wp-help/tags/0.3/wp-help.php for example.
				'labels' => array(
				
					'name' => __( 'Badges', 'simple-badges' ),
					'singular_name' => __( 'Badge', 'simple-badges' ),
					'add_new' => __( 'Add New Badge', 'simple-badges' ),
					'all_items' => __( 'Badges', 'simple-badges' ),
					'add_new_item' => __( 'Add New Badge', 'simple-badges' ),
					'edit_item' => __( 'Edit Badge', 'simple-badges' ),
					'new_item' => __( 'New Badge', 'simple-badges' ),
					'view_item' => __( 'View Badge', 'simple-badges' ),
					'search_items' => __( 'Search Badges', 'simple-badges' ),
					'not_found' => __( 'Badges not found.', 'simple-badges' ),
					'not_found_in_trash' => __( 'Badge Not Found', 'simple-badges' ),
					'parent_item_colon' => __( 'Parent Badge', 'simple-badges' ),
					'menu_name' => __( 'Badges', 'simple-badges' )
				
				),
				
				'public' => true,
				'exclude_from_search' => true,	 			
				'publicly_queryable' => true,
				'show_ui' => true,
				'show_in_nav_menus' => true,
				'show_in_menu' => 'tools.php',
				
				// Note: When using 'some string' to show as a submenu of a menu page 
				// created by a plugin, this item will become the first submenu item, 
				// and replace the location of the top level link. If this isn't desired, 
				// the plugin that creates the menu page needs to set the add_action priority 
				// for admin_menu to 9 or lower. 
				// - http://codex.wordpress.org/Function_Reference/register_post_type

				'show_in_admin_bar' => false,
				'menu_position' => 80,
				'capabilities' => array(
				// Cribbed from http://plugins.svn.wordpress.org/wp-help/tags/0.3/wp-help.php
					'publish_posts' => 'manage_options',
					'edit_posts' => 'manage_options',
					'edit_others_posts' => 'manage_options',
					'delete_posts' => 'manage_options',
					'read_private_posts' => 'manage_options',
					'edit_post' => 'manage_options',
					'delete_post' => 'manage_options',
					'read_post' => 'read'
				),
				'hierarchical' => true,
				// Thinking: child badges could assume the requirements of the parent badge.
				'supports' => array( 'title', 'editor', 'thumbnail', 'page-attributes', 'comments' ),
				// Use the CMB plugin to set these up? Would that even work in this situation.
				'has_archive' => true,
				'rewrite' => array( 
					'slug' => 'badges',
					'with_front' => false,
					'feeds' => false
				),
				'can_export' => true
	 		
	 		)
	 	);
	 	
	 	// flush_rewrite_rules();
	 	// Consider doing this if public rewrites are needed. Flush only on activation, though.
	 	// See http://codex.wordpress.org/Function_Reference/register_post_type
	
	}
	
	
	
	/*
	 * Add meta box 
	 * 
	 */
	
	public function metabox_add() {
			
		add_meta_box( $this->metabox_fields['id'], $this->metabox_fields['title'], array( $this, 'metabox_display' ), $this->metabox_fields['page'], $this->metabox_fields['context'], $this->metabox_fields['priority'] );
		
		$metabox_fields = array(

			'id' => 'simplebadges-meta-box',
			'title' => __( 'Simple Badges', 'simple-badges' ),
			'page' => 'simplebadges_badge',
			'context' => 'normal',
			'priority' => 'high',
			'fields' => array(
				array(
					'name' => 'Badge Type',
					'desc' => '',
					'id' => 'simplebadges_badge_type',
					'type' => 'radio',
					'options' => array( 
						array(
							'value' => __( 'Award this page manually.', 'simple-badges' ),
							'id' => 'simplebadges_badge_type_manual',
							'name' => 'group1'
						),
						array(
							'value' => __( 'Award this badge automatically.', 'simple-badges' ),
							'id' => 'simplebadges_badge_type_auto',
							'name' => 'group1'
						)
					),
					'std' => __( 'Award this page manually.', 'simple-badges' )
				),
				array(
					'name' => __( 'Hidden Badges', 'simple-badges' ),
					'desc' => __( 'Hide this badge from users.', 'simple-badges' ),
					'id' => 'simplebadges_badge_hidetoggle',
					'type' => 'checkbox',
					'std' => 'off'
				),
				array(
					'name' => __( 'Award this badge if&hellip;', 'simple-badges' ),
					'desc' => '',
					'id' => 'simplebadges_badge_conditional_partone',
					'type' => 'select',
					'options' => array( __( 'User post count', 'simple-badges' ), __( 'User comment count', 'simple-badges' ), __( 'User registration date', 'simple-badges' ), __( 'User ID', 'simple-badges' ) ),
					'std' => ''
				),
				array(
					'name' => __( 'Conditional part two&hellip;', 'simple-badges' ),
					'desc' => '',
					'id' => 'simplebadges_badge_conditional_parttwo',
					'type' => 'select',
					'options' => array( __( 'is equal to', 'simple-badges' ), __( 'is less than', 'simple-badges' ), __( 'is greater than', 'simple-badges' ) ),
					'std' => ''
				),
				array(
					'name' => __( 'Conditional part three&hellip;', 'simple-badges' ),
					'desc' => '',
					'id' => 'simplebadges_badge_conditional_partthree',
					'type' => 'text'
				)
			)

		);
	
	}
	
	
	/*
	 * Display meta box
	 *
	 */  
	
	public function metabox_display() {
	
		global $post;
		
		echo '<input type="hidden" name="simplefields_metabox_nonce" value="', wp_create_nonce( basename(__FILE__)), '" />';
		echo '<table class="form-table">';
		
		foreach ( $this->metabox_fields['fields'] as $field ) {
		
			$meta = get_post_meta( $post->ID, $field['id'], true );
			
			echo '<tr>',
				'<th style="width:20%">',
					'<label for="', $field['id'], '">', esc_attr( $field['name'] ), '</label>',
				'</th>',
				'<td>';
				
			// Depending on what inputs are dropped into our box
			switch ( $field['type'] ) {
			
				case 'text' : 
					echo '<input type="text" name"', $field['id'], '" id="', $field['id'], '" value="';
					echo esc_attr( $meta );
					echo '" size="30" style="width: 12%" />', '<br />', $field['desc'];
					break;
					
				case 'textarea' :
					echo '<textarea name="', $field['id'], '" id="', $field['id'], '" cols="60" rows="4" style="width: 97%">', $meta ? $meta : $field['std'], '</textarea>', '<br />', $field['desc'];
					break;
					
				case 'select' : 
					echo '<select name="', $field['id'], '" id="', $field['id'], '">';
					
					foreach ( $field['options'] as $option ) {
						echo '<option', $meta == $option ? ' selected="selected"' : '', '>', $option, '</option>';
					}
					echo '</select>';
					break;
					
				case 'radio' :
					foreach ( $field['options'] as $option ) {
					
					$checkedvalue = '';
					if ( ( !($meta) && $field['std'] == $option['value'] ) || $meta == $option['value'] ) {
						$checkedvalue .= ' checked="checked"';
					}
					
						echo '<input type="radio" name="', $field['id'], '" id="', $option['value'], '" value="', $option['value'], '"', $checkedvalue, ' />&nbsp;<label for="', $option['value'], '">', $option['value'], '</label><br />';
					}
					break;
					
				case 'checkbox' :
					echo '<input type="checkbox" name="', $field['id'], '" id="', $field['id'], '"', $meta ? ' checked="checked"' : '', ' />';
					echo '<label for="', $field['id'], '" id="', $field['id'], '-label">&nbsp;', $field['desc'], '</label>';
					break;
			
			}
			
			echo '</td></tr>';

		}
		
		echo '</table>';
	
	}
	
	
	/*
	 * Save the metabox
	 *
	 */
	 
	public function metabox_save( $post_id ) {
		
		if ( !wp_verify_nonce( $_POST[ 'simplefields_metabox_nonce' ], basename(__FILE__) ) ) {

			return $post_id;

		}
		
		if ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE ) {

			return $post_id;

		}
		
		if ( 'page' == $_POST['post_type'] ) {

			if( !current_user_can( 'edit_post', $post_id ) ) {
				return $post_id;
			}

		} elseif ( !current_user_can( 'edit_post', $post_id ) ) {

			return $post_id;
		}
		
		foreach ( $this->metabox_fields['fields'] as $field ) {
			
			$old = get_post_meta( $post_id, $field['id'], true );
			$new = $_POST[$field['id']];
			
			if ( $new && $new != $old ) {
			
				update_post_meta( $post_id, $field['id'], $new );
			
			} elseif ( '' == $new && $old ) {
			
				delete_post_meta( $post_id, $field['id'], $old );
			
			}
			
		}
		
	}
	
	
	/*
	 * Display badges on the author archive page
	 * Accepts return true/false to enable returning the output instead of echoing it.
	 */
	
	public function author_archive_display( $return = false ) {
		
		// This thing won't have anything to do if it's used outside of an author page.
		if ( !( is_author() ) )
			return;
		
		$this->badge_users_update();
		
		$output = '<h3>' . __( 'Badges:', 'simple-badges' ) . '</h3><ul>';
		
		$args = array(
			'post_type' => 'simplebadges_badge',
		);
		
		// The query itself
		$sb_user_query = new WP_Query( $args );
		
		// The loop
		while ( $sb_user_query->have_posts() ) : $sb_user_query->the_post();
			
			$badge_id = $sb_user_query->post->ID;
			$badge_title = get_the_title();
			
			$badge_image = get_the_post_thumbnail( $badge_id, array(50,50) );
			$badge_image_small = get_the_post_thumbnail( $badge_id, array(30,30) );
			$badge_meta = get_post_meta( $badge_id, 'simplebadges_badge_hidetoggle', true );
						
			// Make sure all badges shown should be.
			//if( $badge_meta != 'on' ) {
			
				// Does the author have this particular badge? Pull the query variable from the page
				// and check that against the array of badge user IDs.
				$archive_author = ( get_query_var( 'author_name' ) ) ? get_user_by( 'slug', get_query_var( 'author_name') ) : get_userdata( get_query_var( 'author') );
				$archive_author_ID = $archive_author->ID;
				
				$badge_users = get_post_meta( $badge_id, 'simplebadges_badge_users_frl', false );
				
				// What to link up if the currently logged in user is an admin
				if ( current_user_can( 'manage_options' ) ) {
					
					$badge_link_url = parse_url( $_SERVER[ 'REQUEST_URI' ],PHP_URL_PATH ) . '?badge=' . $badge_id . '&badgeuser=' . $archive_author_ID;
					$badge_link_url_verified = wp_nonce_url( $badge_link_url, 'simplebadges_nonce_url' );
					$badge_link = '<a href="' . $badge_link_url_verified . '">';
					
				// If not then they should always link to the badge pages themselves.
				} else {
					$badge_link = '<a href="' . get_permalink( $badge_id ) . '"';
				}
				
				if ( in_array( $archive_author_ID, $badge_users ) ) {
					
					$badge_is_owned .= '<li style="list-style-type: none;display: inline-block;margin: 15px 5px 5px 5px;">' . $badge_link  . $badge_image . '</a></li>'; 
				
				} else {
				
					$badge_not_owned .= '<li style="list-style-type: none;display: inline-block;margin: 5px 2px 15px 2px;opacity:0.25;">' . $badge_link  . $badge_image_small . '</a></li>';
				
				}
				
			//}			
			
		
		endwhile;
		
		wp_reset_postdata();
		
		$output .= $badge_is_owned . '<br />' . $badge_not_owned; 
		$output .= '</ul>';
						
		// Out it goes, into the world.
		if ( $return ) {
			
			return $output;
		
		} else {
			
			echo $output;
						
		}	
		
	}
	
	
	/*
	 * Update users per badge
	 * This runs when an admin is adding or removing someone to a manual badge.
	 *
	 */
	
	public function badge_users_update() {
		
		// If true, then we can toggle based on the user and the badge info.
		if ( current_user_can( 'manage_options' ) && isset( $_GET[badgeuser] ) && isset( $_GET[badge] ) && check_admin_referer( 'simplebadges_nonce_url' ) ) {
				
			// Set some proper variables so we can get to work
			$badge_toggle_user_id = $_GET[badgeuser];
			$badge_toggle_badge_id = $_GET[badge];
			
			// Grab this badge's list of user IDs
			$badge_toggle_users = get_post_meta( $badge_toggle_badge_id, 'simplebadges_badge_users_frl', false );
				
			// If the user in question is in that list we just found							
			if ( in_array( $badge_toggle_user_id, $badge_toggle_users ) ) {
					
				// Toggle and remove the author
				delete_post_meta( $badge_toggle_badge_id, 'simplebadges_badge_users_frl', $badge_toggle_user_id );
			
			// If they aren't in the list, let's add them.		
			} else {
					
				// Toggle and add the author
				add_post_meta( $badge_toggle_badge_id, 'simplebadges_badge_users_frl', $badge_toggle_user_id );	
				
			}
				
		}
	
	}
	
	
	/*
	 * Filter the display of badges
	 * $badge_image $content $badge_winners
	 */
	
	public function badge_post_display( $content ) {
		
		if ( !( is_post_type_archive( 'simplebadges_badge' ) || ( is_single() && ( 'simplebadges_badge' == get_post_type() ) )  ) )
			return $content;
			
		$badge_id = get_the_ID();
		
		$badge_image = '<div style="float:right;">' . get_the_post_thumbnail( $badge_id, array(50,50) ) . '</div>';
		
		$badge_users = get_post_meta( $badge_id, 'simplebadges_badge_users_frl', false );
		
		foreach ( $badge_users as $badge_user ) {
			
			$badge_winners .= '<a href="' . get_author_posts_url( $badge_user ) . '">' . get_avatar( $badge_user, 30 ) . '</a>';
			
		}
		
		return $badge_image . $content . $badge_winners;
		
	}
	
	

// end class
}

// Instantiate our class
$SimpleBadges = SimpleBadges::getInstance();


// Simple Badges display function
// Just a wrapper for what's in the class.
function simplebadges_user() {
	global $SimpleBadges;
	
	if ( $SimpleBadges ) {
		return $SimpleBadges->author_archive_display();
	}
}
