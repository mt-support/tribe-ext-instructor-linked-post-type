<?php
/**
 * Single Instructor Template
 * The template for an instructor. By default it displays instructor information and lists
 * events that occur with the specified instructor.
 *
 * This view contains the filters required to create an effective single instructor view.
 */

// Do not allow loading directly or in an unexpected manner.
if ( ! class_exists( 'Tribe__Extension__Instructors_Linked_Post_Type' ) ) {
	return;
}

$extension_instance = Tribe__Extension__Instructors_Linked_Post_Type::instance();

global $post;

$post_id = $post->ID;

$post_type_key = $post->post_type;
?>

<?php while ( have_posts() ) : the_post(); ?>
    <div class="tribe-events-<?php echo $post_type_key; ?>">
        <p class="tribe-events-back">
            <a href="<?php echo esc_url( tribe_get_events_link() ); ?>"
               rel="bookmark"><?php printf( __( '&larr; Back to %s', 'tribe-ext-instructors-linked-post-type' ), tribe_get_event_label_plural() ); ?></a>
        </p>

		<?php do_action( 'tribe_events_single_' . $post_type_key . '_before_item' ) ?>
        <div class="tribe-events-<?php echo $post_type_key; ?>-meta tribe-clearfix">

            <!-- Instructor Title -->
			<?php do_action( 'tribe_events_single_' . $post_type_key . '_before_title' ) ?>
            <h2 class="tribe-<?php echo $post_type_key; ?>-name"><?php echo esc_html( get_the_title( $post_id ) ); ?></h2>
			<?php do_action( 'tribe_events_single_' . $post_type_key . '_after_title' ) ?>

            <!-- Instructor Meta -->
			<?php do_action( 'tribe_events_single_' . $post_type_key . '_before_the_meta' ); ?>
			<?php echo $extension_instance->get_event_single_custom_fields_output( $post_id ); ?>
			<?php do_action( 'tribe_events_single_' . $post_type_key . '_after_the_meta' ) ?>

            <!-- Instructor Featured Image -->
			<?php echo tribe_event_featured_image( $post_id, 'full' ) ?>

            <!-- Instructor Content -->
			<?php if ( get_the_content() ) { ?>
                <div class="tribe-<?php echo $post_type_key; ?>-description tribe-events-content">
					<?php the_content(); ?>
                </div>
			<?php } ?>

        </div>
        <!-- .tribe-events-instructor-meta -->
		<?php do_action( 'tribe_events_single_' . $post_type_key . '_after_item' ) ?>

        <!-- Upcoming event list -->
		<?php do_action( 'tribe_events_single_' . $post_type_key . '_before_upcoming_events' ) ?>

		<?php
		// Use the tribe_events_single_' . $post_type_key . '_posts_per_page to filter the number of events to get here.
		echo $extension_instance->get_upcoming_events( $post_id ); ?>

		<?php do_action( 'tribe_events_single_' . $post_type_key . '_after_upcoming_events' ) ?>

    </div><!-- .tribe-events-tec_instructor -->
	<?php
	do_action( 'tribe_events_single_' . $post_type_key . '_after_template' );
endwhile;
