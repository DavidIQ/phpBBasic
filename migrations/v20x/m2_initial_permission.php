<?php
/**
* phpBBasic extension for the phpBB Forum Software package.
*
* @copyright (c) 2015 DavidIQ.com
* @license GNU General Public License, version 2 (GPL-2.0)
*/

namespace davidiq\phpbbasic\migrations\v20x;

/**
* Migration stage 2: Initial permission
*/
class initial_data extends \phpbb\db\migration\migration
{
	/**
	* Add or update data in the database.
	*
	* @return array Array of table data
	* @access public
	*/
	public function update_data()
	{
		return array(
			array('permission.add', array('a_phpbbasic', true)),
		);
	}
}
