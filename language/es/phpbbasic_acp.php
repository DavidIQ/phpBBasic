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
	'ACP_PHPBBASIC_CONFIG_EXPLAIN'		=> 'Habilitando phpBBasic creará un foro con el título “phpBBasic”.  Esto retendrá la información del tema en un foro aparte si fuera necesario para inhabilitar esta Extensión.',
	'PHPBBASIC_WARNING'	                => '<strong>ADVERTENCIA:</strong> Todos los foros anteriores serán eliminados y los mensajes movidos al “Foro Principal”. Esto NO PUEDE ser deshecho, y puede tomar algún tiempo para completar si tiene muchos foros.',

	'ENABLE_PHPBBASIC'					=> 'Habilitar phpBBasic',
	'PHPBBASIC_ENABLED'					=> 'phpBBasic ha sido habilitado y el foro principal establecido/creado.',
	'PHPBBASIC_DISABLED'				=> 'phpBBasic ha sido deshabilitado. Foro principal deusado por phpBBasic ahora no debe ser visible.',
	'PHPBBASIC_FORUM'					=> 'Foro principal',
	'PHPBBASIC_FORUM_DESC'				=> 'Este foro se muestra en la página de índice junto con todos los temas.',
	'PHPBBASIC_OPTIONS'					=> 'Opciones de phpBBasic',

	'COPY_PERMISSIONS_FROM'				=> 'Copiar permisos de',
	'COPY_PERMISSIONS_FROM_EXPLAIN' 	=> 'Seleccione un foro para copiar permisos para hacer que la página principal tenga los mismos permisos que el que seleccionó aquí.',
));
