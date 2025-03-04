<?php
/**
 * Template for displaying mayo event details
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

get_header(); ?>

<div id="mayo-details-root"></div>

<?php 
wp_enqueue_script('mayo-public');
wp_enqueue_style('mayo-public');

get_footer(); 
?> 