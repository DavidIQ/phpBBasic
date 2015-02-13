<?php
/**
* phpBBasic extension for the phpBB Forum Software package.
*
* @copyright (c) 2015 DavidIQ.com
* @license GNU General Public License, version 2 (GPL-2.0)
*/

namespace davidiq\phpbbasic\event;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
* Event listener
*/
class listener implements EventSubscriberInterface
{
	/** @var \phpbb\config\config */
	protected $config;

	/** @var string phpEx */
	protected $php_ext;

	/** @var string phpbb_root_path */
	protected $phpbb_root_path;

	/**
	* Constructor
	*
	* @param \phpbb\config\config        	$config             Config object
	* @param string							$phpbb_root_path	Current phpBB root path
	* @param string							$php_ext			phpEx
	* @return \davidiq\phpbbasic\event\listener
	* @access public
	*/
	public function __construct(\phpbb\config\config $config, $phpbb_root_path, $php_ext)
	{
		$this->config = $config;
		$this->phpbb_root_path = $phpbb_root_path;
		$this->php_ext = $php_ext;
	}

	/**
	* Assign functions defined in this class to event listeners in the core
	*
	* @return array
	* @static
	* @access public
	*/
	static public function getSubscribedEvents()
	{
		return array(
			'core.notification_manager_add_notifications'	=> 'display_single_forum',
		);
	}

	/**
	* Displays a single forum as configured with phpBBasic
	*
	* @param object $event The event object
	* @return null
	* @access public
	*/
	public function display_single_forum($event)
	{

	}
}
