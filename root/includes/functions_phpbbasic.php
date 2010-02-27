<?php
/**
*
* @package phpBBasic
* @copyright (c) 2010 DavidIQ.com
* @license http://opensource.org/licenses/gpl-license.php GNU Public License
*
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
* Pretty much a copy of base viewforum.php with a few modifications
*/
function display_phpbbasic_forum_topics()
{
	global $db, $auth, $user, $template, $cache;
	global $phpbb_root_path, $phpEx, $config;
	
	// Start initial var setup
	$mark_read	= request_var('mark', '');
	$start		= request_var('start', 0);
	$forum_id	= request_var('f', (int) $config['phpbbasic_forumid']);

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
		WHERE f.forum_id = $forum_id" . (($forum_id != $config['phpbbasic_forumid']) ? '' : " OR f.parent_id <> " . (int) $config['phpbbasic_forumid']);
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

	// Forum Rules
	if ($auth->acl_get('f_read', $forum_id))
	{
		generate_forum_rules($basic_forum_data);
	}

	if ($forum_id == (int)$config['phpbbasic_forumid'])
	{
		$sql = 'SELECT f.*
					FROM ' . FORUMS_TABLE . ' f
				WHERE f.parent_id = ' . (int) $config['phpbbasic_forumid'] . ' 
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
				'S_LIST_SUBFORUMS'	=> ($row['display_subforum_list']) ? true : false,
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
				(t.forum_id = $forum_id OR f.parent_id" . (($forum_id != $config['phpbbasic_forumid']) ? '' : " OR f.parent_id <> " . (int) $config['phpbbasic_forumid']) . ")
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
			WHERE (t.forum_id = $forum_id" . (($forum_id != $config['phpbbasic_forumid']) ? '' : " OR f.parent_id <> " . (int) $config['phpbbasic_forumid']) . ')' . (($auth->acl_get('m_approve', $forum_id)) ? '' : ' AND t.topic_approved = 1');
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
		'WHERE'		=> '(t.forum_id IN (' . $forum_id . ', 0)' . (($forum_id != $config['phpbbasic_forumid']) ? '' : " OR f.parent_id <> " . (int) $config['phpbbasic_forumid']) . ')
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
			(t.forum_id = $forum_id" . (($forum_id != $config['phpbbasic_forumid']) ? '' : " OR f.parent_id <> " . (int) $config['phpbbasic_forumid']) . ")
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
		'TOTAL_TOPICS'	=> ($topics_count == 1) ? $user->lang['VIEW_FORUM_TOPIC'] : sprintf($user->lang['VIEW_FORUM_TOPICS'], $topics_count))
	);

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

	// This is rather a fudge but it's the best I can think of without requiring information
	// on all topics (as we do in 2.0.x). It looks for unread or new topics, if it doesn't find
	// any it updates the forum last read cookie. This requires that the user visit the forum
	// after reading a topic
	if (sizeof($topic_list) && $mark_forum_read)
	{
		update_forum_tracking_info($forum_id, $basic_forum_data['forum_last_post_time'], false, $mark_time_forum);
	}

}

function phpbbasic_update_forum_row_data(&$row, $phpbbasic_enabled)
{
	if ($phpbbasic_enabled)
	{
		global $db, $config;
		//First get the data
		$sql = 'SELECT *
				FROM ' . FORUMS_TABLE . '
				WHERE forum_id = ' . (int) $config['phpbbasic_forumid'];
		$result = $db->sql_query_limit($sql, 1);
		$basic_forum = $db->sql_fetchrow($result);
		$db->sql_freeresult($result);
		
		//Now update the old row with the new data
		foreach ($basic_forum as $column => $value)
		{
			$row[$column] = $basic_forum[$column];		
		}
		unset($basic_forum);
	}
}

function phpbbasic_overwrite_template_vars($phpbbasic_enabled, &$template, $data, $page)
{
	if ($phpbbasic_enabled)
	{
		switch ($page)
		{
			case 'viewtopic':
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
			break;
		}
	}
}

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

?>