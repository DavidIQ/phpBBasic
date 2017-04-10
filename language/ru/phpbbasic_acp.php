<?php
/**
* phpBBasic extension for the phpBB Forum Software package.
* Russian translation by HD321kbps
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
	'ACP_PHPBBASIC_CONFIG_EXPLAIN'		=> 'Включая phpBBasic создается форум с заголовком "phpBBasic". Вся информация о темах сохраниться на отдельном форуме, если возникает необходимость отключить это расширение.',
	'PHPBBASIC_WARNING'	                => '<strong>ПРЕДУПРЕЖДЕНИЕ:</strong> все предыдущие форумы будут удалены, а записи переместятся в "Главный форум". Это действие НЕ МОЖЕТ быть отменено, и может занять некоторое время, если у вас много форумов.',

	'ENABLE_PHPBBASIC'					=> 'Включить phpBBasic',
	'PHPBBASIC_ENABLED'					=> 'phpBBasic был включен и установлен/создан главный форум.',
	'PHPBBASIC_DISABLED'				=> 'phpBBasic был выключен. Основной форум, используемый до phpBBasic, теперь должен быть видимым.',
	'PHPBBASIC_FORUM'					=> 'Главный форум',
	'PHPBBASIC_FORUM_DESC'				=> 'Этот форум показан на главной странице вместе со всеми темами.',
	'PHPBBASIC_OPTIONS'					=> 'Настройки phpBBasic',

	'COPY_PERMISSIONS_FROM'				=> 'Копировать права доступа из',
	'COPY_PERMISSIONS_FROM_EXPLAIN' 	=> 'Выберите форум, чтобы скопировать права доступа для главный форум.',
));

?>