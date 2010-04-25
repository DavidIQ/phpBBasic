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

function hook_validate_forum()
{
	global $user, $config, $db, $phpEx, $phpbb_root_path, $phpbb_hook;

	// We only want this to run once
	$phpbb_hook->remove_hook('phpbb_user_session_handler');

	if (!isset($config['phpbbasic_forumid']) || !$config['phpbbasic_forumid'])
	{
		return;
	}

	$forum_id = request_var('f', 0);

	//Need to make sure this is a valid forum ID
	if ($forum_id > 0 && $forum_id != (int) $config['phpbbasic_forumid'])
	{
		$sql = 'SELECT forum_id FROM ' . FORUMS_TABLE . "
				WHERE forum_id = $forum_id
				AND parent_id = {$config['phpbbasic_forumid']}";
		$result = $db->sql_query_limit($sql, 1);
		if (!$db->sql_fetchrow($result))
		{
			$forum_id = false;
		}
		$db->sql_freeresult($result);
	}

	//Do the redirect if this is viewforum or if forum ID is invalid
	if ($user->page['page_name'] == "viewforum.$phpEx" || $forum_id === false)
	{
		//Need the querystring
		$qs = str_replace('&amp;', '&', $user->page['query_string']);
		$qs = explode('&', $qs);
		$query_string = '';

		foreach ($qs as $qs_key)
		{
			$qs_key = explode('=', $qs_key);
			if ($qs_key[1] == '' || $qs_key[0] == 'sid' || ($qs_key[0] == 'f' && !($forum_id > 0)))
			{
				continue;
			}

			//Override the forum ID if present
			if ($qs_key[0] == 'f')
			{
				$qs_key[1] = $forum_id;
			}
			$query_string .= (($query_string != '') ? '&amp;' : '') . $qs_key[0] . '=' . $qs_key[1];
		}
		
		if ($forum_id > 0)
		{
			redirect(append_sid("{$phpbb_root_path}index.$phpEx", "f=$forum_id"));
		}
		else
		{
			redirect(append_sid("{$phpbb_root_path}index.$phpEx"));
		}
	}
}

$phpbb_hook->register('phpbb_user_session_handler', 'hook_validate_forum');

/**
 * @package phpBB
 * @copyright (c) 2010 Chris Smith
 * @license http://sam.zoy.org/wtfpl/COPYING

 * This program is free software. It comes without any warranty, to
 * the extent permitted by applicable law. You can redistribute it
 * and/or modify it under the terms of the Do What The Fuck You Want
 * To Public License, Version 2, as published by Sam Hocevar. See
 * http://sam.zoy.org/wtfpl/COPYING for more details.
 */
/**
 * This hook disables the delayed redirects used by phpBB.
 *
 * @author Chris Smith <toonarmy@phpbb.com>
 * @param phpbb_hook $hook
 * @return void
 */
function hook_disable_delayed_redirects(&$hook)
{
	global $template, $user, $config, $phpEx;

	//Added for phpBBasic
	if (!isset($config['phpbbasic_forumid']) || !$config['phpbbasic_forumid'])
	{
		return;
	}

	if (!isset($template->_rootref['MESSAGE_TEXT']) || !isset($template->_rootref['META']))
	{
		return;
	}

	//'<meta http-equiv="refresh" content="' . $time . ';url=' . $url . '" />')
	if (preg_match('#<meta http-equiv="refresh" content="[0-9]+;url=(.+?)" />#', $template->_rootref['META'], $match))
	{
		// HTML entitied
		$url = str_replace('&amp;', '&', $match[1]);

		// Don't want anyone in viewforum - phpBBasic edit
		$url = str_replace("viewforum.$phpEx", "index.$phpEx", $url);

		// Show messages from pages that return to the same page,
		// otherwise there is no feedback that anything changed
		// which makes the UCP preferences and other places seem
		// to be broken.
		if (generate_board_url() . '/' . $user->page['page'] !== $url)
		{
			redirect($url);
			exit; // Implicit
		}
	}
}

/**
 * Only register the hook for normal pages, not administration pages.
 */
if (!defined('ADMIN_START'))
{
	$phpbb_hook->register(array('template', 'display'), 'hook_disable_delayed_redirects');
}