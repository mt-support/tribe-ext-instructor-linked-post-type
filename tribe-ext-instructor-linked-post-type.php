<?php
/**
 * Plugin Name:       The Events Calendar Extension: Instructor Linked Post Type
 * GitHub Plugin URI: https://github.com/mt-support/tribe-ext-instructor-linked-post-type
 * Description:       A boilerplate/starter extension for you to use as-is or fork. Used as-is, an "Instructor" custom post type will be created and linked to The Events Calendar's Events, like Organizers are, and basic output will be added to the Single Event Page. See this plugin file's code comments for forking instructions.
 * Version:           1.0.1
 * Extension Class:   Tribe__Extension__Instructor_Linked_Post_Type
 * Author:            Modern Tribe, Inc.
 * Author URI:        http://m.tri.be/1971
 * License:           GPL version 3 or any later version
 * License URI:       https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain:       tribe-ext-instructor-linked-post-type
 */

/**
 * TODO: How to Fork this...
 * !!! Remove this plugin file's "GitHub Plugin URI" header item or else all your changes will be wiped out upon auto-update!!! We have this on auto-update for users who use the extension as-is instead of forking it.
 * Find and replace (case-sensitive) all mentions of "instructor" and "instructors" (both lowercase and uppercase), including the following:
 *** The name of this plugin directory (but do not remove the leading "tribe-ext-" part!)
 *** The name of this directory's sub-folder: src/views/RENAME_THIS/single.php -- and the content of this single.php
 *** This file's class name
 *** The src/Filterbar-Filter.php file's class name and its references to this class' name.
 * Then, replace with your own post type and register_post_type() arguments as appropriate for your project.
 * And add your own custom fields -- see $this->get_custom_field_labels()
 * Check all other "TODO" notes throughout this file
 * Possibly change this plugin's header to offer a more helpful description within the Plugins List wp-admin screen.
 * Test everything is working as desired, including saving of meta data and customizing how it outputs to the Single Events Page and your single instructor page.
 */

if ( ! function_exists( 'tribe_ext_is_event_instructor' ) ) {
	/**
	 * Conditional tag to check if the current page is a single instructor page.
	 *
	 * @return bool
	 **/
	function tribe_ext_is_event_instructor() {
		global $wp_query;

		$tribe_ext_is_event_instructor = ! empty( $wp_query->tribe_ext_is_event_instructor );

		return apply_filters( 'tribe_ext_query_is_event_instructor', $tribe_ext_is_event_instructor );
	}
}

/**
 * Only load this class if it does not already exist to prevent accidental
 * fatals if this extension is installed and activated more than once but the
 * class names are not yet changed. If your second extension is not doing
 * anything, double-check that the class name was changed everywhere (search
 * and replace).
 */
if (
	class_exists( 'Tribe__Extension' )
	&& ! class_exists( 'Tribe__Extension__Instructor_Linked_Post_Type' )
) {
	/**
	 * Extension main class, class begins loading on init() function.
	 */
	class Tribe__Extension__Instructor_Linked_Post_Type extends Tribe__Extension {

		/**
		 * Our post type's key.
		 *
		 * Must not exceed 20 characters and may only contain lowercase alphanumeric
		 * characters, dashes, and underscores.
		 *
		 * @see sanitize_key()
		 *
		 * @return string
		 */
		const POST_TYPE_KEY = 'tribe_ext_instructor';

		/**
		 * Our post type's rewrite slug and singular_name_lowercase.
		 *
		 * Must be lowercase and no spaces.
		 *
		 * @return string
		 */
		const POST_TYPE_SLUG = 'instructor';

		/**
		 * Is Filterbar active. If yes, we'll add some extra functionality.
		 *
		 * @return bool
		 */
		public $filterbar_active = false;

		/**
		 * Setup the Extension's properties.
		 *
		 * This always executes even if the required plugins are not present.
		 */
		public function construct() {
			// Linked Post Types started in version 4.2
			// Tribe__Duplicate__Strategy_Factory class exists since version 4.6
			$this->set_url( 'https://theeventscalendar.com/knowledgebase/linked-post-types/' );
			$this->add_required_plugin( 'Tribe__Events__Main', '4.6' );
			add_action( 'tribe_plugins_loaded', array( $this, 'detect_filterbar' ), 0 );

			/**
			 * Ideally, we would only flush rewrite rules on plugin activation and
			 * deactivation, but we cannot on activation due to the way extensions
			 * get loaded. Therefore, we flush rewrite rules a different way while
			 * plugin is activated. The deactivation hook does work inside the
			 * extension class, though.
			 *
			 * @link https://developer.wordpress.org/reference/functions/flush_rewrite_rules/#comment-597
			 */
			add_action( 'admin_init', array( $this, 'admin_flush_rewrite_rules_if_needed' ) );
			register_deactivation_hook( __FILE__, 'flush_rewrite_rules' );
		}

		/**
		 * Extension initialization and hooks.
		 */
		public function init() {
			// Load plugin textdomain
			load_plugin_textdomain( 'tribe-ext-instructor-linked-post-type', false, basename( dirname( __FILE__ ) ) . '/languages/' );

			add_action( 'init', array( $this, 'register_our_post_type' ) );
			add_action( 'init', array( $this, 'link_post_type_to_events' ) );
			add_action( 'wp_loaded', array( $this, 'set_our_capabilities' ) );
			add_filter( 'tribe_events_linked_post_type_args', array( $this, 'filter_linked_post_type_args' ), 10, 2 );
			add_filter( 'tribe_events_linked_post_id_field_index', array( $this, 'linked_post_id_field_index' ), 10, 2 );
			add_filter( 'tribe_events_linked_post_name_field_index', array( $this, 'get_post_type_name_field_index' ), 10, 2 );
			add_filter( 'tribe_events_linked_post_type_container', array( $this, 'linked_post_type_container' ), 10, 2 );
			add_action( 'tribe_events_linked_post_new_form', array( $this, 'event_edit_form_create_fields' ) );
			add_action( 'tribe_events_linked_post_create_' . self::POST_TYPE_KEY, array( $this, 'event_edit_form_save_data' ), 10, 5 );
			add_filter( 'tribe_events_linked_post_meta_values_' . $this->get_linked_post_type_custom_field_key(), array( $this, 'filter_out_invalid_post_ids' ) );
			add_action( 'admin_menu', array( $this, 'add_meta_box_to_event_editing' ) );
			add_action( 'save_post_' . self::POST_TYPE_KEY, array( $this, 'save_data_from_meta_box' ), 16, 2 );

			add_action( 'wp_head', array( $this, 'single_events_custom_css' ) );

			add_filter( 'parse_query', array( $this, 'set_post_type_in_parse_query' ) );
			add_filter( 'tribe_query_is_event_query', array( $this, 'set_event_query_for_this_single_post' ) );
			add_filter( 'tribe_events_template_paths', array( $this, 'template_paths' ) );
			add_filter( 'tribe_events_current_view_template', array( $this, 'set_current_view_template' ) );

			add_filter( 'tribe_events_linked_post_type_meta_key', array( $this, 'filter_linked_post_type_meta_key' ), 10, 2 );

			// Single Instructor page: Handling the No Events Found situation.
			// We followed how the Tribe__Events__Template_Factory class does it.
			// cleanup after view (reset query, etc)
			add_action( 'tribe_events_after_view', array( $this, 'shutdown_view' ) );
			// set notices
			add_action( 'tribe_events_before_view', array( $this, 'set_notices' ), 15 );

			/**
			 * TODO: Leave this as-is or choose a different action hook.
			 * If you change it to use the
			 * `tribe_events_single_event_after_the_meta`
			 * hook, it will be its own separate meta box below the main one or
			 * the venue one (depending on if there's a map displayed in its
			 * own meta box). If you do change to its own meta box, you will
			 * want to add the `tribe-events-event-meta` class to the output's
			 * outermost/container div.
			 */
			add_action( 'tribe_events_single_event_meta_primary_section_end', array( $this, 'output_linked_posts' ) );

			// Support Filter Bar if it is active.
			if (
				$this->filterbar_active
				&& file_exists( dirname( __FILE__ ) . '/src/Filterbar-Filter.php' )
			) {
				include_once( dirname( __FILE__ ) . '/src/Filterbar-Filter.php' );
				add_action( 'tribe_events_filters_create_filters', array( $this, 'add_filter_to_filterbar' ) );
			}
		}

		/**
		 * Check required plugins after all Tribe plugins have loaded.
		 */
		public function detect_filterbar() {
			if ( Tribe__Dependency::instance()->is_plugin_active( 'Tribe__Events__Filterbar__View' ) ) {
				$this->add_required_plugin( 'Tribe__Events__Filterbar__View', '4.3.1' );
				$this->filterbar_active = true;
			}
		}

		/**
		 * Check required plugins after all Tribe plugins have loaded.
		 */
		public function add_filter_to_filterbar() {
			if ( class_exists( 'Tribe__Events__Filterbar__Filters__Instructor' ) ) {
				new Tribe__Events__Filterbar__Filters__Instructor( $this->get_post_type_label(), self::POST_TYPE_SLUG );
			}
		}

		/**
		 * Ideally, we would only flush rewrite rules on plugin activation, but we
		 * cannot use register_activation_hook() due to the timing of when
		 * extensions load. Therefore, we flush rewrite rules on every visit to the
		 * wp-admin Plugins screen (where we'd expect you to be if you just
		 * activated a plugin)... only if our custom post type key is not one of the
		 * rewrite rules.
		 *
		 * This sort of checking won't catch situations where you modify the args
		 * sent to register_post_type() in a way that would require a refresh to
		 * the rewrite rules... after we already added the rewrite rules.
		 * In this case, just visit your wp-admin Permalinks settings and you
		 * should be good-to-go.
		 */
		public function admin_flush_rewrite_rules_if_needed() {
			global $pagenow;

			if ( 'plugins.php' !== $pagenow ) {
				return;
			}

			$rewrite_rules = get_option( 'rewrite_rules' );

			if ( empty( $rewrite_rules ) ) {
				return;
			}

			$need_refresh = true;

			// search all the rewrite rules for our custom post type key and if one is found, bail out without refreshing Permalinks
			foreach ( $rewrite_rules as $rule ) {
				if ( false !== strpos( $rule, self::POST_TYPE_KEY ) ) {
					$need_refresh = false;
					break;
				}
			}

			if ( $need_refresh ) {
				flush_rewrite_rules();
			}
		}

		/**
		 * Removes anything other than positive integers from Post IDs.
		 *
		 * Also converts Post ID-looking strings to integers.
		 *
		 * @see Tribe__Events__Linked_Posts::META_KEY_PREFIX
		 * @see Tribe__Events__Linked_Posts::get_meta_key()
		 *
		 * @param array $current_linked_posts The array of our post type's Post IDs
		 *                                    currently linked to a single event.
		 *
		 * @return array
		 */
		public function filter_out_invalid_post_ids( $current_linked_posts ) {
			return array_map( 'absint', (array) $current_linked_posts );
		}

		/**
		 * Check to see if any of this post type's custom fields are set.
		 *
		 * @param array $data The post data.
		 *
		 * @return bool If there is ANY organizer data set, return true.
		 */
		public function has_this_post_types_custom_fields( $data ) {
			foreach ( $this->get_custom_field_keys() as $key ) {
				if ( isset( $data[$key] ) && $data[$key] ) {
					return true;
				}
			}

			return false;
		}

		/**
		 * Our post type's custom field label names.
		 *
		 * Enter "Phone" or "Email Address" -- however you want it to look to the
		 * user, and the actual custom field key will be created from it.
		 *
		 * @see Tribe__Extension__Instructor_Linked_Post_Type::sanitize_a_custom_fields_value()
		 *
		 * @return array
		 */
		protected function get_custom_field_labels() {
			$field_labels = array(
				'Phone',
				'Website',
				'Email Address',
			);

			$field_labels = array_map( 'esc_html', $field_labels );

			return $field_labels;
		}

		/**
		 * Validation for each custom field.
		 *
		 * By default, all custom fields are ran through sanitize_meta().
		 *
		 * @link https://developer.wordpress.org/themes/theme-security/data-sanitization-escaping/
		 *
		 * @return string
		 */
		protected function sanitize_a_custom_fields_value( $meta_key = '', $meta_value = '' ) {
			if (
				empty( $meta_key )
				|| empty( $meta_value )
			) {
				return '';
			}

			foreach ( $this->get_custom_field_labels() as $custom_field_label ) {
				$custom_field_key = $this->get_a_custom_field_key_from_label( $custom_field_label );
				if ( $custom_field_key == $meta_key ) {
					// Always run all fields through esc_html()
					$meta_value = esc_html( $meta_value );

					// TODO: Add your own logic here for each field label that requires it.
					// Note that no help text regarding this validation is displayed to the user so they may be surprised by the result (e.g. if they had a typo in the email address, forgetting the @ symbol).
					if ( 'Website' == $custom_field_label ) {
						$meta_value = esc_url_raw( $meta_value );
					}
					if ( 'Email Address' == $custom_field_label ) {
						$meta_value = sanitize_email( $meta_value );
					}
				}
			}

			return $meta_value;
		}

		/**
		 * Our post type's custom field label names.
		 *
		 * Enter "Phone" or "Email Address" -- however you want it to look to the
		 * user -- and the actual custom field key will be created from it.
		 *
		 * @return string
		 */
		protected function get_a_custom_field_key_from_label( $label, $prepend_underscore = true ) {
			$label = preg_replace( '/[-\s]/', '_', $label );

			$label = sprintf( '%s_%s', self::POST_TYPE_KEY, $label );

			if ( ! empty( $prepend_underscore ) ) {
				$label = sprintf( '_%s', $label );
			}

			$label = sanitize_key( $label );

			return $label;
		}

		/**
		 * Get the custom field key that attaches our linked post type to events.
		 *
		 * When one of our posts gets linked to an event, the event will have a
		 * custom field with this key, the value of which is the linked
		 * post's Post ID.
		 *
		 * @see Tribe__Events__Linked_Posts::get_meta_key()
		 */
		public function get_linked_post_type_custom_field_key() {
			return Tribe__Events__Linked_Posts::META_KEY_PREFIX . self::POST_TYPE_KEY;
		}

		/**
		 * Our post type's custom field keys, generated from each field's label.
		 *
		 * Converts spaces and hyphens to underscores, prepends with our post type
		 * key, and then prepended with an underscore so it's "hidden" from default
		 * wp-admin Custom Fields editing.
		 *
		 * @see Tribe__Extension__Instructor_Linked_Post_Type::get_custom_field_labels()
		 * @see sanitize_key()
		 *
		 * @return array
		 */
		protected function get_custom_field_keys() {
			$field_keys = array();

			foreach ( $this->get_custom_field_labels() as $label ) {
				$field_keys[] = $this->get_a_custom_field_key_from_label( $label );
			}

			return $field_keys;
		}

		/**
		 * Get our post type's label ('name' by default, which is capitalized and plural).
		 *
		 * @param $label string Any defined label key for our post type.
		 *
		 * @return string
		 */
		protected function get_post_type_label( $label = 'name' ) {
			$post_type_object = get_post_type_object( self::POST_TYPE_KEY );

			$result = $post_type_object->labels->$label;

			return $result;
		}

		/**
		 * Set the arguments for and register the post type.
		 *
		 * @link https://developer.wordpress.org/reference/functions/get_post_type_labels/
		 * @link https://developer.wordpress.org/reference/functions/register_post_type/
		 *
		 * @see  Linked_Posts::register_linked_post_type()
		 */
		public function register_our_post_type() {
			$labels = array(
				'name'                    => esc_html_x( 'Instructors', 'Post type general name', 'tribe-ext-instructor-linked-post-type' ),
				'singular_name'           => esc_html_x( 'Instructor', 'Post type singular name', 'tribe-ext-instructor-linked-post-type' ),
				'singular_name_lowercase' => esc_html_x( self::POST_TYPE_SLUG, 'Post type singular name', 'tribe-ext-instructor-linked-post-type' ),
				// not part of WP's labels but is required by Linked_Posts::register_linked_post_type()
				'add_new'                 => esc_html_x( 'Add New', self::POST_TYPE_KEY, 'tribe-ext-instructor-linked-post-type' ),
				'add_new_item'            => esc_html__( 'Add New Instructor', 'tribe-ext-instructor-linked-post-type' ),
				'edit_item'               => esc_html__( 'Edit Instructor', 'tribe-ext-instructor-linked-post-type' ),
				'new_item'                => esc_html__( 'New Instructor', 'tribe-ext-instructor-linked-post-type' ),
				'view_item'               => esc_html__( 'View Instructor', 'tribe-ext-instructor-linked-post-type' ),
				'view_items'              => esc_html__( 'View Instructors', 'tribe-ext-instructor-linked-post-type' ),
				'search_items'            => esc_html__( 'Search Instructors', 'tribe-ext-instructor-linked-post-type' ),
				'not_found'               => esc_html__( 'No instructors found', 'tribe-ext-instructor-linked-post-type' ),
				'not_found_in_trash'      => esc_html__( 'No instructors found in Trash', 'tribe-ext-instructor-linked-post-type' ),
				'all_items'               => esc_html__( 'Instructors', 'tribe-ext-instructor-linked-post-type' ),
				'archives'                => esc_html__( 'Instructor Archives', 'tribe-ext-instructor-linked-post-type' ),
				'insert_into_item'        => esc_html__( 'Insert into instructor', 'tribe-ext-instructor-linked-post-type' ),
				'uploaded_to_this_item'   => esc_html__( 'Uploaded to this instructor', 'tribe-ext-instructor-linked-post-type' ),
				'items_list'              => esc_html__( 'Instructors list', 'tribe-ext-instructor-linked-post-type' ),
				'items_list_navigation'   => esc_html__( 'Instructors list navigation', 'tribe-ext-instructor-linked-post-type' ),
			);

			$args = array(
				'labels'              => $labels,
				'description'         => esc_html__( 'Instructors linked to Events', 'tribe-ext-instructor-linked-post-type' ),
				'public'              => true,
				'exclude_from_search' => true,
				'show_in_menu'        => 'edit.php?post_type=' . Tribe__Events__Main::POSTTYPE,
				'menu_icon'           => 'dashicons-businessman',
				'capability_type'     => self::POST_TYPE_KEY,
				'map_meta_cap'        => true, // must be true for $this->set_our_capabilities() to take effect
				'supports'            => array(
					'author',
					'editor',
					'excerpt',
					'revisions',
					'thumbnail',
					'title',
				),
				'rewrite'             => array(
					'slug'       => self::POST_TYPE_SLUG,
					'with_front' => false,
				),
			);

			register_post_type( self::POST_TYPE_KEY, $args );
		}

		/**
		 * Set the initial capabilities for our post type on default roles.
		 *
		 * @see Tribe__Events__Capabilities::set_initial_caps()
		 */
		public function set_our_capabilities() {
			$tribe_events_capabilities = new Tribe__Events__Capabilities();

			$roles = array(
				'administrator',
				'editor',
				'author',
				'contributor',
				'subscriber',
			);

			foreach ( $roles as $role ) {
				$tribe_events_capabilities->register_post_type_caps( self::POST_TYPE_KEY, $role );
			}
		}

		/**
		 * Filters the args sent when linking the post type.
		 *
		 * If 'allow_creation' is not set to TRUE, can only "find" posts via the
		 * drop-down chooser, not also create them, when creating a new Event.
		 *
		 * @param array  $args      Array of linked post type arguments
		 * @param string $post_type Linked post type
		 *
		 * @return array
		 */
		public function filter_linked_post_type_args( $args, $post_type ) {
			if ( self::POST_TYPE_KEY !== $post_type ) {
				return $args;
			}

			$args['allow_creation'] = true;

			return $args;
		}

		/**
		 * Build the string used for this linked post type's field name.
		 *
		 * Overrides the default 'id' to be unique to this linked post type, to
		 * allow for the possibility of multiple custom linked post types.
		 *
		 * @see Tribe__Events__Linked_Posts::get_post_type_id_field_index()
		 *
		 * @return string
		 */
		public function get_post_id_field_name() {
			return $this->get_post_type_label( 'singular_name' ) . '_ID'; // Instructor_ID
		}

		/**
		 * Build the string used for this linked post type's ordering meta key.
		 *
		 * Only applicable for The Events Calendar version 4.6.14 or later.
         * Leading underscore to hide the custom field from wp-admin UI display.
		 *
		 * @see Tribe__Events__Linked_Posts::get_linked_posts_by_post_type()
		 * @see sanitize_key()
         *
         * @since 1.0.1
		 *
		 * @return string
		 */
		private function get_order_meta_key() {
			$key = '_' . $this->get_post_id_field_name() . '_Order'; // _Instructor_ID_Order
            return sanitize_key( $key ); // _instructor_id_order
		}

		/**
		 * Filters the linked post id field.
		 *
		 * @param string $id_field  Field name of the field that will hold the ID
		 * @param string $post_type Post type of linked post
		 *
		 * @return string
		 */
		public function linked_post_id_field_index( $id_field, $post_type ) {
			if ( self::POST_TYPE_KEY === $post_type ) {
				return $this->get_post_id_field_name();
			}

			return $id_field;
		}

		/**
		 * Filter the linked post name field.
		 *
		 * @param string $name      Post type name index.
		 * @param string $post_type Post type.
		 *
		 * @return string
		 */
		public function get_post_type_name_field_index( $name, $post_type ) {
			if ( self::POST_TYPE_KEY === $post_type ) {
				return $this->get_post_type_container_name();
			}

			return $name;
		}

		/**
		 * Build the string used for this linked post type's container name.
		 *
		 * @see Tribe__Events__Linked_Posts::get_post_type_container()
		 *
		 * @return string
		 */
		protected function get_post_type_container_name() {
			return esc_attr( $this->get_post_type_label( 'singular_name_lowercase' ) );
		}

		/**
		 * Filters the index that contains the linked post type data during form submission.
		 *
		 * Form field "name"s need to incorporate this.
		 *
		 * @param string $container Container index that holds submitted data
		 * @param string $post_type Post type of linked post
		 *
		 * @return string
		 */
		public function linked_post_type_container( $container, $post_type ) {
			if ( self::POST_TYPE_KEY === $post_type ) {
				return $this->get_post_type_container_name();
			}

			return $container;
		}

		/**
		 * Tell The Events Calendar that this custom post type exists and should
		 * be usable as one of its linked post types.
		 */
		public function link_post_type_to_events() {
			if ( function_exists( 'tribe_register_linked_post_type' ) ) {
				tribe_register_linked_post_type( self::POST_TYPE_KEY );
			}
		}

		/**
		 * Callback for adding the Meta box to the admin page
		 */
		public function add_meta_box_to_event_editing() {
			$meta_box_id = sprintf( 'tribe_events_%s_details', self::POST_TYPE_KEY );

			add_meta_box(
				$meta_box_id,
				sprintf( esc_html__( '%s Information', 'tribe-ext-instructor-linked-post-type' ), $this->get_post_type_label( 'singular_name' ) ),
				array( $this, 'meta_box' ),
				self::POST_TYPE_KEY,
				'normal',
				'high'
			);
		}

		/**
		 * Output the template used when editing this post directly via wp-admin
		 * post editor (not when editing an Event in wp-admin).
		 */
		public function meta_box() {
			global $post;
			$post_id = $post->ID;
			?>

			<style type="text/css">
				#EventInfo-<?php echo self::POST_TYPE_KEY; ?> {
					border: none;
				}
			</style>
			<div id='eventDetails-<?php echo self::POST_TYPE_KEY; ?>' class="inside eventForm">
				<table cellspacing="0" cellpadding="0" id="EventInfo-<?php echo self::POST_TYPE_KEY; ?>">
					<?php
					foreach ( $this->get_custom_field_labels() as $custom_field_label ) {
						echo $this->get_meta_box_tr_html_for_a_field_label( $custom_field_label, $post_id );
					}
					?>
				</table>
			</div>
			<?php
		}

		/**
		 * Given a custom field's label, build its <tr> HTML, which gets used on
		 * event wp-admin edit screens and this post type's wp-admin edit screen.
		 *
		 * @param     $custom_field_label
		 * @param int $post_id
		 *
		 * @return string
		 */
		protected function get_meta_box_tr_html_for_a_field_label( $custom_field_label, $post_id = 0 ) {
			$custom_field_key = $this->get_a_custom_field_key_from_label( $custom_field_label );

			$post_id = absint( $post_id );

			$value = get_post_meta( $post_id, $custom_field_key, true );

			if ( false === $value ) {
				$value = '';
			}

			$name = sprintf(
				'%s[%s]',
				$this->get_post_type_container_name(),
				$custom_field_key
			);

			// We need to put in an array for TEC's data processing but not for custom field meta box on its own post type editing screen
			$screen = get_current_screen();

			if (
				empty( $screen->post_type )
				|| self::POST_TYPE_KEY !== $screen->post_type
			) {
				$name .= '[]';
			}

			$output = sprintf(
				'<tr class="linked-post %1$s tribe-linked-type-%1$s-%2$s">
			<td>
			<label for="%3$s">%4$s</label>
			</td>
			<td>
			<input id="%3$s" type="text"
				name="%5$s"
				class="%1$s-%2$s" size="25" value="%6$s"/>
			</td>
			</tr>',
				self::POST_TYPE_KEY,
				esc_attr( $custom_field_label ),
				$custom_field_key,
				esc_html__( $custom_field_label . ':', 'tribe-ext-instructor-linked-post-type' ),
				$name,
				esc_html( $value )
			);

			return $output;
		}

		/**
		 * Make sure our meta data gets saved.
		 *
		 * @see Tribe__Events__Organizer::update()
		 *
		 * @param int     $post_id The Post ID.
		 * @param WP_Post $post    The post object.
		 */
		public function save_data_from_meta_box( $post_id = null, $post = null ) {
			// was this submitted from the single post type editor?
			$post_type_container_name = $this->get_post_type_container_name();

			if (
				empty( $_POST['post_ID'] )
				|| $_POST['post_ID'] != $post_id
				|| empty( $_POST[$post_type_container_name] )
			) {
				return;
			}

			// is the current user allowed to edit this post?
			if ( ! current_user_can( 'edit_' . self::POST_TYPE_KEY, $post_id ) ) {
				return;
			}

			$data = stripslashes_deep( $_POST[$post_type_container_name] );

			$this->update_existing( $post_id, $data );
		}

		/**
		 * Output the template for creating a new post of our type when on the
		 * Event edit screen (drop-down chooser).
		 *
		 * Based on /wp-content/plugins/the-events-calendar/src/admin-views/create-organizer-fields.php
		 * See $this->get_custom_field_keys() because this form's "name" fields should match those.
		 *
		 * @param string $post_type The post type that is being modified.
		 */
		public function event_edit_form_create_fields( $post_type ) {
			// We only want to affect our own post type; bail if it's some other post type.
			if ( self::POST_TYPE_KEY !== $post_type ) {
				return;
			}

			foreach ( $this->get_custom_field_labels() as $custom_field_label ) {
				echo $this->get_meta_box_tr_html_for_a_field_label( $custom_field_label );
			}
		}

		/**
		 * Saves the instructor information passed via an event.
		 *
		 * @param string $id               Post type ID index.
		 * @param array  $data             Data for submission.
		 * @param string $linked_post_type Post type.
		 * @param string $post_status      Post status.
		 * @param int    $event_id         Post ID of the post the post type is attached to.
		 *
		 * @see Tribe__Events__Linked_Posts::get_post_type_id_field_index()
		 * @see Tribe__Events__Linked_Posts::handle_submission_by_post_type()
		 *
		 * @return mixed
		 */
		public function event_edit_form_save_data( $id, $data, $post_type, $post_status, $event_id ) {
			$our_id = $this->get_post_id_field_name();

			// Check to see if we're updating an already-existing post.
			if (
				isset( $data[$our_id] )
				&& 0 < (int) $data[$our_id]
			) {
				if (
					! empty( $data[$our_id] )
					&& 1 === count( $data )
				) {
					// We're updating an existing post but only an ID was passed, no other data.
					// So just return the ID, i.e. do nothing.
					return $data[$our_id];
				} else {
					// Need to update an existing post.
					// See Tribe__Events__Organizer->update() for inspiration how to make an "update" function.
					return $this->update_existing( $data[$our_id], $data );
				}
			}

			// Otherwise, we're not updating an existing post; we have to make a new post.
			return $this->create_new_post( $data, $post_status );
		}

		/**
		 * Creates a new Instructor
		 *
		 * @param array  $data        The Instructor data.
		 * @param string $post_status the Intended post status.
		 *
		 * @see Tribe__Events__Organizer::create() Inspiration for additional functionality, such as implementing Avoid Duplicates.
		 *
		 * @return mixed
		 */
		public function create_new_post( $data, $post_status = 'publish' ) {
			$name_field_index = $this->get_post_type_container_name();

			if (
				( isset( $data[$name_field_index] )
					&& $data[$name_field_index]
				)
				|| $this->has_this_post_types_custom_fields( $data )
			) {
				$title   = isset( $data[$name_field_index] ) ? $data[$name_field_index] : sprintf( esc_html__( 'Unnamed %s', 'tribe-ext-instructor-linked-post-type' ), $this->get_post_type_label( 'singular_name' ) );
				$content = isset( $data['Description'] ) ? $data['Description'] : '';
				$slug    = sanitize_title( $title );

				$data = new Tribe__Data( $data );

				$our_id = $this->get_post_id_field_name();

				unset( $data[$our_id] );

				$postdata = array(
					'post_title'    => $title,
					'post_content'  => $content,
					'post_name'     => $slug,
					'post_type'     => self::POST_TYPE_KEY,
					'post_status'   => Tribe__Utils__Array::get( $data, 'post_status', $post_status ),
					'post_author'   => $data['post_author'],
					'post_date'     => $data['post_date'],
					'post_date_gmt' => $data['post_date_gmt'],
				);

				$instructor_id = wp_insert_post( array_filter( $postdata ), true );

				if ( ! is_wp_error( $instructor_id ) ) {
					$this->save_meta( $instructor_id, $data );

					return $instructor_id;
				}
			}

			return 0;
		}

		/**
		 * Updates an instructor.
		 *
		 * This method is different from Tribe__Events__Organizer::update(). We
		 * removed things we didn't need for this context, since we only really
		 * pass the custom fields to this and let WordPress core do it's thing
		 * with the title, post_content, etc.
		 *
		 * @see Tribe__Extension__Instructor_Linked_Post_Type::event_edit_form_save_data()
		 * @see Tribe__Extension__Instructor_Linked_Post_Type::save_data_from_meta_box()
		 *
		 * @param int   $id   The instructor ID to update.
		 * @param array $data The instructor data.
		 *
		 * @return int The updated instructor post ID
		 */
		protected function update_existing( $id, $data ) {
			$data = new Tribe__Data( $data, '' );

			// Update existing. Beware of the potential for infinite loops if you hook to 'save_post' (if it aggressively affects all post types) or if you hook to 'save_post_' . self::POST_TYPE_KEY
			if ( 0 < absint( $id ) ) {
				$args = array( 'ID' => $id );
				$tag  = 'save_post_' . self::POST_TYPE_KEY;
				remove_action( $tag, array( $this, 'save_data_from_meta_box' ), 16 );
				wp_update_post( $args );
				add_action( $tag, array( $this, 'save_data_from_meta_box' ), 16, 2 );

				$meta = array_diff_key( $data->to_array(), array_combine( Tribe__Duplicate__Post::$post_table_columns, Tribe__Duplicate__Post::$post_table_columns ) );

				$this->save_meta( $id, $meta );
			}

			return $id;
		}

		/**
		 * Saves our post type's custom fields.
		 *
		 * @param int   $post_id The Post ID.
		 * @param array $data    The post's data.
		 */
		public function save_meta( $post_id, $data ) {
			$our_id = $this->get_post_id_field_name();

			$name_field_index = $this->get_post_type_container_name();

			if ( isset( $data['FeaturedImage'] ) && ! empty( $data['FeaturedImage'] ) ) {
				update_post_meta( $post_id, '_thumbnail_id', $data['FeaturedImage'] );
				unset( $data['FeaturedImage'] );
			}

			// No point in saving ID to itself.
			unset( $data[$our_id] );

			/*
			 * The post name is saved in the post_title.
			 *
			 * @see Tribe__Events__Linked_Posts::get_post_type_name_field_index()
			 */
			unset( $data[$name_field_index] );

			foreach ( $data as $meta_key => $meta_value ) {
				$meta_value = $this->sanitize_a_custom_fields_value( $meta_key, $meta_value );
				update_post_meta( $post_id, $meta_key, sanitize_text_field( $meta_value ) );
			}
		}

		/**
		 * Get a post's HTML output for all of its custom fields.
		 *
		 * Used for the Event Single Page meta display.
		 *
		 * @param int $post_id
		 *
		 * @return string
		 */
		public function get_event_single_custom_fields_output( $post_id = 0 ) {
			$post_id = absint( $post_id );

			$output = '';

			if ( empty( $post_id ) ) {
				return $output;
			}

			foreach ( $this->get_custom_field_labels() as $custom_field_label ) {
				$custom_field_key = $this->get_a_custom_field_key_from_label( $custom_field_label );

				$value = get_post_meta( $post_id, $custom_field_key, true );

				if (
					false === $value
					|| '' === $value
				) {
					continue;
				}

				// Build the HTML markup applicable to each field.
				// By default use esc_html(), but we can't do that for all uses of $value because we already have our desired HTML (with escaped values).
				if ( 'Website' == $custom_field_label ) {
					$value = esc_url( $value );

					if ( empty( $value ) ) {
						continue;
					}

					$value = sprintf( '<a href="%1$s">%1$s</a>', $value );
				} elseif ( 'Email Address' == $custom_field_label ) {
					$value = antispambot( $value );

					if ( empty( $value ) ) {
						continue;
					}

					$email_link = sprintf( 'mailto:%s', $value );

					$value = sprintf( '<a href="%s">%s</a>', esc_url( $email_link, array( 'mailto' ) ), esc_html( $value ) );
				} else {
					$value = esc_html( $value );
				}

				$output .= sprintf(
					'<dt>%s:</dt><dd class="%s-%s">%s</dd>',
					esc_html( $custom_field_label ),
					esc_attr( self::POST_TYPE_KEY ),
					esc_attr( strtolower( $custom_field_label ) ),
					$value
				);
			}

			return $output;
		}

		/**
		 * Build and output all of our linked posts on the single event page.
		 */
		public function output_linked_posts() {
			$output = '';

			$linked_posts = tribe_get_linked_posts_by_post_type( get_the_ID(), self::POST_TYPE_KEY );

			if ( ! empty( $linked_posts ) ) {
				$output .= sprintf(
					'<div class="tribe-linked-type-%s tribe-clearfix">
				<div class="tribe-events-meta-group">
				<h3 class="tribe-events-single-section-title">%s</h3>
				<div class="all-linked-%s">',
					esc_attr( self::POST_TYPE_KEY ),
					$this->get_post_type_label(),
					self::POST_TYPE_KEY
				);

				foreach ( $linked_posts as $post ) {
					$post_id = $post->ID;

					$output .= sprintf(
						'<dl class="single-%1$s post-id-%2$d">
					<dd class="single-%1$s-title">
					<a href="%3$s" title="%4$s">%5$s</a>
					</dd>
					%6$s
					</dl>',
						esc_attr( self::POST_TYPE_KEY ),
						esc_attr( $post_id ),
						esc_url( get_permalink( $post_id ) ),
						esc_attr( get_the_title( $post_id ) ),
						esc_html( get_the_title( $post_id ) ),
						$this->get_event_single_custom_fields_output( $post_id )
					);
				}

				$output .= '</div></div></div>';
			}

			echo $output;
		}

		/**
		 * Load our custom CSS used for our custom meta box on single event pages.
		 */
		public function single_events_custom_css() {
			// Only target Single Event pages
			if (
				! class_exists( 'Tribe__Events__Main' )
				|| ! is_singular( Tribe__Events__Main::POSTTYPE )
			) {
				return;
			}

			$container_selector = sprintf( '.single-tribe_events .tribe-linked-type-%s .tribe-events-meta-group', esc_attr( self::POST_TYPE_KEY ) );

			$parent_selector = sprintf( '.single-tribe_events .all-linked-%s', esc_attr( self::POST_TYPE_KEY ) );
			?>

			<style type="text/css">
				<?php echo $container_selector; ?> {
					width: 100%;
				}
				<?php echo $parent_selector; ?> {
					display: flex;
					flex-wrap: wrap;
					align-content: space-between;
				}
				<?php echo $parent_selector; ?> > dl {
					margin-bottom: 20px;
					min-width: 250px;
					flex: 0 0 20%;
				}
			</style>

			<?php
		}

		/**
		 * Set our post type key in the query. Also hook things that need to
		 * be hooked during this detecting and setting.
		 *
		 * @see tribe_is_event_query()
		 *
		 * @param $query
		 */
		public function set_post_type_in_parse_query( $query ) {
			// Cannot use is_singular() within parse_query (which runs before pre_get_posts) because the queried object is not yet set
			if (
				! is_admin()
				&& self::POST_TYPE_KEY === $query->get( 'post_type' )
			) {
				$query->tribe_ext_is_event_instructor = true;

				// Override Previous and Next navigation links
				// Commented the following two filters out because the Past Events and Next Events displays do not work (they don't work for Organizers or Venues either) so we just force not displaying the links at all (the next two filters below).
				// add_filter( 'tribe_get_listview_prev_link', array( $this, 'override_previous_link' ) );
				// add_filter( 'tribe_get_listview_next_link', array( $this, 'override_next_link' ) );

				// Single Instructor Page: Force Previous and Next navigation links to not appear.
				add_filter( 'tribe_has_previous_event', '__return_false' );
				add_filter( 'tribe_has_next_event', '__return_false' );
			}
		}

		/**
		 * Tell The Events Calendar that a request for this post type's single post
		 * should run through the tribe_is_event_query() steps, which are
		 * necessary in order to load our custom template.
		 *
		 * @see tribe_is_event_query()
		 *
		 * @param bool $tribe_is_event_query
		 *
		 * @return bool
		 */
		public function set_event_query_for_this_single_post( $tribe_is_event_query ) {
			global $wp_query;

			// Cannot use is_singular() within pre_get_posts because the queried object is not yet set
			if ( ! empty( $wp_query->tribe_ext_is_event_instructor ) ) {
				return true;
			} else {
				return (bool) $tribe_is_event_query;
			}
		}

		/**
		 * Add this plugin's path as a base for the template hierarchy.
		 *
		 * @see Tribe__Events__Templates::getTemplateHierarchy()
		 *
		 * @param $plugins_base_paths
		 *
		 * @return array
		 */
		public function template_paths( $plugins_base_paths ) {
			return array( self::POST_TYPE_KEY => plugin_dir_path( __FILE__ ) ) + $plugins_base_paths;
		}

		/**
		 * Tell The Events Calendar which file (our single.php) should be used as
		 * the template part when loading the single view of one of our custom posts.
		 *
		 * @param $template
		 *
		 * @return string
		 */
		public function set_current_view_template( $template ) {
			if ( is_singular( self::POST_TYPE_KEY ) ) {
				$template = Tribe__Events__Templates::getTemplateHierarchy( self::POST_TYPE_KEY . '/single' );
			}

			return $template;
		}

		/**
		 * Shutdown the view, restore the query, etc. This happens right after
		 * the view file is included.
		 **/
		public function shutdown_view() {
			$this->unhook_view();
		}

		/**
		 * Unhook the hooks we set on this view.
		 **/
		private function unhook_view() {
			// set notices
			remove_action( 'tribe_events_before_view', array( $this, 'set_notices' ) );
			// cleanup after view (reset query, etc)
			remove_action( 'tribe_events_after_view', array( $this, 'shutdown_view' ) );
		}

		/**
		 * Set up the notices for this template
		 **/
		public function set_notices() {
			// By default we only display notices if no events could be found
			if ( have_posts() ) {
				return;
			}

			// Set an appropriate no-results-found message
			$this->nothing_found_notice();
		}

		/**
		 * Override the Previous Events link on the Single Instructor page.
		 *
		 * @param $link
		 *
		 * @return string
		 */
		public function override_previous_link( $link ) {
			parse_str( $link, $result );

			if ( empty( $result['tribe_event_display'] ) ) {
				$tribe_event_display = 'past';
			} else {
				$tribe_event_display = $result['tribe_event_display'];
			}

			if ( empty( $result['tribe_paged'] ) ) {
				$tribe_paged = '1';
			} else {
				$tribe_paged = $result['tribe_paged'];
			}

			$args = array(
				'tribe_event_display' => $tribe_event_display,
				'tribe_paged'         => $tribe_paged,
			);

			$link = add_query_arg( $args, get_permalink( get_the_ID() ) );

			return $link;
		}

		/**
		 * Override the Next Events link on the Single Instructor page.
		 *
		 * @param $link
		 *
		 * @return string
		 */
		public function override_next_link( $link ) {
			parse_str( $link, $result );

			if ( empty( $result['tribe_event_display'] ) ) {
				$tribe_event_display = 'next';
			} else {
				$tribe_event_display = $result['tribe_event_display'];
			}

			if ( empty( $result['tribe_paged'] ) ) {
				$tribe_paged = '1';
			} else {
				$tribe_paged = $result['tribe_paged'];
			}

			$args = array(
				'tribe_event_display' => $tribe_event_display,
				'tribe_paged'         => $tribe_paged,
			);

			$link = add_query_arg( $args, get_permalink( get_the_ID() ) );

			return $link;
		}

		/**
		 * Sets an appropriate no results found message.
		 */
		protected function nothing_found_notice() {
			Tribe__Notices::set_notice( 'event-search-no-results', esc_html__( 'There were no results found.', 'tribe-ext-instructor-linked-post-type' ) );
		}

		/**
		 * Output the upcoming events associated with one of our posts.
		 *
		 * @see tribe_organizer_upcoming_events()
		 *
		 * @return string
		 */
		public function get_upcoming_events( $post_id = false ) {
			$post_id = Tribe__Events__Main::postIdHelper( $post_id );

			if ( $post_id ) {
				$args = array(
					'meta_query'     => array(
						array(
							'key'   => $this->get_linked_post_type_custom_field_key(),
							'value' => $post_id,
						),
					),
					'eventDisplay'   => 'list',
					'posts_per_page' => apply_filters( 'tribe_ext_events_single_' . self::POST_TYPE_KEY . '_posts_per_page', 100 ),
				);

				$html = tribe_include_view_list( $args );

				return apply_filters( 'tribe_ext_' . self::POST_TYPE_KEY . '_upcoming_events', $html );
			}
		}

		public function filter_linked_post_type_meta_key( $return, $post_type ) {
			if ( self::POST_TYPE_KEY === $post_type ) {
				$return = $this->get_order_meta_key();
			}

			return $return;
		}
	} // end class
} // end if class_exists check