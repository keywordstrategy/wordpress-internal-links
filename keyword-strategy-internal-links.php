<?php
/*
Plugin Name: Keyword Strategy Internal Links
Plugin URI: http://www.keywordstrategy.org/wordpress-plugin/
Description: Keyword Strategy link generator plugin
Version: 1.8.5
Author: Keyword Strategy
Author URI: http://www.keywordstrategy.org/
License: GPL2
*/

/*  Copyright 2011  Keyword Strategy  (email : info@keywordstrategy.org)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License, version 2, as 
    published by the Free Software Foundation.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
 */

define('KWS_MAX_LINKS', 1);
if (! function_exists('get_admin_url'))
{
	die("<h3>Keyword Strategy plugin error: please update your WordPress installation.</h3>");
}
define('KWS_PLUGIN_URL', get_admin_url(null, 'options-general.php?page=keyword-strategy-internal-links'));


add_action('admin_init', 'kws_admin_init');
add_action('admin_menu', 'kws_option_page');
add_action('edit_post', 'kws_edit_post');
add_action('kws_cron', 'kws_update_keywords');
register_activation_hook(__FILE__, 'kws_activation');
register_deactivation_hook(__FILE__, 'kws_deactivation');
// related sites action
add_action('wp_ajax_kws_related_urls', 'kws_related_urls');

function kws_option_page()
{
	add_options_page('Keyword Strategy options', 'Keyword Strategy', 'manage_options', 'keyword-strategy-internal-links', 'kws_admin');
}

function kws_admin_init()
{
	wp_enqueue_style('thickbox');
	wp_enqueue_script('jquery');
	wp_enqueue_script('thickbox');
}



function kws_update_keywords()
{
	set_time_limit(120);

	global $kws_options;
	kws_options();
	kws_fix_database();

	$result = _kws_update_keywords();
	if ($result)
	{
		$kws_options['update_error'] = $result;
	}
	else
	{
		$kws_options['update_error'] = false;
		$kws_options['last_update'] = time();
	}
	update_option('kws_options', $kws_options);
}

function _kws_update_keywords()
{
	global $kws_options;
	$result = kws_check_login($kws_options['username'], $kws_options['password']);
	if ($result['body'] != 'ok')
	{
		return 'Incorrect username or password for Keyword Strategy';
	}
	$cookies = $result['cookies'];
	$project = kws_get_project($cookies);
	if (! is_numeric($project))
	{
		$kws_options['project'] = false;
		return 'Can\'t find project for this website';
	}
	$kws_options['project'] = $project;

	$keywords = kws_get_keywords($cookies, $project);
	if (! $keywords)
	{
		return 'No keywords found';
	}
	$kws_options['total_keywords'] = kws_update_database($keywords);

	$keywords = kws_get_inpage($cookies, $project);
	kws_update_database_inpage($keywords);

	$keywords = kws_get_related($cookies, $project);
	kws_update_database_related($keywords);

	update_option('kws_options', $kws_options);
	return false;
}


function kws_request($cfg=array())
{
	global $kws_options, $kws_cookies;
	$cfg_default = array('cookies' => false, 'url' => 'keywords/grid_data', 'start' => 0, 'project' => $kws_options['project'], 'remote' => 1, 'params' => array(), 'method'=>'POST', 'timeout' => 60, 'limit' => 10000, 'unserialize' => true);
	$cfg = array_merge($cfg_default, $cfg);
	extract($cfg);
	if (! $cookies)
	{
		if (! $kws_cookies)
		{
			$result = kws_check_login($kws_options['username'], $kws_options['password']);
			if (! is_array($result) || $result['body'] != 'ok')
			{
				return false;
			}
			$cookies = $result['cookies'];	
			$kws_cookies = $cookies;
		}
		else
		{
			$cookies = $kws_cookies;
		}
	}
	$body = array('start'=>$start, 'limit'=>$limit,'project_id'=>$project, 'remote'=>$remote,);
	foreach ($params AS $param_name => $param_value)
	{
		$body[$param_name] = $param_value;
	}
	$request = new WP_Http;
	$result = $request->request('http://www.keywordstrategy.org/'.$url, array('method'=>$method, 'cookies'=>$cookies, 'body'=>$body, 'timeout'=>60));
	if (! is_array($result)) return false;

	return $unserialize? @unserialize($result['body']) : $result['body'];
}


function kws_related_google($keyword, $url)
{
	$parsed = parse_url($url);
	if (! $parsed) return array();
	$search_similar = "site:{$parsed['host']} similar:{$url} {$keyword}";
	$search_keyword = "site:{$parsed['host']} {$keyword}";
	$return = array_merge(kws_google_extract_urls($search_similar, $url), kws_google_extract_urls($search_keyword, $url));
	$return = array_unique($return);
	return $return;
}

function kws_google_extract_urls($search_query, $ignore_url)
{
	$search_url = "http://www.google.com/search?num=50&q=".urlencode($search_query);
	$parsed = parse_url($ignore_url);
	$request = new WP_Http;
	$result = $request->request($search_url, array('timeout' => 30));
	if (! is_array($result)) return array();
	$html = $result['body'];
	preg_match_all('#href="([^"]+?)"#', $html, $matches);
	if (!$matches) return array();
	$return = array();
	foreach ($matches[1] as $link)
	{
		$pos = strpos($link, $parsed['host']);
		if ($pos === false || $pos > 8 || $link == $url) continue;
		$return[] = $link;
	}
	return $return;
}



function kws_options()
{
	global $kws_options;
	if ($kws_options === NULL)
	{
		$kws_options = get_option('kws_options');
	}
	return $kws_options;
}


function kws_get_project($cookies)
{
	$site_url = site_url();
	return kws_request(array(
		'url' => 'util/get_project',
		'cookies' => $cookies,
		'unserialize' => false,
		'params' => array('site' => $site_url),
	));
}


function kws_get_keywords($cookies, $project_id)
{
	global $kws_options;
	$keywords_limit = isset($kws_options['keywords_limit']) ? $kws_options['keywords_limit']:1000;
	if ($keywords_limit < 20000) $keywords_limit *= 2;
	return kws_request(array(
		'params' => array('start'=>0, 'limit'=>$keywords_limit,'project_id'=>$project_id,'url'=>'!null','sort'=>'rank', 'dir'=>'ASC', 'remote'=>1, 'exact_match' => '>= '.$kws_options['exact_match']),
		'cookies' => $cookies,
	));
}

function kws_blacklist($keywords)
{
	global $kws_options;
	$params = array('keywords' => $keywords, 'blacklist' => 1);
	return kws_request(array('url' => 'keywords/blacklist', 'params' => $params));
}


function kws_update_inpage($title, $content)
{
	global $kws_options;
	kws_request(array(
		'url' => 'keywords/update_inpage',
		'params' => array('project_id' => $kws_options['project'], 'title' => $title, 'content'=>$content, 'blacklist' => 1),
		'unserialize' => false,
	));
	return true;
}


function kws_detach($keywords)
{
	global $kws_options;
	$params = array('keywords' => $keywords);
	return kws_request(array('params' => $params, 'url' => 'keywords/clear_group'));
}


function kws_get_inpage($cookies, $project_id)
{
	global $kws_options;
	return kws_request(array(
		'params' => array('start'=>0, 'limit'=>10000, 'project_id'=>$project_id, 'url'=>'!null', 'inpage'=>'none', 'sort'=>'rank', 'dir'=>'ASC', 'remote'=>1,),
		'cookies' => $cookies,
	));
}


function kws_get_related($cookies, $project_id)
{
	global $kws_options;
	$minimum_links = $kws_options['related_links']? $kws_options['related_links'] : 1 ;
	return kws_request(array(
		'params' => array('start'=>0, 'limit'=>10000, 'project_id'=>$project_id, 'links'=>'<'.$minimum_links, 'sort'=>'rank', 'dir'=>'ASC', 'remote'=>1,),
		'cookie' => $cookies,
	));
}


function kws_update_database_inpage($keywords)
{
	global $wpdb, $kws_options;
	if (! $keywords) return;
	$wpdb->query("TRUNCATE TABLE `{$kws_options['inpage_table']}`");
	$sql = "INSERT INTO `{$kws_options['inpage_table']}` (keyword,url,post_id) VALUES ";
	$count = 0;
	foreach ($keywords AS $item)
	{
		$post_id = kws_url_post_id($item[1]);
		if (! $post_id) continue;
		$sql .= "('".$wpdb->escape($item[0])."','".$wpdb->escape($item[1])."', {$post_id}),";
		$count++;
	}
	$wpdb->query(substr($sql, 0, -1));
	$wpdb->query("DELETE FROM `{$kws_options['inpage_table']}` WHERE id > ".$count);
}


function kws_update_database_related($keywords)
{
	global $wpdb, $kws_options;
	if (! $keywords) return;
	$wpdb->query("TRUNCATE TABLE `{$kws_options['related_table']}`");
	$sql = "INSERT INTO `{$kws_options['related_table']}` (keyword,url,links,post_id) VALUES ";
	$count = 0;
	foreach ($keywords AS $item)
	{
		$post_id = kws_url_post_id($item[1]);
		if (! $post_id) continue;
		$sql .= "('".$wpdb->escape($item[0])."','".$wpdb->escape($item[1])."', ".intval($item[3])." , {$post_id}),";
		$count++;
	}
	$wpdb->query(substr($sql, 0, -1));
	$wpdb->query("DELETE FROM `{$kws_options['related_table']}` WHERE id > ".$count);
}


function kws_update_database($keywords)
{
	global $wpdb, $kws_options;
	if (! $keywords) return 0;
	$keywords_limit = isset($kws_options['keywords_limit']) ? $kws_options['keywords_limit']:1000;
	$wpdb->query("TRUNCATE TABLE `{$kws_options['keywords_table']}`");
	$sql = "INSERT INTO `{$kws_options['keywords_table']}` (keyword,url,exact_match) VALUES ";
	$count = 0;
	foreach ($keywords AS $item)
	{
		$post_id = kws_url_post_id($item[1]);
		if (! $post_id) continue;
		$count++;
		$sql .= "('".$wpdb->escape($item[0])."','".$wpdb->escape($item[1])."','".$wpdb->escape($item[2])."'),";
		if ($count == $keywords_limit) break;
	}
	$wpdb->query(substr($sql, 0, -1));
	$wpdb->query("DELETE FROM `{$kws_options['keywords_table']}` WHERE id > ".count($keywords));
	return $count;
}

function kws_check_login($username, $password, $return_result=true)
{
	if (! $username || ! $password) return false;
	if( !class_exists( 'WP_Http' ) ) include_once( ABSPATH . WPINC. '/class-http.php' );
	$request = new WP_Http;
	$body = array('username' => $username, 'password'=>$password, 'remote'=>1);
	$result = $request->request('http://www.keywordstrategy.org/login', array('method'=>'POST', 'body' => $body, 'timeout' => 20, ));
	return $result;
}


function kws_activation()
{
	global $wpdb;
	$default_options = array('username'=>'', 'password'=>'','update_freq'=>'daily', 'project'=>'', 'last_update' => false, 'wait_days' => 0, 'exact_match' => 140, 'total_keywords' => 0);
	$default_options['keywords_table'] = $wpdb->prefix.'kws_keywords';
	add_option('kws_options', $default_options, '', 'no');
	wp_schedule_event(time()+3600, 'hourly', 'kws_cron');
	$wpdb->query("CREATE TABLE IF NOT EXISTS `{$default_options['keywords_table']}`(`id` int(11) NOT NULL AUTO_INCREMENT, `keyword` varchar(250) NOT NULL, `exact_match` bigint(20) NULL, `url` varchar(250) NOT NULL, PRIMARY KEY (`id`) ) DEFAULT CHARSET=utf8");
}


function kws_fix_database()
{
	global $kws_options, $wpdb;
	// add exact_match column
	$sql = "SHOW COLUMNS FROM {$kws_options['keywords_table']}";
	$result = $wpdb->get_results($sql, ARRAY_A);
	$found_exact = false;
	foreach ($result AS $column)
	{
		if ($column['Field'] == 'exact_match')
		{
			$found_exact = true;
		}
	}
	if (! $found_exact)
	{
		$wpdb->query("ALTER TABLE {$kws_options['keywords_table']} ADD COLUMN `exact_match` bigint(20) NULL");
	}
	// add inpage table
	if (! isset($kws_options['inpage_table']))
	{
		$kws_options['inpage_table'] = $wpdb->prefix.'kws_inpage';
		$wpdb->query("CREATE TABLE IF NOT EXISTS `{$kws_options['inpage_table']}` (`id` int(11) NOT NULL AUTO_INCREMENT, `keyword` varchar(250) NOT NULL, `url` varchar(250) NOT NULL, `post_id` int(11) NOT NULL, PRIMARY KEY (`id`), KEY `url` (`url`) ) DEFAULT CHARSET=utf8");
		update_option('kws_options', $kws_options);
	}
	// add related table
	if (! isset($kws_options['related_table']))
	{
		$kws_options['related_table'] = $wpdb->prefix.'kws_related';
		$wpdb->query("CREATE TABLE IF NOT EXISTS `{$kws_options['related_table']}` (`id` int(11) NOT NULL AUTO_INCREMENT, `keyword` varchar(250) NOT NULL, `url` varchar(250) NOT NULL, `links` int(11) NOT NULL, `post_id` int(11) NOT NULL, PRIMARY KEY (`id`), KEY `url` (`url`) ) DEFAULT CHARSET=utf8");
		update_option('kws_options', $kws_options);
	}
	if (! isset($kws_options['not_appropriate_table']))
	{
		$kws_options['not_appropriate_table'] = $wpdb->prefix.'kws_not_appropriate';
		$wpdb->query("CREATE TABLE IF NOT EXISTS `{$kws_options['not_appropriate_table']}` (`id` int(11) NOT NULL AUTO_INCREMENT, `keyword` varchar(250) NOT NULL, `url` varchar(250) NOT NULL, PRIMARY KEY (`id`), KEY `keyword` (`keyword`) ) DEFAULT CHARSET=utf8");
		update_option('kws_options', $kws_options);
	}
}


function kws_deactivation()
{
	global $wpdb;
	$kws_options = get_option('kws_options');
	$wpdb->query("DROP TABLE `{$kws_options['keywords_table']}`");
	$wpdb->query("DROP TABLE `{$kws_options['related_table']}`");
	$wpdb->query("DROP TABLE `{$kws_options['inpage_table']}`");
	delete_option('kws_options');
	wp_clear_scheduled_hook('kws_cron');
}


function kws_check_banned_urls($check_urls)
{
	global $kws_options;
	if (! isset($kws_options['banned_urls'])) return false;
	foreach (explode("\n", $kws_options['banned_urls']) AS $banned_url)
	{
		$banned_url = trim($banned_url);
		if (! $banned_url) continue;
		$regexp = '/' . preg_quote($banned_url, '/') . '/';
		$regexp = str_replace('\*', '.*', $regexp);
		foreach ($check_urls AS $check_url)
		{
			if (preg_match($regexp, $check_url))
			{
				return true;
			}
		}
	}
	return false;
}


function kws_current_url()
{
	$url = $_SERVER['HTTPS'] == 'on'? 'https://' : 'http://';
	$url .= $_SERVER['SERVER_NAME'];
	if ($_SERVER['SERVER_PORT'] && $_SERVER['SERVER_PORT'] != '80')
	{
		$url .= ':'. $_SERVER['SERVER_PORT'];
	}
	$url .= $_SERVER['REQUEST_URI'];
	return $url;
}


add_filter('the_content', 'kws_replace_content', 100);
function kws_replace_content($content)
{
	global $kws_keywords, $wpdb, $post, $kws_found_keywords, $kws_urls, $kws_options;


	if ($kws_keywords === NULL)
	{
		kws_options();
		$kws_keywords = array();
		$order_by = $kws_options['links_priority'] == 'traffic'? 'exact_match DESC' : 'LENGTH(keyword) DESC';
		$sql = "SELECT keyword, url FROM {$kws_options['keywords_table']} ORDER BY {$order_by}";
		$kws_keywords = $wpdb->get_results($sql, ARRAY_A);
		if (! $kws_keywords) return $content;
	}
	if (isset($kws_options['linker_enabled']) && !$kws_options['linker_enabled']) return $content;

	$links_left = isset($kws_options['links_article']) ? $kws_options['links_article'] : 10;

	# don't modify if added less than 7 days ago
	if (time()-$kws_options['wait_days']*24*3600 < strtotime($post->post_date))
	{
		return $content;
	}

	$post_url = get_permalink();
	$banned_url = 'example.com/hola';

	if (kws_check_banned_urls(array($post_url, kws_current_url())))
	{
		return $content;
	}
	
	if (! $kws_found_keywords)
	{
		$kws_found_keywords = array();
	}
	
	if (! $kws_urls)
	{
		$kws_urls = array();
	}

	foreach ($kws_found_keywords AS $found_keyword => $found_data)
	{
		if (isset($found_data['posts'][$post->ID]))
		{
			$kws_found_keywords[$found_keyword]['count'] -= $kws_found_keywords[$found_keyword]['posts'][$post->ID];
			unset($kws_found_keywords[$found_keyword]['posts'][$post->ID]);
		}
	}

	$ignore_regexp = '/(?:\[caption.*?\[\/caption\]|<a .*?<\/\s*a>';
	if (! $kws_options['header_links'])
	{
		$ignore_regexp .= '|<h1.*?<\/\s*h1>|<h2.*?<\/\s*h2>|<h3.*?<\/\s*h3>|<h4.*?<\/\s*h4>|<h5.*?<\/\s*h5>|<h6.*?<\/\s*h6>';
	}
	$ignore_regexp .= '|<kwsignore.*?<\/\s*kwsignore>)/s';
	preg_match_all($ignore_regexp, $content, $matches);
	$captions = array();
	if ($matches && $matches[0])
	{
		foreach($matches[0] AS $caption_n => $caption_v)
		{
			$caption_title = ' kws_tmp_'.$caption_n.' ';
			$content = str_replace($caption_v, $caption_title, $content);
			$captions[$caption_title] = $caption_v;
		}
	}
		
	/** Loop through each keyphrase, looking for each one in the post */
	foreach ($kws_keywords as $keyphrase)
	{

           
		if ($keyphrase['url'] == $post_url || 
			 	$links_left <= 0 || 
			 	!$keyphrase['keyword'] || 
				(in_array($keyphrase['url'], $kws_urls) && $kws_urls[$keyphrase['keyword']] != $keyphrase['url'])
		) continue;

		if (stristr($content, $keyphrase['url'])) continue;

		if (stristr($keyphrase['keyword'], '&#')) {
			$seemsUTF8 = true;
			$keyphrase['keyword'] = encodeUtfEnt($keyphrase['keyword']);
		} else {
			$seemsUTF8 = false;
			$keyphrase['keyword'] = utf8_encode($keyphrase['keyword']);
		}

			
		/** Skip the rest if the keyphrase isn't even in the post */
		if (!stristr($content, htmlentities($keyphrase['keyword']))) {
			continue;
		}
			
		if ($kws_found_keywords[$keyphrase['keyword']]) {
			$kws_found_keywords[$keyphrase['keyword']]['count']++;
			$kws_found_keywords[$keyphrase['keyword']]['posts'][$post->ID]++;
		} else {
			$kws_found_keywords[$keyphrase['keyword']] = array('count' => 1, 'posts'=>array($post->ID => 1));
		}

		if ($kws_found_keywords[$keyphrase['keyword']]['count'] > KWS_MAX_LINKS) {
			continue;
		}			
			
		/** Build patterns and replacements for the regexp coming later */
		$escaped_keyphrase = preg_quote(htmlentities($keyphrase['keyword']), '/');
		if ($seemsUTF8) {
			// Unicode doesn't like the word boundry `\b` modifier, so can't use that
			$patterns[] = '~(?!((<.*?)|(<a.*?)))('. $escaped_keyphrase . ')(?!(([^<>]*?)>)|([^>]*?</a>))~si';
		} else {
				$patterns[] = '~(?!((<.*?)|(<a.*?)))(\b'. $escaped_keyphrase . '\b)(?!(([^<>]*?)>)|([^>]*?</a>))~si';
		}
		$kws_urls[$keyphrase['keyword']] = $keyphrase['url'];
		$replacements[] = "<a href=\"".htmlspecialchars($keyphrase['url']).'">$0</a>';
		$links_left--;
	}

	if (!empty($replacements)) {
		$content = preg_replace($patterns, $replacements, $content, 1);
	}
		
	foreach($captions AS $caption_title => $caption_value)
	{
		$content = str_replace($caption_title, $caption_value, $content);
	}

	return $content;
}


function kws_admin()
{
	set_time_limit(0);
	$update_frequencies = array('hourly', 'twicedaily', 'daily');
	$keywords_limits = array(100,200,500,1000,2000,5000,10000,20000);
	global $kws_options, $wpdb;
	kws_options();
	kws_fix_database();
	
	$action = false;
	if (isset($_REQUEST['kws_action']))
	{
		$action = $_REQUEST['kws_action'];
	}

	if ($action == 'change_freq')
	{
		$freq = $_GET['kws_freq'];
		if (! in_array($freq, $update_frequencies)) return;
		$kws_options['update_freq'] = $freq;
		update_option('kws_options', $kws_options);
		kws_js_redirect();
	}

	if ($action == 'update_now' || $action == 'update_now_inpage' || $action == 'update_now_related')
	{
		$url = false;
		if ($action == 'update_now_inpage')
		{
			$url = KWS_PLUGIN_URL.'&kws_action=inpage';
		}
		else if ($action == 'update_now_related')
		{
			$url = KWS_PLUGIN_URL.'&kws_action=related';
		}
		kws_update_keywords();
		kws_js_redirect($url);
	}

	if ($action == 'login')
	{
		$username = stripslashes($_POST['kws-username']);
		$password = stripslashes($_POST['kws-password']);
		$redirect_url = KWS_PLUGIN_URL;
		$result = kws_check_login($username, $password);
		if ($result['body'] == 'ok')
		{
			$cookies = $result['cookies'];
			$project = kws_get_project($cookies);
			if (is_numeric($project))
			{
				$kws_options['project'] = $project;
			}
			$kws_options['update_error'] = false;
			$kws_options['username'] = $username;
			$kws_options['password'] = $password;
			update_option('kws_options', $kws_options);
		}
		else
		{
			$redirect_url .= '&kws_login_error='.urlencode("wrong username or password").'&kws_username='.urlencode($username);
		}
		kws_js_redirect($redirect_url);
	}

	if ($action == 'save_options')
	{
		$kws_options['exact_match'] = intval($_POST['kws_exact_match']);
		$kws_options['wait_days'] = intval($_POST['kws_wait_days']);
		$kws_options['links_article'] = intval($_POST['kws_links_article']);
		$kws_options['links_priority'] = strval($_POST['kws_links_priority']);
		$kws_options['tracker_enabled'] = intval($_POST['kws_tracker_enabled']);
		$kws_options['header_links'] = intval($_POST['kws_header_links']);
		$kws_options['linker_enabled'] = intval($_POST['kws_linker_enabled']);
		$kws_options['keywords_limit'] = intval($_POST['kws_keywords_limit']);
		$kws_options['banned_urls'] = stripslashes(strval($_POST['kws_banned_urls']));
		update_option('kws_options', $kws_options);
		kws_js_redirect();
	}

	if ($action == 'related')
	{
		$page = intval($_REQUEST['paged']);
		if (!$page) $page = 1;
		$links_limit = ($kws_options['related_links']? $kws_options['related_links'] : 1);
		$where = " WHERE links<{$links_limit} ";
		if ($_REQUEST['search'])
		{
			$search = trim(stripslashes($_REQUEST['search']));
			$search = str_replace('%', '', $search);
			$search = str_replace('_', '', $search);
			$search = $wpdb->escape('%'.$search.'%');
			$where .= " AND (keyword LIKE '{$search}' OR url LIKE '{$search}')";
		}
		$order_by = "";
		if ($_REQUEST['sort'])
		{
			if ($_REQUEST['sort'] == 'links')
			{
				$_REQUEST['dir'] = $_REQUEST['dir'] == 'asc' ? 'desc' : 'asc';
			}
			$order_by = " ORDER BY {$_REQUEST['sort']} {$_REQUEST['dir']} ";
		}
		$related = $wpdb->get_results("SELECT * FROM `{$kws_options['related_table']}` {$where} {$order_by} LIMIT ".(($page-1) * 10).", 10", ARRAY_A);
		$total_keywords = $wpdb->get_var("SELECT COUNT(*) FROM `{$kws_options['related_table']}` {$where}");

		$page_args = array('total_items' => $total_keywords, 'per_page' => 10, 'current' => $page);
		include WP_PLUGIN_DIR.'/keyword-strategy-internal-links/related.tpl.php';
	}

	if ($action == 'inpage')
	{
		$page = intval($_REQUEST['paged']);
		if (!$page) $page = 1;
		$where = "";
		if ($_REQUEST['search'])
		{
			$search = trim(stripslashes($_REQUEST['search']));
			$search = str_replace('%', '', $search);
			$search = str_replace('_', '', $search);
			$search = $wpdb->escape('%'.$search.'%');
			$where = " WHERE keyword LIKE '{$search}' OR url LIKE '{$search}'";
		}
		$order_by = "";
		if ($_REQUEST['sort'])
		{
			$order_by = " ORDER BY {$_REQUEST['sort']} {$_REQUEST['dir']} ";
		}
		$inpage = $wpdb->get_results("SELECT * FROM `{$kws_options['inpage_table']}` {$where} {$order_by} LIMIT ".(($page-1) * 10).", 10", ARRAY_A);
		$inpage_total_keywords = $wpdb->get_var("SELECT COUNT(*) FROM `{$kws_options['inpage_table']}`{$where}");

		$page_args = array('total_items' => $inpage_total_keywords, 'per_page' => 10, 'current' => $page);
		include WP_PLUGIN_DIR.'/keyword-strategy-internal-links/inpage.tpl.php';
	}

	if ($action == 'inpage_form')
	{
		if (isset($_REQUEST['apply2']))
		{
			$_REQUEST['inpage_action'] = $_REQUEST['inpage_action2'];
		}
		if (is_array($_REQUEST['keyword']) && $_REQUEST['keyword'] && $_REQUEST['inpage_action'] != 'none')
		{
			$keywords_ids = array_map('intval', $_REQUEST['keyword']);
			$sql = "SELECT keyword FROM `{$kws_options['inpage_table']}` WHERE id IN (".join(',', $keywords_ids).")";
			$keywords = $wpdb->get_col($sql);
			if ($keywords)
			{
				if ($_REQUEST['inpage_action'] == 'blacklist')
				{
					kws_blacklist($keywords);
				}
				else
				{
					kws_detach($keywords);
				}
			}
			kws_delete_keywords($keywords);
		}
		$url = KWS_PLUGIN_URL.'&kws_action=inpage';
		if ($_REQUEST['paged'])
		{
			$url .= "&paged=" . $_REQUEST['paged'];
		}
		kws_js_redirect($url);
	}

	if ($action == 'related_links')
	{
		$links = intval($_REQUEST['kws_links']);
		if ($links <= 0) $links = 1;
		$kws_options['related_links'] = $links;
		update_option('kws_options', $kws_options);
		kws_update_keywords();
		kws_js_redirect(KWS_PLUGIN_URL.'&kws_action=related');
	}

	if ($action == 'related_form')
	{
		if (isset($_REQUEST['apply2']))
		{
			$_REQUEST['related_action'] = $_REQUEST['related_action2'];
		}
		if (is_array($_REQUEST['keyword']) && $_REQUEST['keyword'] && $_REQUEST['related_action'] != 'none')
		{
			$keywords_ids = array_map('intval', $_REQUEST['keyword']);
			$sql = "SELECT keyword FROM `{$kws_options['related_table']}` WHERE id IN (".join(',', $keywords_ids).")";
			$keywords = $wpdb->get_col($sql);
			if ($keywords)
			{
				if ($_REQUEST['related_action'] == 'blacklist')
				{
					kws_blacklist($keywords);
				}
				else
				{
					kws_detach($keywords);
				}
			}
			kws_delete_keywords($keywords);
		}
		$url = KWS_PLUGIN_URL.'&kws_action=related';
		if ($_REQUEST['paged'])
		{
			$url .= "&paged=" . $_REQUEST['paged'];
		}
		kws_js_redirect($url);
	}

	if (! $action)
	{
		include WP_PLUGIN_DIR.'/keyword-strategy-internal-links/settings.tpl.php';
	}
}


function kws_related_urls()
{
	global $kws_options, $wpdb;
	kws_options();
	$keyword = stripslashes($_REQUEST['kws_keyword']);
	$keyword_url = $wpdb->get_var($wpdb->prepare("SELECT url FROM `{$kws_options['related_table']}` WHERE keyword = %s", $keyword));
	$keyword_links = $wpdb->get_var($wpdb->prepare("SELECT links FROM `{$kws_options['related_table']}` WHERE keyword = %s", $keyword));
	if ($_GET['not_appropriate'])
	{
		$not_appropriate = stripslashes($_GET['not_appropriate']);
		$sql = $wpdb->prepare("DELETE FROM `{$kws_options['not_appropriate_table']}` WHERE keyword=%s AND url=%s", $keyword, $not_appropriate);
		$wpdb->query($sql);
		$wpdb->insert($kws_options['not_appropriate_table'], array('keyword' => $keyword, 'url' => $not_appropriate));
	}
	$google_urls = kws_related_google($keyword, $keyword_url);
	$urls = array();
	foreach ($google_urls as $google_url)
	{
		if (kws_url_post_id($google_url))
		{
			$urls[] = $google_url;
		}
	}
	$result = kws_request(array('url' => 'keywords/related', 'params' => array('keyword' => $keyword, 'urls' => $urls)));
	$urls = array();
	if ($result)
	{
		foreach ($result AS $item)
		{
			list($url, $url_links) = $item;
			$found = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$kws_options['not_appropriate_table']} WHERE keyword=%s AND url=%s", $keyword, $url));
			if ($found || $keyword_url == $url) continue;
			$post_id = kws_url_post_id($url);
			if (! $post_id) continue;
			$urls[] = array('url' => $url, 'url_links' => $url_links, 'post_id' => $post_id);
		}
	}
	include WP_PLUGIN_DIR.'/keyword-strategy-internal-links/related_ajax.tpl.php';
	die();
}

function kws_url_post_id($url)
{
	global $kws_url_post_ids, $wpdb;
	if (! $kws_url_post_ids)
	{
		$kws_url_post_ids = array();
	}
	if (isset($kws_url_post_ids[$url]))
	{
		return $kws_url_post_ids[$url];
	}
	$url = kws_ireplace(site_url(), site_url(), $url);
	$post_id = url_to_postid($url);
	if (! $post_id || ! $wpdb->query("SELECT id FROM {$wpdb->posts} WHERE post_type!='attachment' AND id={$post_id}"))
	{
		$post_id = false;
	}
	$kws_url_post_ids[$url] = $post_id;
	return $post_id;
}


function kws_delete_keywords($keywords)
{
	global $kws_options, $wpdb;
	if (! $keywords) return;
	$where = "keyword IN (";
	foreach ($keywords AS $keyword)
	{
		$where .= '"'.$wpdb->escape($keyword).'",';
	}
	$where = substr($where,0,-1).')';
	$wpdb->query("DELETE FROM `{$kws_options['keywords_table']}` WHERE {$where}");
	$wpdb->query("DELETE FROM `{$kws_options['inpage_table']}` WHERE {$where}");
	$wpdb->query("DELETE FROM `{$kws_options['related_table']}` WHERE {$where}");
}

add_action('wp_print_scripts', 'kws_tracker');
function kws_tracker()
{
	global $kws_options;
	kws_options();
	if ((isset($kws_options['tracker_enabled']) && !$kws_options['tracker_enabled']) || ! $kws_options['project'])
	{
		return;
	}
	echo "<script>
		__kws = {$kws_options['project']};
		(function() {
			var kws = document.createElement('script'); kws.async = true;
			kws.src = ('https:' == document.location.protocol ? 'https://d2qi79k7w4ifvj.cloudfront.net' : 'http://dl.keywordstrategy.org') + '/track.js';
			var s = document.getElementsByTagName('script')[0]; s.parentNode.insertBefore(kws, s);
		})();
	</script>";
}

function kws_plugin_action_links( $links, $file ) {
	if ( $file == plugin_basename( dirname(__FILE__).'/keyword-strategy-internal-links.php' ) ) {
		$links[] = '<a href="'.KWS_PLUGIN_URL.'">'.__('Settings').'</a>';
	}

	return $links;
}

function kws_edit_post($post_id)
{
	global $kws_options, $wpdb;
	kws_options();
	if (! $kws_options['project']) return;
	$url = get_permalink($post_id);
	if (! $url) return;
	$post = get_post($post_id, 'ARRAY_A');
	if (! $post) return;
	$title = array();
	$content = array();
	$remove_ids = array();
	foreach ($wpdb->get_results("SELECT keyword, id FROM `{$kws_options['inpage_table']}` WHERE url='".addslashes($url)."'", ARRAY_A) AS $item)
	{
		$regex = '/'.preg_replace('/\s+/', '[\'"():;!?&*%#^=+ .,_-]+', $item['keyword']).'/i';
		if (preg_match($regex, $post['post_title']))
		{
			// keyword located in the title of the page
			$title[] = $item['keyword'];
			$remove_ids[] = $item['id'];
		}
		elseif (preg_match($regex, $post['post_content']))
		{
			// keyword is in the body
			$content[] = $item['keyword'];
			$remove_ids[] = $item['id'];
		}
	}
	if ($remove_ids)
	{
		$wpdb->query("DELETE FROM `{$kws_options['inpage_table']}` WHERE id IN (".join(',', $remove_ids).")");
		kws_update_inpage($title, $content);
	}
	kws_edit_post_related($post, $url);
}

function kws_edit_post_related($post, $post_url)
{
	global $kws_options, $wpdb;
	return; //TODO: redo
	$update_keywords = array();
	$sql = $wpdb->prepare("SELECT keyword, id FROM `{$kws_options['related_table']}` WHERE url!=%s", $post_url);
	foreach ($wpdb->get_results($sql, ARRAY_A) AS $item)
	{
		$regex = '/'.preg_replace('/\s+/', '[\'"():;!?&*%#^=+ .,_-]+', $item['keyword']).'/i';
		if (preg_match($regex, $post['post_content'].' '.$post['post_title']))
		{
			$update_keywords[] = $item['keyword'];
			$wpdb->query($wpdb->prepare("UPDATE `{$kws_options['related_table']}` SET links=links+1 WHERE keyword=%s", $item['keyword']));
		}
	}
	if ($update_keywords)
	{
		kws_request(array('url' => 'keywords/add_link', 'params' => array('keywords' => $update_keywords)));
	}
}

add_filter( 'plugin_action_links', 'kws_plugin_action_links', 10, 2 );

function kws_js_redirect($url='')
{
	if (! $url)
	{
		$url = KWS_PLUGIN_URL;
	}
	echo '<script>window.location = "'.$url.'";</script>';
	die();
}

function kws_pagination( $which, $args ) {

	extract( $args );

	$total_pages = ceil($total_items/$per_page);
	$output = '<span class="displaying-num">' . sprintf( _n( '1 item', '%s items', $total_items ), number_format_i18n( $total_items ) ) . '</span>';

	$current_url = ( is_ssl() ? 'https://' : 'http://' ) . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];

	$current_url = remove_query_arg( array( 'hotkeys_highlight_last', 'hotkeys_highlight_first' ), $current_url );

	$page_links = array();

	$disable_first = $disable_last = '';
	if ( $current == 1 )
		$disable_first = ' disabled';
	if ( $current == $total_pages )
		$disable_last = ' disabled';

	$page_links[] = sprintf( "<a class='%s' title='%s' href='%s'>%s</a>",
		'first-page' . $disable_first,
		esc_attr__( 'Go to the first page' ),
		esc_url( remove_query_arg( 'paged', $current_url ) ),
		'&laquo;'
	);

	$page_links[] = sprintf( "<a class='%s' title='%s' href='%s'>%s</a>",
		'prev-page' . $disable_first,
		esc_attr__( 'Go to the previous page' ),
		esc_url( add_query_arg( 'paged', max( 1, $current-1 ), $current_url ) ),
		'&lsaquo;'
	);

	if ( 'bottom' == $which )
		$html_current_page = $current;
	else
		$html_current_page = sprintf( "<input class='current-page' title='%s' type='text' name='%s' value='%s' size='%d' />",
			esc_attr__( 'Current page' ),
			esc_attr( 'paged' ),
			$current,
			strlen( $total_pages )
		);

	$html_total_pages = sprintf( "<span class='total-pages'>%s</span>", number_format_i18n( $total_pages ) );
	$page_links[] = '<span class="paging-input">' . sprintf( _x( '%1$s of %2$s', 'paging' ), $html_current_page, $html_total_pages ) . '</span>';

	$page_links[] = sprintf( "<a class='%s' title='%s' href='%s'>%s</a>",
		'next-page' . $disable_last,
		esc_attr__( 'Go to the next page' ),
		esc_url( add_query_arg( 'paged', min( $total_pages, $current+1 ), $current_url ) ),
		'&rsaquo;'
	);

	$page_links[] = sprintf( "<a class='%s' title='%s' href='%s'>%s</a>",
		'last-page' . $disable_last,
		esc_attr__( 'Go to the last page' ),
		esc_url( add_query_arg( 'paged', $total_pages, $current_url ) ),
		'&raquo;'
	);

	$output .= "\n" . join( "\n", $page_links );

	$page_class = $total_pages < 2 ? ' one-page' : '';
	return "<div class='tablenav-pages{$page_class}'>$output</div>";
}

function kws_ireplace($needle, $replacement, $haystack) {
   $i = 0;
   while (($pos = strpos(strtolower($haystack),strtolower($needle), $i)) !== false) {
      $haystack = substr($haystack, 0, $pos) . $replacement.substr($haystack, $pos+strlen($needle));
      $i=$pos+strlen($replacement);
   }
   return $haystack;
}



