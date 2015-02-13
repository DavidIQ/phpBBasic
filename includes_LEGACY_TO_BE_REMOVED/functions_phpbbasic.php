<?php
/**
* @package phpBBasic
* @copyright (c) 2010 DavidIQ.com
* @license http://opensource.org/licenses/gpl-license.php GNU Public License
*/

/**
* @ignore
*/
if (!defined('IN_PHPBB'))
{
	exit;
}

/**
* Display the basic forum's topics
* Pretty much a copy of base viewforum.php with quite a few modifications
*/
function display_phpbbasic_forum_topics()
{
	global $db, $auth, $user, $template, $cache;
	global $phpbb_root_path, $phpEx, $config;
	
	// Start initial var setup
	$mark_read	= request_var('mark', '');
	$start		= request_var('start', 0);
	$phpbbasic_forumid = (int) $config['phpbbasic_forumid'];
	$forum_id	= request_var('f', $phpbbasic_forumid);

	$default_sort_days	= (!empty($user->data['user_topic_show_days'])) ? $user->data['user_topic_show_days'] : 0;
	$default_sort_key	= (!empty($user->data['user_topic_sortby_type'])) ? $user->data['user_topic_sortby_type'] : 't';
	$default_sort_dir	= (!empty($user->data['user_topic_sortby_dir'])) ? $user->data['user_topic_sortby_dir'] : 'd';

	$sort_days	= request_var('st', $default_sort_days);
	$sort_key	= request_var('sk', $default_sort_key);
	$sort_dir	= request_var('sd', $default_sort_dir);

	$sql_from = FORUMS_TABLE . ' f';
	$lastread_select = '';

	// Grab appropriate forum data
	if ($config['load_db_lastread'] && $user->data['is_registered'])
	{
		$sql_from .= ' LEFT JOIN ' . FORUMS_TRACK_TABLE . ' ft ON (ft.user_id = ' . $user->data['user_id'] . '
			AND ft.forum_id = f.forum_id)';
		$lastread_select .= ', ft.mark_time';
	}

	if ($user->data['is_registered'])
	{
		$sql_from .= ' LEFT JOIN ' . FORUMS_WATCH_TABLE . ' fw ON (fw.forum_id = f.forum_id AND fw.user_id = ' . $user->data['user_id'] . ')';
		$lastread_select .= ', fw.notify_status';
	}

	$sql = "SELECT f.* $lastread_select
		FROM $sql_from
		WHERE f.forum_id = $forum_id" . (($forum_id != $phpbbasic_forumid) ? '' : " OR f.parent_id <> $phpbbasic_forumid");
	$result = $db->sql_query($sql);
	$basic_forum_data = $db->sql_fetchrow($result);
	$db->sql_freeresult($result);

	if (!$basic_forum_data)
	{
		trigger_error('NO_FORUM');
	}

	// Configure style, language, etc.
	$user->setup('viewforum', $basic_forum_data['forum_style']);

	// Redirect to login upon emailed notification links
	if (isset($_GET['e']) && !$user->data['is_registered'])
	{
		login_box('', $user->lang['LOGIN_NOTIFY_FORUM']);
	}

	// Permissions check
	if (!$auth->acl_gets('f_list', 'f_read', $forum_id))
	{
		if ($user->data['user_id'] != ANONYMOUS)
		{
			trigger_error('SORRY_AUTH_READ');
		}

		login_box('', $user->lang['LOGIN_VIEWFORUM']);
	}

	// Forum is passworded ... check whether access has been granted to this
	// user this session, if not show login box
	if ($basic_forum_data['forum_password'])
	{
		login_forum_box($basic_forum_data);
	}

	// Is this forum a link? ... User got here either because the
	// number of clicks is being tracked or they guessed the id
	if ($basic_forum_data['forum_type'] == FORUM_LINK && $basic_forum_data['forum_link'])
	{
		// Does it have click tracking enabled?
		if ($basic_forum_data['forum_flags'] & FORUM_FLAG_LINK_TRACK)
		{
			$sql = 'UPDATE ' . FORUMS_TABLE . '
				SET forum_posts = forum_posts + 1
				WHERE forum_id = ' . $forum_id;
			$db->sql_query($sql);
		}

		// We redirect to the url. The third parameter indicates that external redirects are allowed.
		redirect($basic_forum_data['forum_link'], false, true);
		return;
	}

	// Forum Rules
	if ($auth->acl_get('f_read', $forum_id))
	{
		generate_forum_rules($basic_forum_data);
	}

	if ($forum_id == $phpbbasic_forumid)
	{
		$sql = 'SELECT f.*
					FROM ' . FORUMS_TABLE . ' f
				WHERE f.parent_id = ' . $phpbbasic_forumid . ' 
				ORDER BY f.left_id';
		$result = $db->sql_query($sql);
		
		$has_subforum = false;
		
		while ($row = $db->sql_fetchrow($result))
		{
			$has_subforum = true;
			$forum_unread = (isset($basic_forum_data[$forum_id]) && $row['orig_forum_last_post_time'] > $basic_forum_data[$forum_id]) ? true : false;
			// Which folder should we display?
			$folder_image = $folder_alt = '';
			if ($row['forum_status'] == ITEM_LOCKED)
			{
				$folder_image = ($forum_unread) ? 'forum_unread_locked' : 'forum_read_locked';
				$folder_alt = 'FORUM_LOCKED';
			}
			else
			{
				$folder_alt = ($forum_unread) ? 'NEW_POSTS' : 'NO_NEW_POSTS';
				switch ($row['forum_type'])
				{
					case FORUM_POST:
						$folder_image = ($forum_unread) ? 'forum_unread' : 'forum_read';
					break;

					case FORUM_LINK:
						$folder_image = 'forum_link';
					break;
				}
			}
			$post_click_count = ($row['forum_type'] != FORUM_LINK || $row['forum_flags'] & FORUM_FLAG_LINK_TRACK) ? $row['forum_posts'] : '';
			// Create last post link information, if appropriate
			if ($row['forum_last_post_id'])
			{
				$last_post_subject = $row['forum_last_post_subject'];
				$last_post_time = $user->format_date($row['forum_last_post_time']);
				$last_post_url = append_sid("{$phpbb_root_path}viewtopic.$phpEx", 'f=' . $forum_id . '&amp;p=' . $row['forum_last_post_id']) . '#p' . $row['forum_last_post_id'];
			}
			else
			{
				$last_post_subject = $last_post_time = $last_post_url = '';
			}
			//We're not going to use these
			$l_moderator = $moderators_list = '';
			$l_post_click_count = ($row['forum_type'] == FORUM_LINK) ? 'CLICKS' : 'POSTS';
			if ($row['forum_type'] != FORUM_LINK)
			{
				$u_viewforum = append_sid("{$phpbb_root_path}index.$phpEx", 'f=' . $row['forum_id']);
			}
			else
			{
				// If the forum is a link and we count redirects we need to visit it
				// If the forum is having a password or no read access we do not expose the link, but instead handle it in viewforum
				if (($row['forum_flags'] & FORUM_FLAG_LINK_TRACK) || $row['forum_password'] || !$auth->acl_get('f_read', $forum_id))
				{
					$u_viewforum = append_sid("{$phpbb_root_path}index.$phpEx", 'f=' . $row['forum_id']);
				}
				else
				{
					$u_viewforum = $row['forum_link'];
				}
			}
			// Count the difference of real to public topics, so we can display an information to moderators
			$row['forum_id_unapproved_topics'] = ($auth->acl_get('m_approve', $forum_id) && ($row['forum_topics_real'] != $row['forum_topics'])) ? $forum_id : 0;
			
			$template->assign_block_vars('forumrow', array(
				'S_IS_CAT'			=> false,
				'S_NO_CAT'			=> false,
				'S_IS_LINK'			=> ($row['forum_type'] == FORUM_LINK) ? true : false,
				'S_UNREAD_FORUM'	=> $forum_unread,
				'S_LOCKED_FORUM'	=> ($row['forum_status'] == ITEM_LOCKED) ? true : false,
				'S_SUBFORUMS'		=> false,
				'S_FEED_ENABLED'	=> ($config['feed_forum'] && !phpbb_optionget(FORUM_OPTION_FEED_EXCLUDE, $row['forum_options'])) ? true : false,

				'FORUM_ID'				=> $row['forum_id'],
				'FORUM_NAME'			=> $row['forum_name'],
				'FORUM_DESC'			=> generate_text_for_display($row['forum_desc'], $row['forum_desc_uid'], $row['forum_desc_bitfield'], $row['forum_desc_options']),
				'TOPICS'				=> $row['forum_topics'],
				$l_post_click_count		=> $post_click_count,
				'FORUM_FOLDER_IMG'		=> $user->img($folder_image, $folder_alt),
				'FORUM_FOLDER_IMG_SRC'	=> $user->img($folder_image, $folder_alt, false, '', 'src'),
				'FORUM_FOLDER_IMG_ALT'	=> isset($user->lang[$folder_alt]) ? $user->lang[$folder_alt] : '',
				'FORUM_IMAGE'			=> ($row['forum_image']) ? '<img src="' . $phpbb_root_path . $row['forum_image'] . '" alt="' . $user->lang[$folder_alt] . '" />' : '',
				'FORUM_IMAGE_SRC'		=> ($row['forum_image']) ? $phpbb_root_path . $row['forum_image'] : '',
				'LAST_POST_SUBJECT'		=> censor_text($last_post_subject),
				'LAST_POST_TIME'		=> $last_post_time,
				'LAST_POSTER'			=> get_username_string('username', $row['forum_last_poster_id'], $row['forum_last_poster_name'], $row['forum_last_poster_colour']),
				'LAST_POSTER_COLOUR'	=> get_username_string('colour', $row['forum_last_poster_id'], $row['forum_last_poster_name'], $row['forum_last_poster_colour']),
				'LAST_POSTER_FULL'		=> get_username_string('full', $row['forum_last_poster_id'], $row['forum_last_poster_name'], $row['forum_last_poster_colour']),
				'MODERATORS'			=> $moderators_list,
				'SUBFORUMS'				=> false,

				'L_SUBFORUM_STR'		=> false,
				'L_FORUM_FOLDER_ALT'	=> $folder_alt,
				'L_MODERATOR_STR'		=> $l_moderator,

				'U_UNAPPROVED_TOPICS'	=> ($row['forum_id_unapproved_topics']) ? append_sid("{$phpbb_root_path}mcp.$phpEx", 'i=queue&amp;mode=unapproved_topics&amp;f=' . $row['forum_id_unapproved_topics']) : '',
				'U_VIEWFORUM'		=> $u_viewforum,
				'U_LAST_POSTER'		=> get_username_string('profile', $row['forum_last_poster_id'], $row['forum_last_poster_name'], $row['forum_last_poster_colour']),
				'U_LAST_POST'		=> $last_post_url)
			);
		}
		$db->sql_freeresult($result);
		
		$template->assign_vars(array(
			'S_HAS_SUBFORUM'	=> $has_subforum,
			'U_VIEW_FORUM'			=> append_sid("{$phpbb_root_path}index.$phpEx", "start=$start"),
		));
	}
	
	// Initialize array
	$moderators = array();
	get_moderators($moderators, $forum_id);

	$template->set_filenames(array(
		'body' => 'viewforum_body.html')
	);
	
	make_jumpbox(append_sid("{$phpbb_root_path}index.$phpEx"), $forum_id);

	// Ok, if someone has only list-access, we only display the forum list.
	// We also make this circumstance available to the template in case we want to display a notice. ;)
	if (!$auth->acl_get('f_read', $forum_id))
	{
		$template->assign_vars(array(
			'S_NO_READ_ACCESS'		=> true,
		));

		page_footer();
	}

	// Handle marking posts
	if ($mark_read == 'all')
	{
		$token = request_var('hash', '');
		if (check_link_hash($token, 'global'))
		{
			markread('all');
		}
		$redirect_url = append_sid("{$phpbb_root_path}index.$phpEx");
		meta_refresh(3, $redirect_url);

		trigger_error($user->lang['TOPICS_MARKED'] . '<br /><br />' . sprintf($user->lang['RETURN_FORUM'], '<a href="' . $redirect_url . '">', '</a>'));
	}

	// Is a forum specific topic count required?
	if ($basic_forum_data['forum_topics_per_page'])
	{
		$config['topics_per_page'] = $basic_forum_data['forum_topics_per_page'];
	}

	// Do the forum Prune thang - cron type job ...
	if ($basic_forum_data['prune_next'] < time() && $basic_forum_data['enable_prune'])
	{
		$template->assign_var('RUN_CRON_TASK', '<img src="' . append_sid($phpbb_root_path . 'cron.' . $phpEx, 'cron_type=prune_forum&amp;f=' . $forum_id) . '" alt="cron" width="1" height="1" />');
	}

	// Forum rules and subscription info
	$s_watching_forum = array(
		'link'			=> '',
		'title'			=> '',
		'is_watching'	=> false,
	);

	if (($config['email_enable'] || $config['jab_enable']) && $config['allow_forum_notify'] && $auth->acl_get('f_subscribe', $forum_id))
	{
		$notify_status = (isset($basic_forum_data['notify_status'])) ? $basic_forum_data['notify_status'] : NULL;
		watch_topic_forum('forum', $s_watching_forum, $user->data['user_id'], $forum_id, 0, $notify_status);
	}

	$s_forum_rules = '';
	gen_forum_auth_level('forum', $forum_id, $basic_forum_data['forum_status']);

	// Topic ordering options
	$limit_days = array(0 => $user->lang['ALL_TOPICS'], 1 => $user->lang['1_DAY'], 7 => $user->lang['7_DAYS'], 14 => $user->lang['2_WEEKS'], 30 => $user->lang['1_MONTH'], 90 => $user->lang['3_MONTHS'], 180 => $user->lang['6_MONTHS'], 365 => $user->lang['1_YEAR']);

	$sort_by_text = array('a' => $user->lang['AUTHOR'], 't' => $user->lang['POST_TIME'], 'r' => $user->lang['REPLIES'], 's' => $user->lang['SUBJECT'], 'v' => $user->lang['VIEWS']);
	$sort_by_sql = array('a' => 't.topic_first_poster_name', 't' => 't.topic_last_post_time', 'r' => 't.topic_replies', 's' => 't.topic_title', 'v' => 't.topic_views');

	$s_limit_days = $s_sort_key = $s_sort_dir = $u_sort_param = '';
	gen_sort_selects($limit_days, $sort_by_text, $sort_days, $sort_key, $sort_dir, $s_limit_days, $s_sort_key, $s_sort_dir, $u_sort_param, $default_sort_days, $default_sort_key, $default_sort_dir);

	// Limit topics to certain time frame, obtain correct topic count
	// global announcements must not be counted, normal announcements have to
	// be counted, as forum_topics(_real) includes them
	if ($sort_days)
	{
		$min_post_time = time() - ($sort_days * 86400);

		$sql = 'SELECT COUNT(t.topic_id) AS num_topics
			FROM ' . TOPICS_TABLE . ' t
			LEFT JOIN ' . FORUMS_TABLE . " f ON f.forum_id = t.forum_id
			WHERE
				(t.forum_id = $forum_id OR f.parent_id" . (($forum_id != $phpbbasic_forumid) ? '' : " OR f.parent_id <> " . $phpbbasic_forumid) . ")
				AND ((t.topic_type <> " . POST_GLOBAL . " AND t.topic_last_post_time >= $min_post_time)
					OR t.topic_type = " . POST_ANNOUNCE . ")
			" . (($auth->acl_get('m_approve', $forum_id)) ? '' : 'AND t.topic_approved = 1');
		$result = $db->sql_query($sql);
		$topics_count = (int) $db->sql_fetchfield('num_topics');
		$db->sql_freeresult($result);

		if (isset($_POST['sort']))
		{
			$start = 0;
		}
		$sql_limit_time = "AND t.topic_last_post_time >= $min_post_time";

		// Make sure we have information about day selection ready
		$template->assign_var('S_SORT_DAYS', true);
	}
	else
	{
		//Let's grab the total count...we're going to need that
		$sql = 'SELECT COUNT(t.topic_id) AS num_topics
			FROM ' . TOPICS_TABLE . ' t
			LEFT JOIN ' . FORUMS_TABLE . " f ON f.forum_id = t.forum_id
			WHERE (t.forum_id = $forum_id" . (($forum_id != $phpbbasic_forumid) ? '' : " OR f.parent_id <> " . $phpbbasic_forumid) . ')' . (($auth->acl_get('m_approve', $forum_id)) ? '' : ' AND t.topic_approved = 1');
		$result = $db->sql_query($sql);
		$topics_count = (int) $db->sql_fetchfield('num_topics');
		$db->sql_freeresult($result);
		$sql_limit_time = '';
	}

	// Make sure $start is set to the last page if it exceeds the amount
	if ($start < 0 || $start > $topics_count)
	{
		$start = ($start < 0) ? 0 : floor(($topics_count - 1) / $config['topics_per_page']) * $config['topics_per_page'];
	}

	// Basic pagewide vars
	$post_alt = ($basic_forum_data['forum_status'] == ITEM_LOCKED) ? $user->lang['FORUM_LOCKED'] : $user->lang['POST_NEW_TOPIC'];

	$template->assign_vars(array(
		'MODERATORS'	=> (!empty($moderators[$forum_id])) ? implode(', ', $moderators[$forum_id]) : '',

		'POST_IMG'					=> ($basic_forum_data['forum_status'] == ITEM_LOCKED) ? $user->img('button_topic_locked', $post_alt) : $user->img('button_topic_new', $post_alt),
		'NEWEST_POST_IMG'			=> $user->img('icon_topic_newest', 'VIEW_NEWEST_POST'),
		'LAST_POST_IMG'				=> $user->img('icon_topic_latest', 'VIEW_LATEST_POST'),
		'FOLDER_IMG'				=> $user->img('topic_read', 'NO_NEW_POSTS'),
		'FOLDER_NEW_IMG'			=> $user->img('topic_unread', 'NEW_POSTS'),
		'FOLDER_HOT_IMG'			=> $user->img('topic_read_hot', 'NO_NEW_POSTS_HOT'),
		'FOLDER_HOT_NEW_IMG'		=> $user->img('topic_unread_hot', 'NEW_POSTS_HOT'),
		'FOLDER_LOCKED_IMG'			=> $user->img('topic_read_locked', 'NO_NEW_POSTS_LOCKED'),
		'FOLDER_LOCKED_NEW_IMG'		=> $user->img('topic_unread_locked', 'NEW_POSTS_LOCKED'),
		'FOLDER_STICKY_IMG'			=> $user->img('sticky_read', 'POST_STICKY'),
		'FOLDER_STICKY_NEW_IMG'		=> $user->img('sticky_unread', 'POST_STICKY'),
		'FOLDER_ANNOUNCE_IMG'		=> $user->img('announce_read', 'POST_ANNOUNCEMENT'),
		'FOLDER_ANNOUNCE_NEW_IMG'	=> $user->img('announce_unread', 'POST_ANNOUNCEMENT'),
		'FOLDER_MOVED_IMG'			=> $user->img('topic_moved', 'TOPIC_MOVED'),
		'REPORTED_IMG'				=> $user->img('icon_topic_reported', 'TOPIC_REPORTED'),
		'UNAPPROVED_IMG'			=> $user->img('icon_topic_unapproved', 'TOPIC_UNAPPROVED'),
		'GOTO_PAGE_IMG'				=> $user->img('icon_post_target', 'GOTO_PAGE'),

		'L_NO_TOPICS' 			=> ($basic_forum_data['forum_status'] == ITEM_LOCKED) ? $user->lang['POST_FORUM_LOCKED'] : $user->lang['NO_TOPICS'],

		'S_DISPLAY_POST_INFO'	=> ($auth->acl_get('f_post', $forum_id) || $user->data['user_id'] == ANONYMOUS) ? true : false,

		'S_IS_POSTABLE'			=> true,
		'S_USER_CAN_POST'		=> ($auth->acl_get('f_post', $forum_id)) ? true : false,
		'S_DISPLAY_ACTIVE'		=> false,
		'S_SELECT_SORT_DIR'		=> $s_sort_dir,
		'S_SELECT_SORT_KEY'		=> $s_sort_key,
		'S_SELECT_SORT_DAYS'	=> $s_limit_days,
		'S_TOPIC_ICONS'			=> ($basic_forum_data['enable_icons']) ? true : false,
		'S_WATCH_FORUM_LINK'	=> $s_watching_forum['link'],
		'S_WATCH_FORUM_TITLE'	=> $s_watching_forum['title'],
		'S_WATCHING_FORUM'		=> $s_watching_forum['is_watching'],
		'S_FORUM_ACTION'		=> append_sid("{$phpbb_root_path}index.$phpEx", "start=$start"),
		'S_DISPLAY_SEARCHBOX'	=> false,
		'S_SEARCHBOX_ACTION'	=> append_sid("{$phpbb_root_path}search.$phpEx", 'fid[]=' . $forum_id),
		'S_SINGLE_MODERATOR'	=> (!empty($moderators[$forum_id]) && sizeof($moderators[$forum_id]) > 1) ? false : true,
		'S_IS_LOCKED'			=> ($basic_forum_data['forum_status'] == ITEM_LOCKED) ? true : false,
		'S_VIEWFORUM'			=> true,

		'U_MCP'				=> ($auth->acl_get('m_', $forum_id)) ? append_sid("{$phpbb_root_path}mcp.$phpEx", "f=$forum_id&amp;i=main&amp;mode=forum_view", true, $user->session_id) : '',
		'U_POST_NEW_TOPIC'	=> ($auth->acl_get('f_post', $forum_id) || $user->data['user_id'] == ANONYMOUS) ? append_sid("{$phpbb_root_path}posting.$phpEx", 'mode=post&amp;f=' . $forum_id) : '',
		'U_VIEW_FORUM'		=> append_sid("{$phpbb_root_path}index.$phpEx", ((strlen($u_sort_param)) ? "$u_sort_param" : '') . "start=$start"),
		'U_MARK_TOPICS'		=> ($user->data['is_registered'] || $config['load_anon_lastread']) ? append_sid("{$phpbb_root_path}index.$phpEx", 'hash=' . generate_link_hash('global') . "&amp;mark=all") : '',
				'S_PHPBBASIC_FORUM'	=> ($forum_id == (int) $config['phpbbasic_forumid']) ? true : false,
	));

	// Grab icons
	$icons = $cache->obtain_icons();

	// Grab all topic data
	$rowset = $announcement_list = $topic_list = $global_announce_list = array();

	$sql_array = array(
		'SELECT'	=> 't.*',
		'FROM'		=> array(
			TOPICS_TABLE		=> 't'
		),
		'LEFT_JOIN'	=> array(),
	);

	$sql_approved = ($auth->acl_get('m_approve', $forum_id)) ? '' : 'AND t.topic_approved = 1';

	if ($user->data['is_registered'])
	{
		if ($config['load_db_track'])
		{
			$sql_array['LEFT_JOIN'][] = array('FROM' => array(TOPICS_POSTED_TABLE => 'tp'), 'ON' => 'tp.topic_id = t.topic_id AND tp.user_id = ' . $user->data['user_id']);
			$sql_array['SELECT'] .= ', tp.topic_posted';
		}

		if ($config['load_db_lastread'])
		{
			$sql_array['LEFT_JOIN'][] = array('FROM' => array(TOPICS_TRACK_TABLE => 'tt'), 'ON' => 'tt.topic_id = t.topic_id AND tt.user_id = ' . $user->data['user_id']);
			$sql_array['SELECT'] .= ', tt.mark_time';
		}
	}
	
	$sql_array['LEFT_JOIN'][] = array('FROM' => array(FORUMS_TABLE => 'f'), 'ON' => 'f.forum_id = t.forum_id');

	// Obtain announcements ... removed sort ordering, sort by time in all cases
	$sql = $db->sql_build_query('SELECT', array(
		'SELECT'	=> $sql_array['SELECT'],
		'FROM'		=> $sql_array['FROM'],
		'LEFT_JOIN'	=> $sql_array['LEFT_JOIN'],
		'WHERE'		=> '(t.forum_id IN (' . $forum_id . ', 0)' . (($forum_id != $phpbbasic_forumid) ? '' : " OR f.parent_id <> " . $phpbbasic_forumid) . ')
			AND t.topic_type IN (' . POST_ANNOUNCE . ', ' . POST_GLOBAL . ')',
		'ORDER_BY'	=> 't.topic_time DESC',
	));
	$result = $db->sql_query($sql);

	while ($row = $db->sql_fetchrow($result))
	{
		$rowset[$row['topic_id']] = $row;
		$announcement_list[] = $row['topic_id'];

		if ($row['topic_type'] == POST_GLOBAL)
		{
			$global_announce_list[$row['topic_id']] = true;
		}
		else
		{
			$topics_count--;
		}
	}
	$db->sql_freeresult($result);

	// If the user is trying to reach late pages, start searching from the end
	$store_reverse = false;
	$sql_limit = $config['topics_per_page'];
	if ($start > $topics_count / 2)
	{
		$store_reverse = true;

		if ($start + $config['topics_per_page'] > $topics_count)
		{
			$sql_limit = min($config['topics_per_page'], max(1, $topics_count - $start));
		}

		// Select the sort order
		$sql_sort_order = $sort_by_sql[$sort_key] . ' ' . (($sort_dir == 'd') ? 'ASC' : 'DESC');
		$sql_start = max(0, $topics_count - $sql_limit - $start);
	}
	else
	{
		// Select the sort order
		$sql_sort_order = $sort_by_sql[$sort_key] . ' ' . (($sort_dir == 'd') ? 'DESC' : 'ASC');
		$sql_start = $start;
	}

	// Grab just the sorted topic ids
	$sql = 'SELECT t.topic_id
		FROM ' . TOPICS_TABLE . " t
		LEFT JOIN " . FORUMS_TABLE . " f ON f.forum_id = t.forum_id
		WHERE
			(t.forum_id = $forum_id" . (($forum_id != $phpbbasic_forumid) ? '' : " OR f.parent_id <> " . $phpbbasic_forumid) . ")
			AND t.topic_type IN (" . POST_NORMAL . ', ' . POST_STICKY . ")
			$sql_approved
			$sql_limit_time
		ORDER BY t.topic_type " . ((!$store_reverse) ? 'DESC' : 'ASC') . ', ' . $sql_sort_order;

	$result = $db->sql_query_limit($sql, $sql_limit, $sql_start);

	while ($row = $db->sql_fetchrow($result))
	{
		$topic_list[] = (int) $row['topic_id'];
	}
	$db->sql_freeresult($result);

	if (sizeof($topic_list))
	{
		// SQL array for obtaining topics/stickies
		$sql_array = array(
			'SELECT'		=> $sql_array['SELECT'],
			'FROM'			=> $sql_array['FROM'],
			'LEFT_JOIN'		=> $sql_array['LEFT_JOIN'],

			'WHERE'			=> $db->sql_in_set('t.topic_id', $topic_list),
		);

		// If store_reverse, then first obtain topics, then stickies, else the other way around...
		// Funnily enough you typically save one query if going from the last page to the middle (store_reverse) because
		// the number of stickies are not known
		$sql = $db->sql_build_query('SELECT', $sql_array);
		$result = $db->sql_query($sql);

		while ($row = $db->sql_fetchrow($result))
		{
			$rowset[$row['topic_id']] = $row;
		}
		$db->sql_freeresult($result);
	}

	$template->assign_vars(array(
		'PAGINATION'	=> generate_pagination(append_sid("{$phpbb_root_path}index.$phpEx", (strlen($u_sort_param) ? $u_sort_param : '')), $topics_count, $config['topics_per_page'], $start),
		'PAGE_NUMBER'	=> on_page($topics_count, $config['topics_per_page'], $start),
		'TOTAL_TOPICS'	=> ($topics_count == 1) ? $user->lang['VIEW_FORUM_TOPIC'] : sprintf($user->lang['VIEW_FORUM_TOPICS'], $topics_count),
		'FORUM_NAME'	=> $basic_forum_data['forum_name'],
	));
	
	if ($forum_id != $phpbbasic_forumid)
	{
		generate_forum_nav($basic_forum_data);
	}
	
	$topic_list = ($store_reverse) ? array_merge($announcement_list, array_reverse($topic_list)) : array_merge($announcement_list, $topic_list);
	$topic_tracking_info = $tracking_topics = array();

	// Okay, lets dump out the page ...
	if (sizeof($topic_list))
	{
		$mark_forum_read = true;
		$mark_time_forum = 0;

		if ($config['load_db_lastread'] && $user->data['is_registered'])
		{
			$topic_tracking_info = get_topic_tracking($forum_id, $topic_list, $rowset, array($forum_id => $basic_forum_data['mark_time']), $global_announce_list);
			$mark_time_forum = (!empty($basic_forum_data['mark_time'])) ? $basic_forum_data['mark_time'] : $user->data['user_lastmark'];
		}
		else if ($config['load_anon_lastread'] || $user->data['is_registered'])
		{
			$topic_tracking_info = get_complete_topic_tracking($forum_id, $topic_list, $global_announce_list);

			if (!$user->data['is_registered'])
			{
				$user->data['user_lastmark'] = (isset($tracking_topics['l'])) ? (int) (base_convert($tracking_topics['l'], 36, 10) + $config['board_startdate']) : 0;
			}
			$mark_time_forum = (isset($tracking_topics['f'][$forum_id])) ? (int) (base_convert($tracking_topics['f'][$forum_id], 36, 10) + $config['board_startdate']) : $user->data['user_lastmark'];
		}

		$s_type_switch = 0;
		foreach ($topic_list as $topic_id)
		{
			$row = &$rowset[$topic_id];

			// This will allow the style designer to output a different header
			// or even separate the list of announcements from sticky and normal topics
			$s_type_switch_test = ($row['topic_type'] == POST_ANNOUNCE || $row['topic_type'] == POST_GLOBAL) ? 1 : 0;

			// Replies
			$replies = ($auth->acl_get('m_approve', $forum_id)) ? $row['topic_replies_real'] : $row['topic_replies'];

			$unread_topic = (isset($topic_tracking_info[$topic_id]) && $row['topic_last_post_time'] > $topic_tracking_info[$topic_id] && $row['topic_last_poster_id'] != $user->data['user_id']) ? true : false;

			// Get folder img, topic status/type related information
			$folder_img = $folder_alt = $topic_type = '';
			topic_status($row, $replies, $unread_topic, $folder_img, $folder_alt, $topic_type);

			// Generate all the URIs ...
			$view_topic_url_params = 't=' . $topic_id;
			$view_topic_url = append_sid("{$phpbb_root_path}viewtopic.$phpEx", $view_topic_url_params);

			$topic_unapproved = (!$row['topic_approved'] && $auth->acl_get('m_approve', (($row['forum_id']) ? $row['forum_id'] : $forum_id))) ? true : false;
			$posts_unapproved = ($row['topic_approved'] && $row['topic_replies'] < $row['topic_replies_real'] && $auth->acl_get('m_approve', (($row['forum_id']) ? $row['forum_id'] : $forum_id))) ? true : false;
			$u_mcp_queue = ($topic_unapproved || $posts_unapproved) ? append_sid("{$phpbb_root_path}mcp.$phpEx", 'i=queue&amp;mode=' . (($topic_unapproved) ? 'approve_details' : 'unapproved_posts') . "&amp;t=$topic_id", true, $user->session_id) : '';

			// Send vars to template
			$template->assign_block_vars('topicrow', array(
				'FORUM_ID'					=> $forum_id,
				'TOPIC_ID'					=> $topic_id,
				'TOPIC_AUTHOR'				=> get_username_string('username', $row['topic_poster'], $row['topic_first_poster_name'], $row['topic_first_poster_colour']),
				'TOPIC_AUTHOR_COLOUR'		=> get_username_string('colour', $row['topic_poster'], $row['topic_first_poster_name'], $row['topic_first_poster_colour']),
				'TOPIC_AUTHOR_FULL'			=> get_username_string('full', $row['topic_poster'], $row['topic_first_poster_name'], $row['topic_first_poster_colour']),
				'FIRST_POST_TIME'			=> $user->format_date($row['topic_time']),
				'LAST_POST_SUBJECT'			=> censor_text($row['topic_last_post_subject']),
				'LAST_POST_TIME'			=> $user->format_date($row['topic_last_post_time']),
				'LAST_VIEW_TIME'			=> $user->format_date($row['topic_last_view_time']),
				'LAST_POST_AUTHOR'			=> get_username_string('username', $row['topic_last_poster_id'], $row['topic_last_poster_name'], $row['topic_last_poster_colour']),
				'LAST_POST_AUTHOR_COLOUR'	=> get_username_string('colour', $row['topic_last_poster_id'], $row['topic_last_poster_name'], $row['topic_last_poster_colour']),
				'LAST_POST_AUTHOR_FULL'		=> get_username_string('full', $row['topic_last_poster_id'], $row['topic_last_poster_name'], $row['topic_last_poster_colour']),

				'PAGINATION'		=> topic_generate_pagination($replies, $view_topic_url),
				'REPLIES'			=> $replies,
				'VIEWS'				=> $row['topic_views'],
				'TOPIC_TITLE'		=> censor_text($row['topic_title']),
				'TOPIC_TYPE'		=> $topic_type,

				'TOPIC_FOLDER_IMG'		=> $user->img($folder_img, $folder_alt),
				'TOPIC_FOLDER_IMG_SRC'	=> $user->img($folder_img, $folder_alt, false, '', 'src'),
				'TOPIC_FOLDER_IMG_ALT'	=> $user->lang[$folder_alt],
				'TOPIC_FOLDER_IMG_WIDTH'=> $user->img($folder_img, '', false, '', 'width'),
				'TOPIC_FOLDER_IMG_HEIGHT'	=> $user->img($folder_img, '', false, '', 'height'),

				'TOPIC_ICON_IMG'		=> (!empty($icons[$row['icon_id']])) ? $icons[$row['icon_id']]['img'] : '',
				'TOPIC_ICON_IMG_WIDTH'	=> (!empty($icons[$row['icon_id']])) ? $icons[$row['icon_id']]['width'] : '',
				'TOPIC_ICON_IMG_HEIGHT'	=> (!empty($icons[$row['icon_id']])) ? $icons[$row['icon_id']]['height'] : '',
				'ATTACH_ICON_IMG'		=> ($auth->acl_get('u_download') && $auth->acl_get('f_download', $forum_id) && $row['topic_attachment']) ? $user->img('icon_topic_attach', $user->lang['TOTAL_ATTACHMENTS']) : '',
				'UNAPPROVED_IMG'		=> ($topic_unapproved || $posts_unapproved) ? $user->img('icon_topic_unapproved', ($topic_unapproved) ? 'TOPIC_UNAPPROVED' : 'POSTS_UNAPPROVED') : '',

				'S_PHPBBASIC_ENABLED'	=> true,
				'S_TOPIC_TYPE'			=> $row['topic_type'],
				'S_USER_POSTED'			=> (isset($row['topic_posted']) && $row['topic_posted']) ? true : false,
				'S_UNREAD_TOPIC'		=> $unread_topic,
				'S_TOPIC_REPORTED'		=> (!empty($row['topic_reported']) && $auth->acl_get('m_report', $forum_id)) ? true : false,
				'S_TOPIC_UNAPPROVED'	=> $topic_unapproved,
				'S_POSTS_UNAPPROVED'	=> $posts_unapproved,
				'S_HAS_POLL'			=> ($row['poll_start']) ? true : false,
				'S_POST_ANNOUNCE'		=> ($row['topic_type'] == POST_ANNOUNCE) ? true : false,
				'S_POST_GLOBAL'			=> ($row['topic_type'] == POST_GLOBAL) ? true : false,
				'S_POST_STICKY'			=> ($row['topic_type'] == POST_STICKY) ? true : false,
				'S_TOPIC_LOCKED'		=> ($row['topic_status'] == ITEM_LOCKED) ? true : false,
				'S_TOPIC_MOVED'			=> ($row['topic_status'] == ITEM_MOVED) ? true : false,

				'U_NEWEST_POST'			=> append_sid("{$phpbb_root_path}viewtopic.$phpEx", $view_topic_url_params . '&amp;view=unread') . '#unread',
				'U_LAST_POST'			=> append_sid("{$phpbb_root_path}viewtopic.$phpEx", $view_topic_url_params . '&amp;p=' . $row['topic_last_post_id']) . '#p' . $row['topic_last_post_id'],
				'U_LAST_POST_AUTHOR'	=> get_username_string('profile', $row['topic_last_poster_id'], $row['topic_last_poster_name'], $row['topic_last_poster_colour']),
				'U_TOPIC_AUTHOR'		=> get_username_string('profile', $row['topic_poster'], $row['topic_first_poster_name'], $row['topic_first_poster_colour']),
				'U_VIEW_TOPIC'			=> $view_topic_url,
				'U_MCP_REPORT'			=> append_sid("{$phpbb_root_path}mcp.$phpEx", 'i=reports&amp;mode=reports&amp;t=' . $topic_id, true, $user->session_id),
				'U_MCP_QUEUE'			=> $u_mcp_queue,

				'S_TOPIC_TYPE_SWITCH'	=> ($s_type_switch == $s_type_switch_test) ? -1 : $s_type_switch_test)
			);

			$s_type_switch = ($row['topic_type'] == POST_ANNOUNCE || $row['topic_type'] == POST_GLOBAL) ? 1 : 0;

			if ($unread_topic)
			{
				$mark_forum_read = false;
			}

			unset($rowset[$topic_id]);
		}
	}

	if (sizeof($topic_list) && $mark_forum_read)
	{
		update_forum_tracking_info($forum_id, $basic_forum_data['forum_last_post_time'], false, $mark_time_forum);
	}

}

/**
* Overwrite template variables more easily
*/
function phpbbasic_overwrite_template_vars($phpbbasic_enabled, &$template, $data)
{
	if ($phpbbasic_enabled)
	{
		global $server_path, $viewtopic_url, $auth, $phpbb_root_path, $phpEx;
		global $start, $u_sort_param, $user;
		
		$template->assign_vars(array(
			'FORUM_NAME'	=> $user->lang['INDEX'],
			'U_MCP' 		=> ($auth->acl_get('m_', $data['forum_id'])) ? append_sid("{$phpbb_root_path}mcp.$phpEx", "i=main&amp;mode=topic_view&amp;t={$data['topic_id']}&amp;start=$start" . ((strlen($u_sort_param)) ? "&amp;$u_sort_param" : ''), true, $user->session_id) : '',

			'S_TOPIC_ACTION' 		=> append_sid("{$phpbb_root_path}viewtopic.$phpEx", "t={$data['topic_id']}&amp;start=$start"),
			'S_MOD_ACTION' 			=> append_sid("{$phpbb_root_path}mcp.$phpEx", "t={$data['topic_id']}&amp;start=$start&amp;quickmod=1&amp;redirect=" . urlencode(str_replace('&amp;', '&', $viewtopic_url)), true, $user->session_id),
			'S_VIEWTOPIC'			=> true,
			'U_TOPIC'				=> "{$server_path}viewtopic.$phpEx?t={$data['topic_id']}",
			'U_VIEW_FORUM' 			=> append_sid("{$phpbb_root_path}index.$phpEx"),
			'U_FORUM'				=> false,
			'U_VIEW_OLDER_TOPIC'	=> append_sid("{$phpbb_root_path}viewtopic.$phpEx", "t={$data['topic_id']}&amp;view=previous"),
			'U_VIEW_NEWER_TOPIC'	=> append_sid("{$phpbb_root_path}viewtopic.$phpEx", "t={$data['topic_id']}&amp;view=next"))
		);
	}
}

/**
* Synchronize forum post and topic counts
*/
function phpbbasic_sync()
{
	global $db, $config;

	$total_posts = $total_topics = $total_topics_real = 0;

	$sql = 'SELECT SUM(forum_posts) AS total_posts, SUM(forum_topics) AS total_topics, SUM(forum_topics_real) AS total_topics_real
		FROM ' . FORUMS_TABLE . '
		WHERE forum_id <> ' . (int) $config['phpbbasic_forumid'] . ' 
			AND parent_id <> ' . (int) $config['phpbbasic_forumid'];
	$result = $db->sql_query($sql);
	$row = $db->sql_fetchrow($result);
	$db->sql_freeresult($result);

	$total_posts = (int) $row['total_posts'];
	$total_topics = (int) $row['total_topics'];
	$total_topics_real = (int) $row['total_topics_real'];

	$sql = 'SELECT COUNT(*) AS post_count
		FROM ' . POSTS_TABLE . '
		WHERE forum_id = ' . (int) $config['phpbbasic_forumid'];
	$result = $db->sql_query($sql);
	$total_posts += $db->sql_fetchfield('post_count');
	$db->sql_freeresult($result);

	$sql = 'SELECT COUNT(*) AS topic_count
		FROM ' . TOPICS_TABLE . '
		WHERE forum_id = ' . (int) $config['phpbbasic_forumid'];
	$result = $db->sql_query($sql);
	$total_topics += $db->sql_fetchfield('topic_count');
	$db->sql_freeresult($result);

	$sql = 'SELECT COUNT(*) AS topic_count_real
		FROM ' . TOPICS_TABLE . '
		WHERE forum_id = ' . (int) $config['phpbbasic_forumid'] . '
			AND topic_approved = 1';
	$result = $db->sql_query($sql);
	$total_topics_real += $db->sql_fetchfield('topic_count_real');
	$db->sql_freeresult($result);

	$sql = 'UPDATE ' . FORUMS_TABLE . "
			SET forum_posts = $total_posts, forum_topics = $total_topics, forum_topics_real = $total_topics_real
			WHERE forum_id = " . (int) $config['phpbbasic_forumid'];
	$db->sql_query($sql);
}

/**
* Display the phpbbasic acp forums module in place of acp_forums.php
*/
function acp_forums_main($id, $mode, $acp_forums_class)
{
	global $db, $user, $auth, $template, $cache;
	global $config, $phpbb_admin_path, $phpbb_root_path, $phpEx;

	$user->add_lang('acp/forums');
	$acp_forums_class->tpl_name = 'acp_forums_phpbbasic';
	$acp_forums_class->page_title = 'ACP_MANAGE_FORUMS';

	$form_key = 'acp_forums_phpbbasic';
	add_form_key($form_key);

	$action		= request_var('action', '');
	$update		= (isset($_POST['update'])) ? true : false;
	$original_forumid = $forum_id	= request_var('f', 0);
	$phpbbasic_forumid = (int) $config['phpbbasic_forumid'];
	$acp_forums_class->parent_id	= request_var('parent_id', 0);

	//Make sure there is no funny business going on with the parent forum id
	if ($acp_forums_class->parent_id != $phpbbasic_forumid && $acp_forums_class->parent_id > 0)
	{
		$acp_forums_class->parent_id = $phpbbasic_forumid;
	}
	$forum_data = $errors = array();
	if ($update && !check_form_key($form_key))
	{
		$update = false;
		$errors[] = $user->lang['FORM_INVALID'];
	}

	// Check additional permissions
	switch ($action)
	{
		case 'progress_bar':
			$start = request_var('start', 0);
			$total = request_var('total', 0);

			$acp_forums_class->display_progress_bar($start, $total);
			exit;
		break;

		case 'delete':

			if (!$auth->acl_get('a_forumdel') || $forum_id == $phpbbasic_forumid)
			{
				trigger_error($user->lang['NO_PERMISSION_FORUM_DELETE'] . adm_back_link($acp_forums_class->u_action . '&amp;parent_id=' . $acp_forums_class->parent_id), E_USER_WARNING);
			}

		break;

		case 'add':

			if (!$auth->acl_get('a_forumadd'))
			{
				trigger_error($user->lang['NO_PERMISSION_FORUM_ADD'] . adm_back_link($acp_forums_class->u_action . '&amp;parent_id=' . $acp_forums_class->parent_id), E_USER_WARNING);
			}

		break;
	}

	// Major routines
	if ($update)
	{
		switch ($action)
		{
			case 'delete':
				$action_subforums	= request_var('action_subforums', '');
				$subforums_to_id	= request_var('subforums_to_id', 0);
				$action_posts		= request_var('action_posts', '');
				$posts_to_id		= request_var('posts_to_id', 0);

				$errors = $acp_forums_class->delete_forum($forum_id, $action_posts, $action_subforums, $posts_to_id, $subforums_to_id);

				if (sizeof($errors))
				{
					break;
				}

				$auth->acl_clear_prefetch();
				$cache->destroy('sql', FORUMS_TABLE);

				trigger_error($user->lang['FORUM_DELETED'] . adm_back_link($acp_forums_class->u_action . '&amp;parent_id=' . $acp_forums_class->parent_id));

			break;

			case 'edit':
				$forum_data = array(
					'forum_id'		=>	$forum_id
				);

			// No break here

			case 'add':

				$forum_data += array(
					'parent_id'				=> request_var('forum_parent_id', $acp_forums_class->parent_id),
					'forum_type'			=> request_var('forum_type', FORUM_POST),
					'type_action'			=> request_var('type_action', ''),
					'forum_status'			=> request_var('forum_status', ITEM_UNLOCKED),
					'forum_parents'			=> '',
					'forum_name'			=> utf8_normalize_nfc(request_var('forum_name', '', true)),
					'forum_link'			=> request_var('forum_link', ''),
					'forum_link_track'		=> request_var('forum_link_track', false),
					'forum_desc'			=> utf8_normalize_nfc(request_var('forum_desc', '', true)),
					'forum_desc_uid'		=> '',
					'forum_desc_options'	=> 7,
					'forum_desc_bitfield'	=> '',
					'forum_rules'			=> utf8_normalize_nfc(request_var('forum_rules', '', true)),
					'forum_rules_uid'		=> '',
					'forum_rules_options'	=> 7,
					'forum_rules_bitfield'	=> '',
					'forum_rules_link'		=> request_var('forum_rules_link', ''),
					'forum_image'			=> request_var('forum_image', ''),
					'forum_style'			=> request_var('forum_style', 0),
					'display_subforum_list'	=> request_var('display_subforum_list', false),
					'display_on_index'		=> request_var('display_on_index', false),
					'forum_topics_per_page'	=> request_var('topics_per_page', 0),
					'enable_indexing'		=> request_var('enable_indexing', true),
					'enable_icons'			=> request_var('enable_icons', false),
					'enable_prune'			=> request_var('enable_prune', false),
					'enable_post_review'	=> request_var('enable_post_review', true),
					'enable_quick_reply'	=> request_var('enable_quick_reply', false),
					'prune_days'			=> request_var('prune_days', 7),
					'prune_viewed'			=> request_var('prune_viewed', 7),
					'prune_freq'			=> request_var('prune_freq', 1),
					'prune_old_polls'		=> request_var('prune_old_polls', false),
					'prune_announce'		=> request_var('prune_announce', false),
					'prune_sticky'			=> request_var('prune_sticky', false),
					'forum_password'		=> request_var('forum_password', '', true),
					'forum_password_confirm'=> request_var('forum_password_confirm', '', true),
					'forum_password_unset'	=> request_var('forum_password_unset', request_var('forum_type', FORUM_POST) == FORUM_LINK),
				);

				// On add, add empty forum_options... else do not consider it (not updating it)
				if ($action == 'add')
				{
					$forum_data['forum_options'] = 0;
				}

				// Linked forums and categories are not able to be locked...
				if ($forum_data['forum_type'] == FORUM_LINK)
				{
					$forum_data['forum_status'] = ITEM_UNLOCKED;
				}

				$forum_data['show_active'] = ($forum_data['forum_type'] == FORUM_POST) ? request_var('display_recent', true) : request_var('display_active', true);

				// Get data for forum rules if specified...
				if ($forum_data['forum_rules'])
				{
					generate_text_for_storage($forum_data['forum_rules'], $forum_data['
_uid'], $forum_data['forum_rules_bitfield'], $forum_data['forum_rules_options'], request_var('rules_parse_bbcode', false), request_var('rules_parse_urls', false), request_var('rules_parse_smilies', false));
				}

				// Get data for forum description if specified
				if ($forum_data['forum_desc'])
				{
					generate_text_for_storage($forum_data['forum_desc'], $forum_data['forum_desc_uid'], $forum_data['forum_desc_bitfield'], $forum_data['forum_desc_options'], request_var('desc_parse_bbcode', false), request_var('desc_parse_urls', false), request_var('desc_parse_smilies', false));
				}

				$errors = $acp_forums_class->update_forum_data($forum_data);

				if (!sizeof($errors))
				{
					$forum_perm_from = request_var('forum_perm_from', 0);
					$cache->destroy('sql', FORUMS_TABLE);

					// Copy permissions?
					if ($forum_perm_from && $forum_perm_from != $forum_data['forum_id'] &&
						($action != 'edit' || empty($forum_id) || ($auth->acl_get('a_fauth') && $auth->acl_get('a_authusers') && $auth->acl_get('a_authgroups') && $auth->acl_get('a_mauth'))))
					{
						copy_forum_permissions($forum_perm_from, $forum_data['forum_id'], ($action == 'edit') ? true : false);
						cache_moderators();
					}

					$auth->acl_clear_prefetch();

					$acl_url = '&amp;mode=setting_forum_local&amp;forum_id[]=' . $forum_data['forum_id'];

					$message = ($action == 'add') ? $user->lang['FORUM_CREATED'] : $user->lang['FORUM_UPDATED'];

					// Redirect to permissions
					if ($auth->acl_get('a_fauth'))
					{
						$message .= '<br /><br />' . sprintf($user->lang['REDIRECT_ACL'], '<a href="' . append_sid("{$phpbb_admin_path}index.$phpEx", 'i=permissions' . $acl_url) . '">', '</a>');
					}

					// redirect directly to permission settings screen if authed
					if ($action == 'add' && !$forum_perm_from && $auth->acl_get('a_fauth'))
					{
						meta_refresh(4, append_sid("{$phpbb_admin_path}index.$phpEx", 'i=permissions' . $acl_url));
					}

					trigger_error($message . adm_back_link($acp_forums_class->u_action . '&amp;parent_id=' . $acp_forums_class->parent_id));
				}

			break;
		}
	}

	switch ($action)
	{
		case 'move_up':
		case 'move_down':

			if (!$forum_id)
			{
				trigger_error($user->lang['NO_FORUM'] . adm_back_link($acp_forums_class->u_action . '&amp;parent_id=' . $acp_forums_class->parent_id), E_USER_WARNING);
			}

			$sql = 'SELECT *
				FROM ' . FORUMS_TABLE . "
				WHERE forum_id = $forum_id";
			$result = $db->sql_query($sql);
			$row = $db->sql_fetchrow($result);
			$db->sql_freeresult($result);

			if (!$row)
			{
				trigger_error($user->lang['NO_FORUM'] . adm_back_link($acp_forums_class->u_action . '&amp;parent_id=' . $acp_forums_class->parent_id), E_USER_WARNING);
			}

			$move_forum_name = $acp_forums_class->move_forum_by($row, $action, 1);

			if ($move_forum_name !== false)
			{
				add_log('admin', 'LOG_FORUM_' . strtoupper($action), $row['forum_name'], $move_forum_name);
				$cache->destroy('sql', FORUMS_TABLE);
			}

		break;

		case 'sync':
			if (!$forum_id)
			{
				trigger_error($user->lang['NO_FORUM'] . adm_back_link($acp_forums_class->u_action . '&amp;parent_id=' . $acp_forums_class->parent_id), E_USER_WARNING);
			}

			@set_time_limit(0);

			$sql = 'SELECT forum_name, forum_topics_real
				FROM ' . FORUMS_TABLE . "
				WHERE forum_id = $forum_id";
			$result = $db->sql_query($sql);
			$row = $db->sql_fetchrow($result);
			$db->sql_freeresult($result);

			if (!$row)
			{
				trigger_error($user->lang['NO_FORUM'] . adm_back_link($acp_forums_class->u_action . '&amp;parent_id=' . $acp_forums_class->parent_id), E_USER_WARNING);
			}

			if ($row['forum_topics_real'])
			{
				$sql = 'SELECT MIN(topic_id) as min_topic_id, MAX(topic_id) as max_topic_id
					FROM ' . TOPICS_TABLE . '
					WHERE forum_id = ' . $forum_id;
				$result = $db->sql_query($sql);
				$row2 = $db->sql_fetchrow($result);
				$db->sql_freeresult($result);

				// Typecast to int if there is no data available
				$row2['min_topic_id'] = (int) $row2['min_topic_id'];
				$row2['max_topic_id'] = (int) $row2['max_topic_id'];

				$start = request_var('start', $row2['min_topic_id']);

				$batch_size = 2000;
				$end = $start + $batch_size;

				// Sync all topics in batch mode...
				sync('topic_approved', 'range', 'topic_id BETWEEN ' . $start . ' AND ' . $end, true, false);
				sync('topic', 'range', 'topic_id BETWEEN ' . $start . ' AND ' . $end, true, true);

				if ($end < $row2['max_topic_id'])
				{
					// We really need to find a way of showing statistics... no progress here
					$sql = 'SELECT COUNT(topic_id) as num_topics
						FROM ' . TOPICS_TABLE . '
						WHERE forum_id = ' . $forum_id . '
							AND topic_id BETWEEN ' . $start . ' AND ' . $end;
					$result = $db->sql_query($sql);
					$topics_done = request_var('topics_done', 0) + (int) $db->sql_fetchfield('num_topics');
					$db->sql_freeresult($result);

					$start += $batch_size;

					$url = $acp_forums_class->u_action . "&amp;parent_id={$acp_forums_class->parent_id}&amp;f=$forum_id&amp;action=sync&amp;start=$start&amp;topics_done=$topics_done&amp;total={$row['forum_topics_real']}";

					meta_refresh(0, $url);

					$template->assign_vars(array(
						'U_PROGRESS_BAR'		=> $acp_forums_class->u_action . "&amp;action=progress_bar&amp;start=$topics_done&amp;total={$row['forum_topics_real']}",
						'UA_PROGRESS_BAR'		=> addslashes($acp_forums_class->u_action . "&amp;action=progress_bar&amp;start=$topics_done&amp;total={$row['forum_topics_real']}"),
						'S_CONTINUE_SYNC'		=> true,
						'L_PROGRESS_EXPLAIN'	=> sprintf($user->lang['SYNC_IN_PROGRESS_EXPLAIN'], $topics_done, $row['forum_topics_real']))
					);

					return;
				}
			}

			$url = $acp_forums_class->u_action . "&amp;parent_id={$acp_forums_class->parent_id}&amp;f=$forum_id&amp;action=sync_forum";
			meta_refresh(0, $url);

			$template->assign_vars(array(
				'U_PROGRESS_BAR'		=> $acp_forums_class->u_action . '&amp;action=progress_bar',
				'UA_PROGRESS_BAR'		=> addslashes($acp_forums_class->u_action . '&amp;action=progress_bar'),
				'S_CONTINUE_SYNC'		=> true,
				'L_PROGRESS_EXPLAIN'	=> sprintf($user->lang['SYNC_IN_PROGRESS_EXPLAIN'], 0, $row['forum_topics_real']))
			);

			return;

		break;

		case 'sync_forum':

			$sql = 'SELECT forum_name, forum_type
				FROM ' . FORUMS_TABLE . "
				WHERE forum_id = $forum_id";
			$result = $db->sql_query($sql);
			$row = $db->sql_fetchrow($result);
			$db->sql_freeresult($result);

			if (!$row)
			{
				trigger_error($user->lang['NO_FORUM'] . adm_back_link($acp_forums_class->u_action . '&amp;parent_id=' . $acp_forums_class->parent_id), E_USER_WARNING);
			}

			sync('forum', 'forum_id', $forum_id, false, true);

			add_log('admin', 'LOG_FORUM_SYNC', $row['forum_name']);
			$cache->destroy('sql', FORUMS_TABLE);

			$template->assign_var('L_FORUM_RESYNCED', sprintf($user->lang['FORUM_RESYNCED'], $row['forum_name']));

		break;

		case 'add':
		case 'edit':

			if ($update)
			{
				$forum_data['forum_flags'] = 0;
				$forum_data['forum_flags'] += (request_var('forum_link_track', false)) ? FORUM_FLAG_LINK_TRACK : 0;
				$forum_data['forum_flags'] += (request_var('prune_old_polls', false)) ? FORUM_FLAG_PRUNE_POLL : 0;
				$forum_data['forum_flags'] += (request_var('prune_announce', false)) ? FORUM_FLAG_PRUNE_ANNOUNCE : 0;
				$forum_data['forum_flags'] += (request_var('prune_sticky', false)) ? FORUM_FLAG_PRUNE_STICKY : 0;
				$forum_data['forum_flags'] += ($forum_data['show_active']) ? FORUM_FLAG_ACTIVE_TOPICS : 0;
				$forum_data['forum_flags'] += (request_var('enable_post_review', true)) ? FORUM_FLAG_POST_REVIEW : 0;
				$forum_data['forum_flags'] += (request_var('enable_quick_reply', false)) ? FORUM_FLAG_QUICK_REPLY : 0;
			}

			// Show form to create/modify a forum
			if ($action == 'edit')
			{
				$acp_forums_class->page_title = 'EDIT_FORUM';
				$row = $acp_forums_class->get_forum_info($forum_id);
				$old_forum_type = $row['forum_type'];

				if (!$update)
				{
					$forum_data = $row;
				}
				else
				{
					$forum_data['left_id'] = $row['left_id'];
					$forum_data['right_id'] = $row['right_id'];
				}

				$forum_data['forum_password_confirm'] = $forum_data['forum_password'];
			}
			else
			{
				$acp_forums_class->page_title = 'CREATE_FORUM';

				$forum_id = $acp_forums_class->parent_id;

				// Fill forum data with default values
				if (!$update)
				{
					$forum_data = array(
						'forum_id'				=> 0,
						'parent_id'				=> $acp_forums_class->parent_id,
						'forum_type'			=> FORUM_POST,
						'forum_status'			=> ITEM_UNLOCKED,
						'forum_name'			=> utf8_normalize_nfc(request_var('forum_name', '', true)),
						'forum_link'			=> '',
						'forum_link_track'		=> false,
						'forum_desc'			=> '',
						'forum_rules'			=> '',
						'forum_rules_link'		=> '',
						'forum_image'			=> '',
						'forum_style'			=> 0,
						'display_subforum_list'	=> false,
						'display_on_index'		=> false,
						'forum_topics_per_page'	=> 0,
						'enable_indexing'		=> true,
						'enable_icons'			=> false,
						'enable_prune'			=> false,
						'prune_days'			=> 7,
						'prune_viewed'			=> 7,
						'prune_freq'			=> 1,
						'forum_flags'			=> FORUM_FLAG_POST_REVIEW + FORUM_FLAG_ACTIVE_TOPICS,
						'forum_options'			=> 0,
						'forum_password'		=> '',
						'forum_password_confirm'=> '',
					);
				}
			}

			$forum_rules_data = array(
				'text'			=> $forum_data['forum_rules'],
				'allow_bbcode'	=> true,
				'allow_smilies'	=> true,
				'allow_urls'	=> true
			);

			$forum_desc_data = array(
				'text'			=> $forum_data['forum_desc'],
				'allow_bbcode'	=> true,
				'allow_smilies'	=> true,
				'allow_urls'	=> true
			);

			$forum_rules_preview = '';

			// Parse rules if specified
			if ($forum_data['forum_rules'])
			{
				if (!isset($forum_data['forum_rules_uid']))
				{
					// Before we are able to display the preview and plane text, we need to parse our request_var()'d value...
					$forum_data['forum_rules_uid'] = '';
					$forum_data['forum_rules_bitfield'] = '';
					$forum_data['forum_rules_options'] = 0;

					generate_text_for_storage($forum_data['forum_rules'], $forum_data['forum_rules_uid'], $forum_data['forum_rules_bitfield'], $forum_data['forum_rules_options'], request_var('rules_allow_bbcode', false), request_var('rules_allow_urls', false), request_var('rules_allow_smilies', false));
				}

				// Generate preview content
				$forum_rules_preview = generate_text_for_display($forum_data['forum_rules'], $forum_data['forum_rules_uid'], $forum_data['forum_rules_bitfield'], $forum_data['forum_rules_options']);

				// decode...
				$forum_rules_data = generate_text_for_edit($forum_data['forum_rules'], $forum_data['forum_rules_uid'], $forum_data['forum_rules_options']);
			}

			// Parse desciption if specified
			if ($forum_data['forum_desc'])
			{
				if (!isset($forum_data['forum_desc_uid']))
				{
					// Before we are able to display the preview and plane text, we need to parse our request_var()'d value...
					$forum_data['forum_desc_uid'] = '';
					$forum_data['forum_desc_bitfield'] = '';
					$forum_data['forum_desc_options'] = 0;

					generate_text_for_storage($forum_data['forum_desc'], $forum_data['forum_desc_uid'], $forum_data['forum_desc_bitfield'], $forum_data['forum_desc_options'], request_var('desc_allow_bbcode', false), request_var('desc_allow_urls', false), request_var('desc_allow_smilies', false));
				}

				// decode...
				$forum_desc_data = generate_text_for_edit($forum_data['forum_desc'], $forum_data['forum_desc_uid'], $forum_data['forum_desc_options']);
			}

			$forum_type_options = '';
			$forum_type_ary = array(FORUM_POST => 'FORUM', FORUM_LINK => 'LINK');

			foreach ($forum_type_ary as $value => $lang)
			{
				$forum_type_options .= '<option value="' . $value . '"' . (($value == $forum_data['forum_type']) ? ' selected="selected"' : '') . '>' . $user->lang['TYPE_' . $lang] . '</option>';
			}

			if ($original_forumid == $phpbbasic_forumid)
			{
				$forum_type_options = '<option value="' . FORUM_POST . '"' . ' selected="selected">' . $user->lang['TYPE_FORUM'] . '</option>';
			}
			$styles_list = style_select($forum_data['forum_style'], true);

			$statuslist = '<option value="' . ITEM_UNLOCKED . '"' . (($forum_data['forum_status'] == ITEM_UNLOCKED) ? ' selected="selected"' : '') . '>' . $user->lang['UNLOCKED'] . '</option><option value="' . ITEM_LOCKED . '"' . (($forum_data['forum_status'] == ITEM_LOCKED) ? ' selected="selected"' : '') . '>' . $user->lang['LOCKED'] . '</option>';

			$sql = 'SELECT forum_id
				FROM ' . FORUMS_TABLE . '
				WHERE forum_type = ' . FORUM_POST . "
					AND forum_id <> $forum_id";
			$result = $db->sql_query_limit($sql, 1);

			$postable_forum_exists = false;
			if ($db->sql_fetchrow($result))
			{
				$postable_forum_exists = true;
			}
			$db->sql_freeresult($result);

			// Subforum move options
			if ($postable_forum_exists)
			{
				$template->assign_vars(array(
					'S_MOVE_FORUM_OPTIONS'		=> make_forum_select($forum_data['parent_id'], $forum_id, false, true, false))
				);
			}

			if (strlen($forum_data['forum_password']) == 32)
			{
				$errors[] = $user->lang['FORUM_PASSWORD_OLD'];
			}

			$template->assign_vars(array(
				'S_EDIT_FORUM'		=> true,
				'S_ERROR'			=> (sizeof($errors)) ? true : false,
				'S_PARENT_ID'		=> $acp_forums_class->parent_id,
				'S_FORUM_PARENT_ID'	=> $forum_data['parent_id'],
				'S_ADD_ACTION'		=> ($action == 'add') ? true : false,

				'U_BACK'		=> $acp_forums_class->u_action . '&amp;parent_id=' . $acp_forums_class->parent_id,
				'U_EDIT_ACTION'	=> $acp_forums_class->u_action . "&amp;parent_id={$acp_forums_class->parent_id}&amp;action=$action&amp;f=$forum_id",

				'L_COPY_PERMISSIONS_EXPLAIN'	=> $user->lang['COPY_PERMISSIONS_' . strtoupper($action) . '_EXPLAIN'],
				'L_TITLE'						=> $user->lang[$acp_forums_class->page_title],
				'ERROR_MSG'						=> (sizeof($errors)) ? implode('<br />', $errors) : '',

				'S_PHPBBASIC_FORUM'			=> ((int) $forum_data['forum_id'] == $phpbbasic_forumid) ? true : false,
				'FORUM_NAME'				=> $forum_data['forum_name'],
				'FORUM_DATA_LINK'			=> $forum_data['forum_link'],
				'FORUM_IMAGE'				=> $forum_data['forum_image'],
				'FORUM_IMAGE_SRC'			=> ($forum_data['forum_image']) ? $phpbb_root_path . $forum_data['forum_image'] : '',
				'FORUM_POST'				=> FORUM_POST,
				'FORUM_LINK'				=> FORUM_LINK,
				'PRUNE_FREQ'				=> $forum_data['prune_freq'],
				'PRUNE_DAYS'				=> $forum_data['prune_days'],
				'PRUNE_VIEWED'				=> $forum_data['prune_viewed'],
				'TOPICS_PER_PAGE'			=> $forum_data['forum_topics_per_page'],
				'FORUM_RULES_LINK'			=> $forum_data['forum_rules_link'],
				'FORUM_RULES'				=> $forum_data['forum_rules'],
				'FORUM_RULES_PREVIEW'		=> $forum_rules_preview,
				'FORUM_RULES_PLAIN'			=> $forum_rules_data['text'],
				'S_BBCODE_CHECKED'			=> ($forum_rules_data['allow_bbcode']) ? true : false,
				'S_SMILIES_CHECKED'			=> ($forum_rules_data['allow_smilies']) ? true : false,
				'S_URLS_CHECKED'			=> ($forum_rules_data['allow_urls']) ? true : false,
				'S_FORUM_PASSWORD_SET'		=> (empty($forum_data['forum_password'])) ? false : true,

				'FORUM_DESC'				=> $forum_desc_data['text'],
				'S_DESC_BBCODE_CHECKED'		=> ($forum_desc_data['allow_bbcode']) ? true : false,
				'S_DESC_SMILIES_CHECKED'	=> ($forum_desc_data['allow_smilies']) ? true : false,
				'S_DESC_URLS_CHECKED'		=> ($forum_desc_data['allow_urls']) ? true : false,

				'S_FORUM_TYPE_OPTIONS'		=> $forum_type_options,
				'S_STATUS_OPTIONS'			=> $statuslist,
				'S_STYLES_OPTIONS'			=> $styles_list,
				'S_FORUM_OPTIONS'			=> make_forum_select(($action == 'add') ? $forum_data['parent_id'] : false, ($action == 'edit') ? $forum_data['forum_id'] : false, false, false, false),
				'S_FORUM_POST'				=> ($forum_data['forum_type'] == FORUM_POST) ? true : false,
				'S_FORUM_ORIG_POST'			=> (isset($old_forum_type) && $old_forum_type == FORUM_POST) ? true : false,
				'S_FORUM_ORIG_LINK'			=> (isset($old_forum_type) && $old_forum_type == FORUM_LINK) ? true : false,
				'S_FORUM_LINK'				=> ($forum_data['forum_type'] == FORUM_LINK) ? true : false,
				'S_ENABLE_INDEXING'			=> ($forum_data['enable_indexing']) ? true : false,
				'S_TOPIC_ICONS'				=> ($forum_data['enable_icons']) ? true : false,
				'S_PRUNE_ENABLE'			=> ($forum_data['enable_prune']) ? true : false,
				'S_FORUM_LINK_TRACK'		=> ($forum_data['forum_flags'] & FORUM_FLAG_LINK_TRACK) ? true : false,
				'S_PRUNE_OLD_POLLS'			=> ($forum_data['forum_flags'] & FORUM_FLAG_PRUNE_POLL) ? true : false,
				'S_PRUNE_ANNOUNCE'			=> ($forum_data['forum_flags'] & FORUM_FLAG_PRUNE_ANNOUNCE) ? true : false,
				'S_PRUNE_STICKY'			=> ($forum_data['forum_flags'] & FORUM_FLAG_PRUNE_STICKY) ? true : false,
				'S_DISPLAY_ACTIVE_TOPICS'	=> ($forum_data['forum_flags'] & FORUM_FLAG_ACTIVE_TOPICS) ? true : false,
				'S_ENABLE_POST_REVIEW'		=> ($forum_data['forum_flags'] & FORUM_FLAG_POST_REVIEW) ? true : false,
				'S_ENABLE_QUICK_REPLY'		=> ($forum_data['forum_flags'] & FORUM_FLAG_QUICK_REPLY) ? true : false,
				'S_CAN_COPY_PERMISSIONS'	=> ($action != 'edit' || empty($forum_id) || ($auth->acl_get('a_fauth') && $auth->acl_get('a_authusers') && $auth->acl_get('a_authgroups') && $auth->acl_get('a_mauth'))) ? true : false,
				'S_HIDE_FORUM_OPTIONS'		=> ((!$acp_forums_class->parent_id && !$original_forumid) || $original_forumid == $phpbbasic_forumid) ? true : false,
			));

			return;

		break;

		case 'delete':

			if (!$forum_id)
			{
				trigger_error($user->lang['NO_FORUM'] . adm_back_link($acp_forums_class->u_action . '&amp;parent_id=' . $acp_forums_class->parent_id), E_USER_WARNING);
			}

			$forum_data = $acp_forums_class->get_forum_info($forum_id);

			$subforums_id = array();
			$subforums = get_forum_branch($forum_id, 'children');

			foreach ($subforums as $row)
			{
				$subforums_id[] = $row['forum_id'];
			}

			$forums_list = make_forum_select($forum_data['parent_id'], $subforums_id);

			$sql = 'SELECT forum_id
				FROM ' . FORUMS_TABLE . '
				WHERE forum_type = ' . FORUM_POST . "
					AND forum_id <> $forum_id";
			$result = $db->sql_query_limit($sql, 1);

			if ($db->sql_fetchrow($result))
			{
				$template->assign_vars(array(
					'S_MOVE_FORUM_OPTIONS'		=> make_forum_select($forum_data['parent_id'], $subforums_id, false, true)) // , false, true, false???
				);
			}
			$db->sql_freeresult($result);

			$parent_id = ($acp_forums_class->parent_id == $forum_id) ? 0 : $acp_forums_class->parent_id;

			$template->assign_vars(array(
				'S_DELETE_FORUM'		=> true,
				'U_ACTION'				=> $acp_forums_class->u_action . "&amp;parent_id={$parent_id}&amp;action=delete&amp;f=$forum_id",
				'U_BACK'				=> $acp_forums_class->u_action . '&amp;parent_id=' . $acp_forums_class->parent_id,

				'FORUM_NAME'			=> $forum_data['forum_name'],
				'S_FORUM_POST'			=> ($forum_data['forum_type'] == FORUM_POST) ? true : false,
				'S_FORUM_LINK'			=> ($forum_data['forum_type'] == FORUM_LINK) ? true : false,
				'S_HAS_SUBFORUMS'		=> ($forum_data['right_id'] - $forum_data['left_id'] > 1) ? true : false,
				'S_FORUMS_LIST'			=> $forums_list,
				'S_ERROR'				=> (sizeof($errors)) ? true : false,
				'ERROR_MSG'				=> (sizeof($errors)) ? implode('<br />', $errors) : '')
			);

			return;
		break;

		case 'copy_perm':
			$forum_perm_from = request_var('forum_perm_from', 0);

			// Copy permissions?
			if (!empty($forum_perm_from) && $forum_perm_from != $forum_id)
			{
				copy_forum_permissions($forum_perm_from, $forum_id, true);
				cache_moderators();
				$auth->acl_clear_prefetch();
				$cache->destroy('sql', FORUMS_TABLE);

				$acl_url = '&amp;mode=setting_forum_local&amp;forum_id[]=' . $forum_id;

				$message = $user->lang['FORUM_UPDATED'];

				// Redirect to permissions
				if ($auth->acl_get('a_fauth'))
				{
					$message .= '<br /><br />' . sprintf($user->lang['REDIRECT_ACL'], '<a href="' . append_sid("{$phpbb_admin_path}index.$phpEx", 'i=permissions' . $acl_url) . '">', '</a>');
				}

				trigger_error($message . adm_back_link($acp_forums_class->u_action . '&amp;parent_id=' . $acp_forums_class->parent_id));
			}

		break;
	}

	// Default management page
	if (!$acp_forums_class->parent_id)
	{
		$navigation = $user->lang['FORUM_INDEX'];
	}
	else
	{
		$navigation = '<a href="' . $acp_forums_class->u_action . '">' . $user->lang['FORUM_INDEX'] . '</a>';

		$forums_nav = get_forum_branch($acp_forums_class->parent_id, 'parents', 'descending');
		foreach ($forums_nav as $row)
		{
			if ($row['forum_id'] == $acp_forums_class->parent_id)
			{
				$navigation .= ' -&gt; ' . $row['forum_name'];
			}
			else
			{
				$navigation .= ' -&gt; <a href="' . $acp_forums_class->u_action . '&amp;parent_id=' . $row['forum_id'] . '">' . $row['forum_name'] . '</a>';
			}
		}
	}

	if ($action == 'sync' || $action == 'sync_forum')
	{
		$template->assign_var('S_RESYNCED', true);
	}

	$sql = 'SELECT *
		FROM ' . FORUMS_TABLE . "
		WHERE parent_id = $acp_forums_class->parent_id " . (($acp_forums_class->parent_id == 0) ? 'AND forum_id = ' . $phpbbasic_forumid : '') . "
		ORDER BY left_id";
	$result = $db->sql_query($sql);

	if ($row = $db->sql_fetchrow($result))
	{
		do
		{
			$forum_type = $row['forum_type'];

			if ($row['forum_status'] == ITEM_LOCKED)
			{
				$folder_image = '<img src="images/icon_folder_lock.gif" alt="' . $user->lang['LOCKED'] . '" />';
			}
			else
			{
				switch ($forum_type)
				{
					case FORUM_LINK:
						$folder_image = '<img src="images/icon_folder_link.gif" alt="' . $user->lang['LINK'] . '" />';
					break;

					default:
						$folder_image = ($row['left_id'] + 1 != $row['right_id']) ? '<img src="images/icon_subfolder.gif" alt="' . $user->lang['SUBFORUM'] . '" />' : '<img src="images/icon_folder.gif" alt="' . $user->lang['FOLDER'] . '" />';
					break;
				}
			}

			$url = $acp_forums_class->u_action . "&amp;parent_id=$acp_forums_class->parent_id&amp;f={$row['forum_id']}";

			$template->assign_block_vars('forums', array(
				'FOLDER_IMAGE'		=> $folder_image,
				'FORUM_IMAGE'		=> ($row['forum_image']) ? '<img src="' . $phpbb_root_path . $row['forum_image'] . '" alt="" />' : '',
				'FORUM_IMAGE_SRC'	=> ($row['forum_image']) ? $phpbb_root_path . $row['forum_image'] : '',
				'FORUM_NAME'		=> $row['forum_name'],
				'FORUM_DESCRIPTION'	=> generate_text_for_display($row['forum_desc'], $row['forum_desc_uid'], $row['forum_desc_bitfield'], $row['forum_desc_options']),
				'FORUM_TOPICS'		=> $row['forum_topics'],
				'FORUM_POSTS'		=> $row['forum_posts'],
				'FORUM_ID'			=> $row['forum_id'],

				'S_FORUM_LINK'		=> ($forum_type == FORUM_LINK) ? true : false,
				'S_FORUM_POST'		=> ($forum_type == FORUM_POST) ? true : false,

				'U_FORUM'			=> ($acp_forums_class->parent_id == 0) ? $acp_forums_class->u_action . '&amp;parent_id=' . $row['forum_id'] : false,
				'U_MOVE_UP'		=> ($acp_forums_class->parent_id != 0) ? $url . '&amp;action=move_up' : false,
				'U_MOVE_DOWN'	=> ($acp_forums_class->parent_id != 0) ? $url . '&amp;action=move_down' : false,
				'U_EDIT'			=> $url . '&amp;action=edit',
				'U_DELETE'		=> ($acp_forums_class->parent_id != 0) ? $url . '&amp;action=delete' : false,
				'U_SYNC'			=> $url . '&amp;action=sync')
			);
			
		}
		while ($row = $db->sql_fetchrow($result));
	}
	else if ($acp_forums_class->parent_id)
	{
		$row = $acp_forums_class->get_forum_info($acp_forums_class->parent_id);

		$url = $acp_forums_class->u_action . '&amp;parent_id=' . $acp_forums_class->parent_id . '&amp;f=' . $row['forum_id'];

		$template->assign_vars(array(
			'S_NO_FORUMS'		=> true,

			'U_EDIT'			=> $url . '&amp;action=edit',
			'U_DELETE'			=> $url . '&amp;action=delete',
			'U_SYNC'			=> $url . '&amp;action=sync')
		);
	}
	$db->sql_freeresult($result);

	$template->assign_vars(array(
		'ERROR_MSG'		=> (sizeof($errors)) ? implode('<br />', $errors) : '',
		'NAVIGATION'	=> $navigation,
		'U_ACTION'		=> $acp_forums_class->u_action . '&amp;parent_id=' . $acp_forums_class->parent_id,
		'S_PHPBBASIC_FORUMID' => $phpbbasic_forumid,
		'S_PARENT_ID'		=> request_var('parent_id', 0),
		'U_PROGRESS_BAR'	=> $acp_forums_class->u_action . '&amp;action=progress_bar',
		'UA_PROGRESS_BAR'	=> addslashes($acp_forums_class->u_action . '&amp;action=progress_bar'),
	));
}

?>