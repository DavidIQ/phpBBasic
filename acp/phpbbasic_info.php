<?php
/**
* phpBBasic extension for the phpBB Forum Software package.
*
* @copyright (c) 2015 DavidIQ.com
* @license GNU General Public License, version 2 (GPL-2.0)
*/

namespace davidiq\phpbbasic\acp;

class phpbbasic_info
{
	function module()
	{
		return array(
			'filename'	=> '\davidiq\phpbbasic\acp\phpbbasic_module',
			'title'		=> 'ACP_PHPBBASIC_CONFIG',
			'version'	=> '2.0.0',
			'modes'		=> array(
				'main'		=> array(
						'title' => 'ACP_PHPBBASIC_CONFIG',
						'auth'	=> 'ext_davidiq/phpbbasic',
						'cat' 	=> array('ACP_CAT_PHPBBASIC'),
				),
			),
		);
	}
}
