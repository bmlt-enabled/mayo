<?php
/**
 * Template for displaying mayo event archives
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

get_header(); ?>

<div id="mayo-archive-root">Loading...</div>

<?php 
wp_enqueue_script('mayo-public');
wp_enqueue_style('mayo-public');

get_footer(); 
?> 