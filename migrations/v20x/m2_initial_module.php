<?php
/**
* phpBBasic extension for the phpBB Forum Software package.
*
* @copyright (c) 2015 DavidIQ.com
* @license GNU General Public License, version 2 (GPL-2.0)
*/

namespace davidiq\phpbbasic\migrations\v20x;

/**
* Migration stage 3: Initial module
*/
class m2_initial_module extends \phpbb\db\migration\migration
{
	/**
	* Add or update data in the database
	*
	* @return array Array of table data
	* @access public
	*/
	public function update_data()
	{
		return array(
			array('module.add', array('acp', 'ACP_CAT_DOT_MODS', 'ACP_CAT_PHPBBASIC')),
			array('module.add', array(
				'acp', 'ACP_CAT_PHPBBASIC', array(
					'module_basename'	=> '\davidiq\phpbbasic\acp\phpbbasic_module',
					'modes'				=> array('main'),
				),
			)),
		);
	}
}
