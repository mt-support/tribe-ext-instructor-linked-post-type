<?php
/**
 * Plugin Name:     The Events Calendar Extension: Instructors Linked Post Type
 * Description:     A boilerplate/"starter extension" for you to use as-is or fork. Used as-is, an "Instructors" custom post type will be created and linked to The Events Calendar's Events, like Organizers are, and basic output will be added to the Single Event Page. See this plugin file's code comments for forking instructions.
 * Version:         1.0.0
 * Extension Class: Tribe__Extension__Instructors_Linked_Post_Type
 * Author:          Modern Tribe, Inc.
 * Author URI:      http://m.tri.be/1971
 * License:         GPL version 3 or any later version
 * License URI:     https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:     tribe-ext-instructors-linked-post-type
 */

// Do not load unless Tribe Common is fully loaded.
if ( ! class_exists( 'Tribe__Extension' ) ) {
	return;
}

/**
 * TODO: How to Fork this...
 * Find all mentions of "instructor" and "instructors", including the name of this plugin directory and this file's class name.
 * Then, replace with your own post type and register_post_type() arguments as appropriate for your project.
 * And add your own custom fields -- see $this->get_custom_field_labels()
 * Test everything is working as desired, including saving of meta data and customizing how it outputs to the Single Events Page.
 * You might also want to build a single-{post_type}.php similar to /wp-content/plugins/events-calendar-pro/src/views/pro/single-organizer.php
 * @link https://developer.wordpress.org/themes/template-files-section/custom-post-type-template-files/
 */

/**
 * Conditional tag to check if current page is an event instructor page.
 *
 * If true, you may want to do something like this:
 * get_template_part( 'organizer_template' );
 *
 * @return bool
 **/
if ( ! function_exists( 'tribe_is_event_instructors' ) ) {
	function tribe_is_event_instructors() {
		global $wp_query;

		$tribe_is_event_instructors = ! empty( $wp_query->tribe_is_event_instructors );

		return apply_filters( 'tribe_query_is_event_instructors', $tribe_is_event_instructors );
	}
}

/**
 * Extension main class, class begins loading on init() function.
 */
class Tribe__Extension__Instructors_Linked_Post_Type extends Tribe__Extension {

	/**
	 * Setup the Extension's properties.
	 *
	 * This always executes even if the required plugins are not present.
	 */
	public function construct() {
		// Linked Post Types started in version 4.2
		// Tribe__Duplicate__Strategy_Factory class exists since version 4.6
		$this->add_required_plugin( 'Tribe__Events__Main', '4.6' );
		$this->set_url( 'https://theeventscalendar.com/knowledgebase/abstract-post-types/' );
	}

	/**
	 * Extension initialization and hooks.
	 */
	public function init() {
		add_action( 'init', array( $this, 'register_our_post_type' ) );
		add_filter( 'tribe_events_linked_post_type_args', array( $this, 'filter_linked_post_type_args' ), 10, 2 );
		add_filter( 'tribe_events_linked_post_id_field_index', array( $this, 'linked_post_id_field_index' ), 10, 2 );
		add_filter( 'tribe_events_linked_post_type_container', array( $this, 'linked_post_type_container' ), 10, 2 );
		add_action( 'init', array( $this, 'link_post_type_to_events' ) );
		add_action( 'tribe_events_linked_post_new_form', array( $this, 'event_edit_form_create_fields' ) );
		add_action( 'tribe_events_linked_post_create_' . $this->get_post_type_key(), array(
			$this,
			'event_edit_form_save_data',
		), 10, 5 );
		add_filter( 'tribe_events_linked_post_meta_values__tribe_linked_post_' . $this->get_post_type_key(), array(
			$this,
			'filter_out_invalid_post_ids',
		) );
		add_action( 'admin_menu', array( $this, 'add_meta_box_to_event_editing' ) );
		add_action( 'tribe_events_single_event_after_the_meta', array( $this, 'output_linked_posts' ) );
	}

	/**
	 * Removes anything other than positive integers from Post IDs.
	 *
	 * Also converts Post ID-looking strings to integers.
	 *
	 * @see Tribe__Events__Linked_Posts::get_meta_key()
	 *
	 * @param array $current_linked_posts The array of the current meta field's values.
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
			if ( isset( $data[ $key ] ) && $data[ $key ] ) {
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
	 * Our post type's custom field label names.
	 *
	 * Enter "Phone" or "Email Address" -- however you want it to look to the
	 * user, and the actual custom field key will be created from it.
	 *
	 * @return string
	 */
	protected function get_a_custom_field_key_from_label( $label, $prepend_underscore = true ) {
		$label = preg_replace( '/[-\s]/', '_', $label );

		$label = sprintf( '%s_%s', $this->get_post_type_key(), $label );

		if ( ! empty( $prepend_underscore ) ) {
			$label = sprintf( '_%s', $label );
		}

		$label = sanitize_key( $label );

		return $label;
	}

	/**
	 * Our post type's custom field keys, generated from each field's label.
	 *
	 * Converts spaces and hyphens to underscores, prepends with our post type
	 * key, and then prepended with an underscore so it's "hidden" from default
	 * wp-admin Custom Fields editing.
	 *
	 * @see $this->get_custom_field_labels()
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
	 * Our post type's key.
	 *
	 * Must not exceed 20 characters and may only contain lowercase alphanumeric
	 * characters, dashes, and underscores.
	 *
	 * @see sanitize_key()
	 *
	 * @return string
	 */
	protected function get_post_type_key() {
		return 'tec_instructor';
	}

	/**
	 * Get our post type's label ('name' by default, which is capitalized and plural).
	 *
	 * @param $label string Any defined label key for our post type.
	 *
	 * @return string
	 */
	protected function get_post_type_label( $label = 'name' ) {
		$post_type_object = get_post_type_object( $this->get_post_type_key() );

		$result = $post_type_object->labels->$label;

		return $result;
	}

	/**
	 * Set the arguments for and register the post type.
	 *
	 * @link https://developer.wordpress.org/reference/functions/register_post_type/
	 */
	public function register_our_post_type() {
		$labels = array(
			'name'                    => _x( 'Instructors', 'Post type general name', 'tribe-ext-instructors-linked-post-type' ),
			'singular_name'           => _x( 'Instructor', 'Post type singular name', 'tribe-ext-instructors-linked-post-type' ),
			'singular_name_lowercase' => _x( 'instructor', 'Post type singular name', 'tribe-ext-instructors-linked-post-type' ),
		);

		$args = array(
			'labels'              => $labels,
			'public'              => true,
			'exclude_from_search' => true,
			'publicly_queryable'  => false,
			'show_in_menu'        => 'edit.php?post_type=' . Tribe__Events__Main::POSTTYPE,
			'menu_icon'           => 'dashicons-businessman',
			'map_meta_cap'        => true,
			'supports'            => array( 'title', 'editor' ),
			'rewrite'             => array(
				'slug'       => 'instructor',
				'with_front' => false,
			),
		);

		register_post_type( $this->get_post_type_key(), $args );
	}

	/**
	 * Filters the args sent when linking the post type.
	 *
	 * If 'allow_creation' is not set to TRUE, can only "find" posts via the
	 * drop-down chooser, not also create them, when creating a new Event.
	 *
	 * @param array  $args Array of linked post type arguments
	 * @param string $post_type Linked post type
	 *
	 * @return array
	 */
	public function filter_linked_post_type_args( $args, $post_type ) {
		if ( $this->get_post_type_key() !== $post_type ) {
			return $args;
		}

		$args[ 'allow_creation' ] = true;

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
	protected function get_post_id_field_name() {
		return $this->get_post_type_label( 'singular_name' ) . '_ID'; // Instructor_ID
	}

	/**
	 * Filters the linked post id field.
	 *
	 * @param string $id_field Field name of the field that will hold the ID
	 * @param string $post_type Post type of linked post
	 */
	public function linked_post_id_field_index( $id_field, $post_type ) {
		if ( $this->get_post_type_key() === $post_type ) {
			return $this->get_post_id_field_name();
		}

		return $id_field;
	}

	/**
	 * Build the string used for this linked post type's container name.
	 *
	 * @see Tribe__Events__Linked_Posts::get_post_type_container()
	 *
	 * @return string
	 */
	protected function get_post_type_container_name() {
		return $this->get_post_type_label( 'singular_name_lowercase' );
	}

	/**
	 * Filters the index that contains the linked post type data during form submission.
	 *
	 * Form field "name"s need to incorporate this.
	 *
	 * @param string $container Container index that holds submitted data
	 * @param string $post_type Post type of linked post
	 */
	public function linked_post_type_container( $container, $post_type ) {
		if ( $this->get_post_type_key() === $post_type ) {
			return $this->get_post_type_container_name();
		}

		return $container;
	}

	public function link_post_type_to_events() {
		if ( function_exists( 'tribe_register_linked_post_type' ) ) {
			tribe_register_linked_post_type( $this->get_post_type_key() );
		}
	}

	/**
	 * Callback for adding the Meta box to the admin page
	 */
	public function add_meta_box_to_event_editing() {
		$meta_box_id = sprintf( 'tribe_events_%s_details', $this->get_post_type_key() );

		add_meta_box(
			$meta_box_id,
			sprintf( esc_html__( '%s Information', 'tribe-ext-instructors-linked-post-type' ), $this->get_post_type_label( 'singular_name' ) ),
			array(
				$this,
				'meta_box',
			),
			$this->get_post_type_key(),
			'normal',
			'high'
		);
	}

	protected function get_meta_box_tr_html_for_a_field_label( $custom_field_label ) {
		$custom_field = $this->get_a_custom_field_key_from_label( $custom_field_label );

		$name = sprintf( '%s[%s][]',
			$this->get_post_type_container_name(),
			$custom_field
		);

		$output = sprintf(
			'<tr class="linked-post %1$s tribe-linked-type-%1$s-%2$s">
            <td>
                <label for="%3$s">%4$s</label>
            </td>
            <td>
                <input id="%3$s" type="text"
                       name="%5$s"
                       class="%1$s-%2$s" size="25" value=""/>
            </td>
            </tr>',
			$this->get_post_type_key(),
			esc_attr( $custom_field_label ),
			$custom_field,
			esc_html__( $custom_field_label . ':', 'tribe-ext-instructors-linked-post-type' ),
			$name
		);

		return $output;
	}

	/**
	 * Output the template used when editing this post directly via wp-admin
	 * post editor (not when editing an Event in wp-admin).
	 *
	 * Based on /wp-content/plugins/the-events-calendar/src/admin-views/organizer-meta-box.php
	 */
	public function meta_box() {
		global $post;
		$options = '';
		$style   = '';
		$post_id = $post->ID;
		$saved   = false;

		$post_type_key = $this->get_post_type_key();

		if ( $post_type_key === $post->post_type ) {

			if (
				( is_admin() && isset( $_GET[ 'post' ] ) && $_GET[ 'post' ] )
				|| ( ! is_admin() && isset( $post_id ) )
			) {
				$saved = true;
			}

			if ( ! empty( $post_id ) ) {

				if ( $saved ) { //if there is a post AND the post has been saved at least once.
					$title = apply_filters( 'the_title', $post->post_title, $post->ID );
				}

				foreach ( $this->get_custom_field_keys() as $tag ) {
					if ( metadata_exists( 'post', $post_id, $tag ) ) {
						// heads up: variable variables
						// https://secure.php.net/manual/en/language.variables.variable.php
						$$tag = get_post_meta( $post_id, $tag, true );
					}
				}
			}
		}
		?>
        <style type="text/css">
            #EventInfo-<?php echo $this->get_post_type_key(); ?> {
                border: none;
            }
        </style>
        <div id='eventDetails-<?php echo $this->get_post_type_key(); ?>' class="inside eventForm">
            <table cellspacing="0" cellpadding="0" id="EventInfo-<?php echo $this->get_post_type_key(); ?>">
				<?php
				foreach ( $this->get_custom_field_labels() as $custom_field_label ) {
					echo $this->get_meta_box_tr_html_for_a_field_label( $custom_field_label );
				}
				?>
            </table>
        </div>
		<?php
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
		$post_type_key = $this->get_post_type_key();

		// We only want to affect our own post type; bail if it's some other post type.
		if ( $post_type_key !== $post_type ) {
			return;
		}

		foreach ( $this->get_custom_field_labels() as $custom_field_label ) {
			echo $this->get_meta_box_tr_html_for_a_field_label( $custom_field_label );
		}
	}

	/**
	 * Saves the instructor information passed via an event.
	 *
	 * @param string $id Post type ID index.
	 * @param array  $data Data for submission.
	 * @param string $linked_post_type Post type.
	 * @param string $post_status Post status.
	 * @param int    $event_id Post ID of the post the post type is attached to.
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
			isset( $data[ $our_id ] )
			&& 0 < (int) $data[ $our_id ]
		) {
			if (
				! empty( $data[ $our_id ] )
				&& 1 === count( $data )
			) {
				// We're updating an existing post but only an ID was passed, no other data.
				// So just return the ID, i.e. do nothing.
				return $data[ $our_id ];
			} else {
				// Need to update an existing post.
				// See Tribe__Events__Organizer->update() for inspiration how to make an "update" function.
				return $this->update_existing( $data[ $our_id ], $data );
			}
		}

		// Otherwise, we're not updating an existing post; we have to make a new post.
		return $this->create_new_post( $data, $post_status );
	}

	/**
	 * Creates a new Instructor
	 *
	 * @param array  $data The Instructor data.
	 * @param string $post_status the Intended post status.
	 *
	 * @see Tribe__Events__Organizer::create() Inspiration for additional functionality, such as implementing Avoid Duplicates.
	 *
	 * @return mixed
	 */
	public function create_new_post( $data, $post_status = 'publish' ) {
		if (
			( isset( $data[ 'name' ] )
			  && $data[ 'name' ]
			)
			|| $this->has_this_post_types_custom_fields( $data )
		) {
			$title   = isset( $data[ 'name' ] ) ? $data[ 'name' ] : sprintf( esc_html__( 'Unnamed %s', 'tribe-ext-instructors-linked-post-type' ), $this->get_post_type_label( 'singular_name' ) );
			$content = isset( $data[ 'Description' ] ) ? $data[ 'Description' ] : '';
			$slug    = sanitize_title( $title );

			$data = new Tribe__Data( $data );

			$our_id = $this->get_post_id_field_name();

			unset( $data[ $our_id ] );

			$postdata = array(
				'post_title'    => $title,
				'post_content'  => $content,
				'post_name'     => $slug,
				'post_type'     => $this->get_post_type_key(),
				'post_status'   => Tribe__Utils__Array::get( $data, 'post_status', $post_status ),
				'post_author'   => $data[ 'post_author' ],
				'post_date'     => $data[ 'post_date' ],
				'post_date_gmt' => $data[ 'post_date_gmt' ],
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
	 * Updates an instructor
	 *
	 * @param int   $id The instructor ID to update.
	 * @param array $data The instructor data.
	 *
	 * @return int The updated instructor post ID
	 */
	protected function update_existing( $id, $data ) {
		$data = new Tribe__Data( $data, '' );

		$our_id = $this->get_post_id_field_name();

		unset( $data[ $our_id ] );

		$args = array_filter( array(
			'ID'            => $id,
			'post_title'    => Tribe__Utils__Array::get( $data, 'post_title', $data[ 'Organizer' ] ), // TODO
			'post_content'  => Tribe__Utils__Array::get( $data, 'post_content', $data[ 'Description' ] ),
			'post_excerpt'  => Tribe__Utils__Array::get( $data, 'post_excerpt', $data[ 'Excerpt' ] ),
			'post_author'   => $data[ 'post_author' ],
			'post_date'     => $data[ 'post_date' ],
			'post_date_gmt' => $data[ 'post_date_gmt' ],
			'post_status'   => $data[ 'post_status' ],
		) );

		// Update existing. Beware of the potential for infinite loops if you hook to 'save_post' (if it aggressively affects all post types) or if you hook to 'save_post_' . $this->get_post_type_key()
		if ( 1 < count( $args ) ) {
			$tag = 'save_post_' . $this->get_post_type_key();
			// TODO
			//remove_action( $tag, array( tribe( 'tec.main' ), 'save_organizer_data' ), 16 );
			wp_update_post( $args );
			//add_action( $tag, array( tribe( 'tec.main' ), 'save_organizer_data' ), 16, 2 );
		}

		// TODO?
		$post_fields = array_merge( Tribe__Duplicate__Post::$post_table_columns, array(
			'Description',
			'Excerpt',
		) );

		$meta = array_diff_key( $data->to_array(), array_combine( $post_fields, $post_fields ) );

		$this->save_meta( $id, $meta );

		return $id;
	}

	/**
	 * Saves our post type's custom fields.
	 *
	 * @param int   $post_id The Post ID.
	 * @param array $data The post's data.
	 *
	 */
	public function save_meta( $post_id, $data ) {
		$our_id = $this->get_post_id_field_name();

		if ( isset( $data[ 'FeaturedImage' ] ) && ! empty( $data[ 'FeaturedImage' ] ) ) {
			update_post_meta( $post_id, '_thumbnail_id', $data[ 'FeaturedImage' ] );
			unset( $data[ 'FeaturedImage' ] );
		}

		// No point in saving ID to itself.
		unset( $data[ $our_id ] );

		/*
		 * The post name is saved in the post_title.
		 *
		 * @see Tribe__Events__Linked_Posts::get_post_type_name_field_index()
		 */
		unset( $data[ 'name' ] );

		foreach ( $data as $key => $var ) {
			update_post_meta( $post_id, $key, sanitize_text_field( $var ) );
		}
	}

	public function output_linked_posts() {
		$output = '';

		$linked_posts = tribe_get_linked_posts_by_post_type( get_the_ID(), $this->get_post_type_key() );

		if ( ! empty( $linked_posts ) ) {
			$output .= sprintf( '<div class="%s">', $this->get_post_type_key() );
			foreach ( $linked_posts as $post ) {
				$output .= sprintf( '<div>%s</div>', $post->post_title );
			}
			$output .= '</div>';
		}

		echo $output;
	}

}