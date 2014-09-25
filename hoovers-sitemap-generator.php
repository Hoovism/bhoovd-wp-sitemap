<?php

/**
 * Plugin Name: Hoover's Sitemap Generator
 * Plugin URI:  http://wpsitemap.hoovism.com
 * Description: Hoover's XML Sitemap Generator for Wordpress
 * Author:      Matthew Hoover
 * Author URI:  http://www.hoovism.com
 * Version:     1.1
 * Text Domain: hoovers-sitemap-generator
 */


function hoovers_sitemap_generator_activate() {
	hoovers_sitemap_generator_rules();
	flush_rewrite_rules();
}


function hoovers_sitemap_generator_deactivate() {
	flush_rewrite_rules();
}

function hoovers_sitemap_generator_rules() {
	add_rewrite_rule("sitemap/([0-9]|[0-9]+)/?$", 'index.php?pagename=sitemap&smp=$matches[1]', "top");
	add_rewrite_rule('sitemap/static/?$', 'index.php?pagename=sitemapstatic', 'top');
	add_rewrite_rule('sitemap/archive/?$', 'index.php?pagename=sitemaparchive', 'top');
	add_rewrite_rule('sitemap/?$', 'index.php?pagename=sitemapindex', 'top');
}

function hoovers_sitemap_generator_query_vars($vars) {
	$vars[] = 'smp';
	return $vars;
}

function hoovers_sitemap_generator_display() {
	global $wpdb, $wp_locale;

	// determine which part of the sitemap we need via pagename
	$page = get_query_var("pagename");

	// $smp needs to be renamed to something like 'page'.
	// This used to be a selector for both the type of request
	// (sitemap index, static sitemap, sitemap) and the page number
	$smp = get_query_var('smp');

	// sitemap priority for posts in the last month
	$post_priority_1m = 0.80;
	$post_cf_1m = "daily";

	// sitemap priority for the posts in the last 3 months
	$post_priority_3m = 0.79;
	$post_cf_3m = "daily";

	// sitemap priority for the posts in the last 6 months
	$post_priority_6m = 0.78;
	$post_cf_6m = "weekly";

	// sitemap priority for the posts in the last 12 months
	$post_priority_12m = 0.77;
	$post_cf_12m = "weekly";

	// sitemap priority for all other posts
	$post_priority_gt12m = 0.75;
	$post_cf_gt12m = "monthly";

	// priority for monthly archives
	$monthly_priority = 0.2;

	// priority for pages
	$page_priority = 0.5;


	if($page == 'sitemap') {

		hoovers_sitemap_header();

		$query = new WP_Query(array(
				'post_type' => 'post',
				'orderby' => 'date',
				'order' => 'ASC',
				'paged' => $smp ? 
						$smp : 1,
				'posts_per_page' => 5000
				)
			);

		while ($query->have_posts()) : $query->the_post();

			$permalink = get_permalink();
			$modified_date = the_modified_date('Y-m-d\TH:i:s', '', '', false).
						w3c_tz_str(get_option('gmt_offset'));


			$pdm = get_the_time('m');
			$pdy = get_the_time('Y');

			if(hoovers_within_prev($pdy, $pdm, $months=1)) {
				$priority = $post_priority_1m;
				$changefreq = $post_cf_1m;
			}
			else if(hoovers_within_prev($pdy, $pdm, $months=3)) {
				$priority = $post_priority_3m;
				$changefreq = $post_cf_3m;
			}
			else if(hoovers_within_prev($pdy, $pdm, $months=6)) {
				$priority = $post_priority_6m;
				$changefreq = $post_cf_6m;
			}
			else if(hoovers_within_prev($pdy, $pdm, $months=12)) {
				$priority = $post_priority_12m;
				$changefreq = $post_cf_12m;
			}
			else {
				$priority = $post_priority_gt12m;
				$changefreq = $post_cf_gt12m;
			}



			echo " <url>\n";
			echo "  <loc>".$permalink."</loc>\n";
			echo "  <lastmod>".$modified_date."</lastmod>\n";
			echo "  <changefreq>".$changefreq."</changefreq>\n";
			echo "  <priority>".$priority."</priority>\n";
			echo " </url>\n";

		endwhile;


		hoovers_sitemap_footer();
		exit();
	}
	else if($page == 'sitemapstatic') {
		hoovers_sitemap_header();


		// pages - add the home page and other static stuffs
		echo " <url>\n";
		echo "  <loc>".get_site_url()."</loc>\n";
		echo "  <changefreq>daily</changefreq>\n";
		echo "  <priority>0.9</priority>\n";
		echo " </url>\n";

		$query = new WP_Query(array(
				'post_type' => 'page',
				'orderby' => 'date',
				'order' => 'ASC'
				)
			);

		while($query->have_posts()) : $query->the_post();
			$permalink = get_permalink();
			$modified_date = the_modified_date('Y-m-d\TH:i:s', '', '', false).
						w3c_tz_str(get_option('gmt_offset'));

			echo " <url>\n";
			echo "  <loc>".$permalink."</loc>\n";
			echo "  <lastmod>".$modified_date."</lastmod>\n";
			echo "  <changefreq>weekly</changefreq>\n";
			echo "  <priority>".$page_priority."</priority>\n";
			echo " </url>\n";

		endwhile;

		hoovers_sitemap_footer();
		exit();
	}
	else if($page == 'sitemapindex') {
		hoovers_sitemap_index_header();

		$query = new WP_Query(array(
					'post_type' => 'post',
					'orderby' => 'date',
					'order' => 'ASC',
					'posts_per_page' => 5000
					)
				);

		echo " <sitemap>\n";
		echo "  <loc>".get_site_url()."/sitemap/static/</loc>\n";
		echo " </sitemap>\n";

		echo " <sitemap>\n";
		echo "  <loc>".get_site_url()."/sitemap/archive/</loc>\n";
		echo " </sitemap>\n";

		for($i = 1; $i <= $query->max_num_pages; $i++) {
			echo " <sitemap>\n";
			echo "  <loc>".get_site_url()."/sitemap/".$i."/</loc>\n";
			echo " </sitemap>\n";
		}

		hoovers_sitemap_index_footer();
		exit();
	}
	else if($page == 'sitemaparchive') {
		hoovers_sitemap_header();

		$where = apply_filters( 'getarchives_where', "WHERE post_type = 'post' AND post_status = 'publish'", array() );


		$last_changed = wp_cache_get( 'last_changed', 'posts' );
		if ( ! $last_changed ) {
			$last_changed = microtime();
			wp_cache_set( 'last_changed', $last_changed, 'posts' );
		}

		$query = "SELECT YEAR(post_date) AS `year`, MONTH(post_date) AS `month`, count(ID) as posts FROM $wpdb->posts $where GROUP BY YEAR(post_date), MONTH(post_date) ORDER BY post_date";
		$key = md5( $query );
		$key = "wp_get_archives:$key:$last_changed";

		if ( ! $results = wp_cache_get( $key, 'posts' ) ) {
			$results = $wpdb->get_results( $query );
			wp_cache_set( $key, $results, 'posts' );
		}
		if ( $results ) {

			foreach ( (array) $results as $result ) {
				$url = get_month_link( $result->year, $result->month );


				echo " <url>\n";
				echo "  <loc>".$url."</loc>\n";
				echo "  <changefreq>".(hoovers_within_prev($result->year, $result->month, 1) ? "daily" : (hoovers_within_prev($result->year, $result->month, 6) ? "weekly" : "monthly"))."</changefreq>\n";
				echo "  <priority>".$monthly_priority."</priority>\n";
				echo " </url>\n";
			}
		}


		hoovers_sitemap_footer();
		exit();
	}

}

function hoovers_within_prev($y, $m, $months=6, $years=0, $include_earliest=false) {

	$cy = date('Y');
	$cm = date('m');

	$earliest_year = $cy - $years;

	// correct for excessive use of months
	while($months >= 12) {
		$earliest_year--;
		$months = $months - 12;
	}

	// if we have more months left than the 
	// current month of the year, fix it
	if($months >= $cm) {
		$earliest_year--;
		$months = $months - $cm;
		$earliest_month = 12 - $months;
	}
	else {
		$earliest_month = $cm - $months;
	}


	// if the year of the post is after the earliest year
	// return true, return false if it is less than the earliest
	// year
	if($y > $earliest_year) {
		return true;
	}
	else if($y < $earliest_year) {
		return false;
	}

	// check if the given month should be included
	if($include_earliest && $m >= $earliest_month) {
		return true;
	}
	else if(!$include_earliest && $m > $earliest_month) {
		return true;
	}

	return false;

}

function hoovers_sitemap_comment() {
	// Please don't delete this; I will be sad.  :'(
	echo " <!--                                                     -->\n";
	echo " <!--   Sitemap generated by Hoover's Sitemap Generator   -->\n";
	echo " <!--            http://wpsitemap.hoovism.com             -->\n";
	echo " <!--                                                     -->\n";
}

function hoovers_sitemap_http_headers() {
	header('HTTP/1.1 200 OK');
	header('Content-Type: application/xml');
}

function hoovers_sitemap_header() {
	hoovers_sitemap_http_headers();

	echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
	echo "<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\"\n";
	echo " xmlns:xsi=\"http://www.w3.org/2001/XMLSchema-instance\"\n";
	echo " xsi:schemaLocation=\"http://www.sitemaps.org/schemas/sitemap/0.9\n";
	echo " http://www.sitemaps.org/schemas/sitemap/0.9/sitemap.xsd\">\n";

	hoovers_sitemap_comment();
}

function hoovers_sitemap_index_header() {
	hoovers_sitemap_http_headers();

	echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
	echo "<sitemapindex xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n";

	hoovers_sitemap_comment();
}

function hoovers_sitemap_footer() {
	echo "</urlset>";
}

function hoovers_sitemap_index_footer() {
	echo "</sitemapindex>";
}

function w3c_tz_str($dec_offset) {
	if($dec_offset < 0) {
		$sign = "-";
		$dec_offset = abs($dec_offset);
	}
	else {
		$sign = "+";
	}

	$hours = intval($dec_offset);
	$minutes = ($dec_offset - $hours) * 60;
	return $sign.leading_zero($hours).":".leading_zero($minutes);
}

function leading_zero($str, $digits=2) {
	while(strlen($str) < $digits) {
		$str = "0".$str;
	}

	return $str;
}


register_activation_hook(__FILE__, 'hoovers_sitemap_generator_activate');
register_deactivation_hook(__FILE__, 'hoovers_sitemap_generator_deactivate');
add_action('init', 'hoovers_sitemap_generator_rules');
add_filter('query_vars', 'hoovers_sitemap_generator_query_vars');
add_filter('template_redirect', 'hoovers_sitemap_generator_display');


?>
