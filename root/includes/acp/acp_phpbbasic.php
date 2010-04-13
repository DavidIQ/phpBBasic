<?php
/**
*
* @package acp
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
* @package acp
*/
class acp_phpbbasic
{
	var $u_action;

	function main($id, $mode)
	{
		global $db, $user, $auth, $template, $cache;
		global $config, $phpbb_root_path, $phpEx;
		include($phpbb_root_path . 'includes/functions_phpbbasic.' . $phpEx);
		include($phpbb_root_path . 'includes/acp/acp_forums.' . $phpEx);
		
		$this->tpl_name = 'acp_phpbbasic';
		$this->page_title = 'ACP_PHPBBASIC_CONFIG';

		$form_name = 'acp_phpbbasic';
		add_form_key($form_name);

		$submit = (isset($_POST['submit'])) ? true : false;
		$phpbbasic_enable = request_var('phpbbasic_enable', (isset($config['phpbbasic_forumid']) && $config['phpbbasic_forumid']));
		$single_forum = request_var('phpbbasic_single_forum', false);
		$errors = $forum_data = array();

		if ($submit)
		{
			if (!check_form_key($form_name))
			{
				trigger_error($user->lang['FORM_INVALID']. adm_back_link($this->u_action), E_USER_WARNING);
			}

			$phpbbasic_forumid = (isset($config['phpbbasic_forumid']) ? (int) $config['phpbbasic_forumid'] : 0);

			$acp_forums = new acp_forums();

			if ($phpbbasic_enable && !$phpbbasic_forumid)
			{
				//Check to see if we already have a forum we want to use
				$use_existing_forum = request_var('use_existing_forum', false);

				if ($use_existing_forum)
				{
					$existing_forum_select = request_var('existing_forum_select', 0);

					//Let's retrieve the existing data first
					$forum_data = $acp_forums->get_forum_info($existing_forum_select);

					//Need to update some of the forum data
					$forum_data += array(
						'parent_id'				=> 0,
						'forum_type'			=> FORUM_POST,
						'type_action'			=> 'type_action',
						'forum_parents'			=> '',
						'forum_link'			=> '',
						'forum_link_track'		=> false,
						'display_subforum_list'	=> false,
						'display_on_index'		=> false,
						'forum_topics_per_page'	=> 0,
						'enable_post_review'	=> true,
						'enable_quick_reply'	=> true,
						'prune_old_polls'		=> false,
						'prune_announce'		=> false,
						'prune_sticky'			=> false,
						'forum_password'		=> '',
						'forum_password_confirm'=> '',
						'forum_password_unset'	=> '',
						'forum_options'			=> 0,
						'display_on_index'		=> false,
						'show_active'			=> true,
					);
				}
				else
				{
					//Need to create the forum
					$forum_data += array(
						'parent_id'				=> 0,
						'forum_type'			=> FORUM_POST,
						'type_action'			=> 'type_action',
						'forum_status'			=> ITEM_UNLOCKED,
						'forum_parents'			=> '',
						'forum_name'			=> utf8_normalize_nfc($user->lang['PHPBBASIC_FORUM']),
						'forum_link'			=> '',
						'forum_link_track'		=> false,
						'forum_desc'			=> utf8_normalize_nfc($user->lang['PHPBBASIC_FORUM_DESC']),
						'forum_desc_uid'		=> '',
						'forum_desc_options'	=> 7,
						'forum_desc_bitfield'	=> '',
						'forum_rules'			=> '',
						'forum_rules_uid'		=> '',
						'forum_rules_options'	=> 7,
						'forum_rules_bitfield'	=> '',
						'forum_rules_link'		=> '',
						'forum_image'			=> '',
						'forum_style'			=> 0,
						'display_subforum_list'	=> false,
						'display_on_index'		=> false,
						'forum_topics_per_page'	=> 0,
						'enable_indexing'		=> true,
						'enable_icons'			=> false,
						'enable_prune'			=> false,
						'enable_post_review'	=> true,
						'enable_quick_reply'	=> true,
						'prune_days'			=> 7,
						'prune_viewed'			=> 7,
						'prune_freq'			=> 1,
						'prune_old_polls'		=> false,
						'prune_announce'		=> false,
						'prune_sticky'			=> false,
						'forum_password'		=> '',
						'forum_password_confirm'=> '',
						'forum_password_unset'	=> '',
						'forum_options'			=> 0,
						'display_on_index'		=> false,
						'show_active'			=> true,
					);
				}

				$errors = $acp_forums->update_forum_data($forum_data);
				
				if (!sizeof($errors))
				{
					$phpbbasic_forumid = (int) $forum_data['forum_id'];
					set_config('phpbbasic_forumid', $phpbbasic_forumid);
					
					$copy_perm_from = request_var('copy_perm_from', 0);
					copy_forum_permissions($copy_perm_from, $phpbbasic_forumid, false);

					//We now delete all other forums if requested
					if ($single_forum)
					{
						$forumlist = array();
						$sql = 'SELECT forum_id
								FROM ' . FORUMS_TABLE . "
								WHERE forum_id <> $phpbbasic_forumid";
						$result = $db->sql_query($sql);
						//Gather a list of all forums
						while ($row = $db->sql_fetchrow($result))
						{
							$forumlist[] = (int) $row['forum_id'];
						}
						$db->sql_freeresult($result);
						//Now we delete each one
						foreach ($forumlist as $forum_id)
						{
							$errors = $acp_forums->delete_forum($forum_id, 'move', 'move', $phpbbasic_forumid, $phpbbasic_forumid);

							if (sizeof($errors))
							{
								trigger_error(implode('<br />', $errors) . adm_back_link($this->u_action), E_USER_WARNING);
							}
						}
						unset($forumlist);
					}
					else
					{
						//We make all existing forums children (sub-forums) of the main forum.
						//Only one level of sub-forums is allowed.
						$sql = 'UPDATE ' . FORUMS_TABLE . "
								SET parent_id = $phpbbasic_forumid,
									forum_parents = '',
									left_id = left_id + 1,
									right_id = right_id + 1
								WHERE forum_id <> $phpbbasic_forumid";
						$result = $db->sql_query($sql);
					}

					//Set the left_id and right_id to proper values
					$sql = 'UPDATE ' . FORUMS_TABLE . "
							SET left_id = 1,
								right_id = right_id + 1
							WHERE forum_id = $phpbbasic_forumid";
					$db->sql_query($sql);

					$cache->destroy('sql', FORUMS_TABLE);
					cache_moderators();
					$auth->acl_clear_prefetch();

					//Re-sync all forums
					sync('forum', 'forum_id', $phpbbasic_forumid, false, true);
					add_log('admin', 'LOG_PHPBBASIC_ENABLED');
					trigger_error($user->lang['PHPBBASIC_ENABLED'] . adm_back_link($this->u_action));
				}
			}
			else if (!$phpbbasic_enable && $phpbbasic_forumid)
			{
				$forumlist = array();
				$sql = 'SELECT forum_id
						FROM ' . FORUMS_TABLE;
				$result = $db->sql_query($sql);
				//Gather a list of all forums
				while ($row = $db->sql_fetchrow($result))
				{
					$forumlist[] = (int) $row['forum_id'];
				}
				$db->sql_freeresult($result);
				//Need to update the left and right ids...boy what a pain...
				$counter = 0;
				foreach ($forumlist as $forum_id)
				{
					$counter++;
					$sql = 'UPDATE ' . FORUMS_TABLE . "
							SET parent_id = 0,
								forum_parents = '',
								left_id = $counter,
								right_id = " . ($counter + 1) . "
							WHERE forum_id = $forum_id";
					$result = $db->sql_query($sql);
					$counter++;
				}
				unset($forumlist);

				$cache->destroy('sql', FORUMS_TABLE);
				cache_moderators();
				$auth->acl_clear_prefetch();

				set_config('phpbbasic_forumid', 0);
				//Re-sync main forum
				sync('forum', 'forum_id', $phpbbasic_forumid, false, true);
				add_log('admin', 'LOG_PHPBBASIC_DISABLED');
				trigger_error($user->lang['PHPBBASIC_DISABLED'] . adm_back_link($this->u_action));
			}
			
		}

		$template->assign_vars(array(
			'U_ACTION'				=> $this->u_action,
			'S_PHPBBASIC_ENABLE'	=> $phpbbasic_enable,
			'S_FORUM_OPTIONS'		=> make_forum_select(0, false, false, false, false),
			'PHPBBASIC_VERSION'		=> $config['phpbbasic_version'],
			'S_ERROR'				=> (sizeof($errors) ? true : false),
			'ERROR_MSG'				=> (sizeof($errors)) ? implode('<br />', $errors) : '',
		));
	}
}

?>