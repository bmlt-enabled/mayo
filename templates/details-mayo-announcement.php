<?php
/**
 * Template for displaying mayo announcement details
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

get_header(); ?>

<div id="mayo-announcement-details-root">Loading...</div>

<?php
wp_enqueue_script('mayo-public');
wp_enqueue_style('mayo-public');

get_footer();
?>
