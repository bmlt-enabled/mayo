<?php
/**
 * Template for displaying mayo event archives
 */

// Debug output
error_log('Mayo archive template loaded');

get_header(); ?>

<div id="mayo-archive-root"></div>

<?php 
// Ensure these are correctly enqueued
wp_enqueue_script('mayo-public');
wp_enqueue_style('mayo-public');

// Debug output
error_log('Mayo scripts enqueued');

get_footer(); 
?> 