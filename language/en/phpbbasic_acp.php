<?php
/**
* phpBBasic extension for the phpBB Forum Software package.
*
* @copyright (c) 2015 DavidIQ.com
* @license GNU General Public License, version 2 (GPL-2.0)
*/

/**
* DO NOT CHANGE
*/
if (!defined('IN_PHPBB'))
{
	exit;
}

if (empty($lang) || !is_array($lang))
{
	$lang = array();
}

// DEVELOPERS PLEASE NOTE
//
// All language files should use UTF-8 as their encoding and the files must not contain a BOM.
//
// Placeholders can now contain order information, e.g. instead of
// 'Page %s of %s' you can (and should) write 'Page %1$s of %2$s', this allows
// translators to re-order the output of data while ensuring it remains correct
//
// You do not need this where single placeholders are used, e.g. 'Message %d' is fine
// equally where a string contains only two placeholders which are used to wrap text
// in a url you again do not need to specify an order e.g., 'Click %sHERE%s' is fine

$lang = array_merge($lang, array(
	'ACP_PHPBBASIC_CONFIG_EXPLAIN'		=> 'Enabling phpBBasic will create a forum with the title “Main Forum”.  This will retain topic information in a separate forum should the need arise to disable this Extension.',
	'PHPBBASIC_WARNING'	                => '<strong>WARNING:</strong> all previous forums will be removed and posts moved to “Main Forum”. This CANNOT be undone and may take some time to complete if you have many forums.',

	'ENABLE_PHPBBASIC'					=> 'Enable phpBBasic',
	'PHPBBASIC_ENABLED'					=> 'phpBBasic has been enabled and main forum set/created.',
	'PHPBBASIC_DISABLED'				=> 'phpBBasic has been disabled.  Main forum used for phpBBasic should now visible.',
	'PHPBBASIC_FORUM'					=> 'Main Forum',
	'PHPBBASIC_FORUM_DESC'				=> 'This forum is shown on the index page along with all topics.',
	'PHPBBASIC_OPTIONS'					=> 'phpBBasic Options',

	'COPY_PERMISSIONS_FROM'				=> 'Copy permissions from',
	'COPY_PERMISSIONS_FROM_EXPLAIN' 	=> 'Select a forum to copy permissions from making the main page have the same permissions as the one you select here.',
));

