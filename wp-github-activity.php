<?php
/*
Plugin Name: WP GitHub Activity 
Plugin URI: http://crowdfavorite.com/wordpress/plugins/ 
Description: Show your recent GitHub activity on your WordPress site via shortcode or sidebar widget.
Version: 1.0
Author: Crowd Favorite
Author URI: http://crowdfavorite.com
*/
class cf_github_activity {
	public static $instance = null;
	// has the CSS and JS already been output on this page load?
	var $css_output = false;
	var $js_output = false;
	public static function instance() {
		if (is_null(self::$instance)) {
			self::$instance = new cf_github_activity;
		}
		return self::$instance;
	}
}
function cf_github_activity($username, $excluded = array(), $count = 10, $include_css = true) {
	$feed = fetch_feed('https://github.com/'.$username.'.atom');
	if (is_wp_error($feed)) {
		return '';
	}
	// disable the default CSS or JS using these filters
	cf_github_activity::instance()->css_output = apply_filters('cf_github_activity_css_output', cf_github_activity::instance()->css_output);
	cf_github_activity::instance()->js_output = apply_filters('cf_github_activity_js_output', cf_github_activity::instance()->js_output);
	$html = '';
	if ($include_css && !cf_github_activity::instance()->css_output) {
		$html .= cf_github_activity_css();
		cf_github_activity::instance()->css_output = true;
	}
	$i = 0;
	foreach ($feed->get_items() as $item) {
		if ($i == $count) {
			break;
		}
		$content = $item->data['child']['http://www.w3.org/2005/Atom']['content'][0]['data'];
		$skip = false;
		if (!empty($excluded)) {
			foreach ($excluded as $exclude) {
				if (strpos($content, '<!-- '.$exclude.' -->') !== false) {
					$skip = true;
					break;
				}
			}
		}
		if (!$skip) {
			$html .= '<div class="github-activity-item">'.$content.'</div>';
			$i++;
		}
	}
	if (!cf_github_activity::instance()->js_output) {
		$html .= cf_github_activity_js_fix_urls();
		cf_github_activity::instance()->js_output = true;
	}
	return $html;
}
function cf_github_activity_css() {
?>
	<style>
		.github-activity-item .title a:nth-of-type(1),
		.github-activity-item .commits ul li span,
		.github-activity-item .details a {
			display: none;
		}
		.github-activity-item blockquote:before,
		.github-activity-item blockquote:after {
			content: '"';
			display: inline;
			color: #fff;
			font-size: 13px;
			padding: 0px;
			top: 0px;
			left: 0px;
		}
		.github-activity-item blockquote {
			display: inline;
			color: #fff;
			font-size: 13px;
			padding: 0px;
		}
		.github-activity-item .details .commits ul,
		.github-activity-item .details .commits ul li {
			list-style: none;
			display: inline;
			padding: 0px;

		}
		.github-activity-item .title,
		.github-activity-item .details,
		.github-activity-item .details .commits ul li code {
			font-size: 13px;
			display: inline;
		}
		.github-activity-item .details .commits ul li code a {
			display: inline;
		}
		.github-activity-item .details .commits ul li code:before {
			content: '- ';
			color: #fff;
			display: inline;
		}
		.github-activity-item .time {
			display: none;
		}
	</style>
<?php
}
function cf_github_activity_js_fix_urls() {
	ob_start();
?>
<script>
jQuery(function($) {
	$('.github-activity-item a').each(function() {
		var href = $(this).attr('href');
		var target = $(this).attr('target');
		if (href.indexOf('https://') == -1) {
			$(this).attr('href', 'https://github.com' + href);
		}
		$(this).attr('target', '_blank');
	});
});
</script>
<?php
	return ob_get_clean();
}
function cf_github_activity_shortcode($args = array()) {
	$args = shortcode_atts(array(
		'username' => null,
		'excluded' => null,
		'count' => 10
	), $args);
	// if no username, get out.
	if (empty($args['username'])) {
		return '';
	}
	$excluded = (!empty($args['excluded']) ? explode(',', $args['excluded']) : array());
	$excluded = array_unique(array_map('trim', $excluded));
	return cf_github_activity($args['username'], $excluded, $args['count']);
}
add_shortcode('github_activity', 'cf_github_activity_shortcode');
class CF_GitHub_Activity_Widget extends WP_Widget {
	function __construct() {
		$title = __('GitHub Activity', 'github-activity');
		$desc = __('Show recent GitHub Activity.', 'github-activity');
		// widget actual processes
		parent::__construct(
			'github-activity-widget', 
			$title,
			array(
				'classname' => 'github-activity-widget',
				'description' => $desc
			)
		);
	}
	function form($instance) {
		$defaults = array(
			'title' => __('GitHub Activity', 'github-activity'),
			'username' => '',
			'excluded' => '',
			'count' => 10,
		);
		foreach ($defaults as $k => $v) {
			if (!isset($instance[$k])) {
				$instance[$k] = $v;
			}
		}
?>
<p>
	<label for="<?php echo $this->get_field_id('title'); ?>"><?php _e('Title', 'github-activity'); ?></label>
	<input type="text" name="<?php echo $this->get_field_name('title'); ?>" id="<?php echo $this->get_field_id('title'); ?>" value="<?php echo esc_attr($instance['title']); ?>" class="widefat" />
</p>
<p>
	<label for="<?php echo $this->get_field_id('username'); ?>"><?php _e('Username', 'github-activity'); ?></label>
	<input type="text" name="<?php echo $this->get_field_name('username'); ?>" id="<?php echo $this->get_field_id('username'); ?>" value="<?php echo esc_attr($instance['username']); ?>" class="widefat" />
</p>
<p>
	<label for="<?php echo $this->get_field_id('count'); ?>"><?php _e('How Many Items?', 'github-activity'); ?></label>
	<input type="text" size="3" name="<?php echo $this->get_field_name('count'); ?>" id="<?php echo $this->get_field_id('count'); ?>" value="<?php echo esc_attr($instance['count']); ?>" />
</p>
<p>
	<label for="<?php echo $this->get_field_id('excluded'); ?>"><?php _e('Activity Types to Exclude', 'github-activity'); ?></label>
	<input type="text" name="<?php echo $this->get_field_name('excluded'); ?>" id="<?php echo $this->get_field_id('excluded'); ?>" value="<?php echo esc_attr($instance['excluded']); ?>" />
</p>
<p class="help"><?php _e('Comma separated - example: "issue_comment, watch". Types are the leading HTML comment in the output HTML.', 'github-activity'); ?></p>
<?php
	}
	function update($new_instance, $old_instance) {
		$instance = $old_instance;
		$instance['title'] = strip_tags($new_instance['title']);
		$instance['username'] = strip_tags($new_instance['username']);
		if (!($count = intval($new_instance['count']))) {
			$count = 1;
		}
		$instance['count'] = $count;
		$instance['excluded'] = strip_tags($new_instance['excluded']);
		return $instance;
	}
	function widget($args, $instance) {
		extract($args);
		$excluded = (!empty($instance['excluded']) ? explode(',', $instance['excluded']) : array());
		$excluded = array_unique(array_map('trim', $excluded));
		echo $before_widget.$before_title.$instance['title'].$after_title;
		echo cf_github_activity($instance['username'], $excluded, $instance['count']);
		echo $after_widget;
	}
	
	static function register() {
		register_widget('CF_GitHub_Activity_Widget');
	}
}
add_action('widgets_init', array('CF_GitHub_Activity_Widget', 'register'));
