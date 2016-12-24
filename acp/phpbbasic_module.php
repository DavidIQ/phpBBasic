<?php
/**
* phpBBasic extension for the phpBB Forum Software package.
*
* @copyright (c) 2015 DavidIQ.com
* @license GNU General Public License, version 2 (GPL-2.0)
*/

namespace davidiq\phpbbasic\acp;

class phpbbasic_module
{
    /** @var \phpbb\config\config */
    protected $config;

    /** @var \phpbb\db\driver\driver_interface */
    protected $db;

    /** @var \phpbb\request\request */
    protected $request;

    /** @var \phpbb\template\template */
    protected $template;

    /** @var \phpbb\user */
    protected $user;

    /** @var \phpbb\log\log */
    protected $log;

    /** @var  \phpbb\cache\driver\driver_interface */
    protected $cache;

    /** @var \phpbb\auth\auth */
    protected $auth;

    /** @var string */
    protected $phpbb_root_path;

    /** @var string */
    protected $php_ext;

    /** @var string */
    public $u_action;

	function main($id, $mode)
	{
        global $user, $auth, $cache, $template, $config, $phpbb_container, $request, $phpbb_root_path, $phpEx, $db;

        $this->cache = $cache;
		$this->tpl_name = 'phpbbasic';
		$this->page_title = 'ACP_PHPBBASIC_CONFIG';
		$this->db = $db;
        $this->request = $request;
        $this->template = $template;
        $this->user = $user;
        $this->config = $config;
        $this->phpbb_root_path = $phpbb_root_path;
        $this->php_ext = $phpEx;
        $this->log = $phpbb_container->get('log');
        $this->auth = $auth;

        $this->user->add_lang_ext('davidiq/phpbbasic', 'phpbbasic_acp');

		$form_name = 'phpbbasic_module';
		add_form_key($form_name);

		$phpbbasic_enable = $this->request->variable('phpbbasic_enable', $this->config['phpbbasic_forumid'] > 0);
		$single_forum = $this->request->variable('phpbbasic_single_forum', false);
		$errors = $forum_data = array();

        if ($this->request->is_set_post('submit'))
		{
			if (!check_form_key($form_name))
			{
				trigger_error($this->user->lang('FORM_INVALID'). adm_back_link($this->u_action), E_USER_WARNING);
			}

            $phpbbasic_forumid = (isset($this->config['phpbbasic_forumid']) ? (int) $this->config['phpbbasic_forumid'] : 0);

			include($this->phpbb_root_path . 'includes/acp/acp_forums.' . $this->php_ext);
            $acp_forums = new \acp_forums();

			if ($phpbbasic_enable && !$phpbbasic_forumid)
			{
                //Need to create the forum
                $forum_data = array(
                    'forum_status'			=> ITEM_UNLOCKED,
                    'forum_name'			=> utf8_normalize_nfc($this->user->lang('PHPBBASIC_FORUM')),
                    'forum_desc'			=> utf8_normalize_nfc($this->user->lang('PHPBBASIC_FORUM_DESC')),
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
                    'enable_indexing'		=> true,
                    'enable_icons'			=> false,
                    'enable_prune'			=> false,
                    'prune_days'			=> 7,
                    'prune_viewed'			=> 7,
                    'prune_freq'			=> 1,

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
                    'show_active'			=> true,
                );

				$errors = $acp_forums->update_forum_data($forum_data);
				
				if (!sizeof($errors))
				{
					$phpbbasic_forumid = (int) $forum_data['forum_id'];
					$this->config->set('phpbbasic_forumid', $phpbbasic_forumid);
					
					$copy_perm_from = $this->request->variable('copy_perm_from', 0);
					copy_forum_permissions($copy_perm_from, array($phpbbasic_forumid), false);

                    $forumlist = array();
                    $sql = 'SELECT forum_id
                            FROM ' . FORUMS_TABLE . "
                            WHERE forum_id <> $phpbbasic_forumid";
                    $result = $this->db->sql_query($sql);
                    //Gather a list of all forums
                    while ($row = $this->db->sql_fetchrow($result))
                    {
                        $forumlist[] = (int) $row['forum_id'];
                    }
                    $this->db->sql_freeresult($result);
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

					$this->cache->destroy('sql', FORUMS_TABLE);
					phpbb_cache_moderators($this->db, $this->cache, $this->auth);
					$this->auth->acl_clear_prefetch();

					//Re-sync forum data
					sync('forum', 'forum_id', $phpbbasic_forumid, false, true);
                    $this->log->add('admin', $this->user->data['user_id'], $this->user->ip, 'LOG_PHPBBASIC_ENABLED');
					trigger_error($this->user->lang('PHPBBASIC_ENABLED') . adm_back_link($this->u_action));
				}
			}
			else if (!$phpbbasic_enable && $phpbbasic_forumid)
			{
				$this->cache->destroy('sql', FORUMS_TABLE);
                phpbb_cache_moderators($this->db, $this->cache, $this->auth);
				$this->auth->acl_clear_prefetch();

				$this->config->set('phpbbasic_forumid', 0);
				//Re-sync main forum
				sync('forum', 'forum_id', $phpbbasic_forumid, false, true);
                $this->log->add('admin', $this->user->data['user_id'], $this->user->ip, 'LOG_PHPBBASIC_DISABLED');
				trigger_error($this->user->lang('PHPBBASIC_DISABLED') . adm_back_link($this->u_action));
			}
		}

		$template->assign_vars(array(
			'U_ACTION'				=> $this->u_action,
			'S_PHPBBASIC_ENABLE'	=> $phpbbasic_enable,
			'S_FORUM_OPTIONS'		=> make_forum_select(0, false, false, false, false),
			'S_ERROR'				=> (sizeof($errors) ? true : false),
			'ERROR_MSG'				=> (sizeof($errors)) ? implode('<br />', $errors) : '',
		));
	}
}
