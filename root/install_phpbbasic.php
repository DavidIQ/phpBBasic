<?php
/**
*
* @author DavidIQ (David Colon) davidiq@phpbb.com
* @package umil
* @copyright (c) 2010 DavidIQ
* @license http://opensource.org/licenses/gpl-license.php GNU Public License
*
*/

/**
* @ignore
*/
define('UMIL_AUTO', true);
define('IN_PHPBB', true);
$phpbb_root_path = (defined('PHPBB_ROOT_PATH')) ? PHPBB_ROOT_PATH : './';
$phpEx = substr(strrchr(__FILE__, '.'), 1);
include($phpbb_root_path . 'common.' . $phpEx);
$user->session_begin();
$auth->acl($user->data);
$user->setup();

if (!file_exists($phpbb_root_path . 'umil/umil_auto.' . $phpEx))
{
	trigger_error('Please download the latest UMIL (Unified MOD Install Library) from: <a href="http://www.phpbb.com/mods/umil/">phpBB.com/mods/umil</a>', E_USER_ERROR);
}

$mod_name = 'phpBBasic';
$version_config_name = 'phpbbasic_version';
$language_file = 'mods/info_acp_phpbbasic';

$versions = array(
	'0.0.1'	=> array(
		'config_add' => array(
			array('phpbbasic_forumid', 0),
		),

		'permission_add' => array(
			array('a_phpbbasic', true),
		),

		'module_add' => array(
			array('acp', 'ACP_CAT_DOT_MODS', 'ACP_CAT_PHPBBASIC'),
			
			array('acp', 'ACP_CAT_PHPBBASIC', array(
					'module_basename'		=> 'phpbbasic',
					'module_langname'		=> 'ACP_PHPBBASIC_CONFIG',
					'modes'					=> 'main',
					'module_auth'			=> 'acl_a_phpbbasic',
				),
			),
		),
	),
	'0.0.2' => array(),
	'0.0.3' => array(),
	'1.0.0-RC1' => array(),
	'1.0.0' => array(),
);

include($phpbb_root_path . 'umil/umil_auto.' . $phpEx);

?>