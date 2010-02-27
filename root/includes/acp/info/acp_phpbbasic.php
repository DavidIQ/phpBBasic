<?php
/**
*
* @package acp
* @copyright (c) 2010 DavidIQ.com
* @license http://opensource.org/licenses/gpl-license.php GNU Public License
*
*/

/**
* @package module_install
*/
class acp_phpbbasic_info
{
	function module()
	{
		return array(
			'filename'	=> 'acp_phpbbasic',
			'title'		=> 'ACP_PHPBBASIC_CONFIG',
			'version'	=> '1.0.0',
			'modes'		=> array(
				'main'		=> array(
						'title' => 'ACP_PHPBBASIC_CONFIG',
						'auth'	=> 'acl_a_phpbbasic',
						'cat' 	=> array('ACP_CAT_PHPBBASIC'),
				),
			),
		);
	}

	function install()
	{
	}

	function uninstall()
	{
	}
}

?>