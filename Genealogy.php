<?php

/**
 * Prevent direct execution of this script.
 */
if (!defined('MEDIAWIKI')) die(1);

/**
 * Explicit global declarations, for when this is autoloaded by Composer
 */
global $wgExtensionCredits,
		$wgExtensionMessagesFiles,
		$wgAutoloadClasses,
		$wgSpecialPages,
		$wgHooks;

/**
 * Extension metadata
 */
$wgExtensionCredits['parserhook'][] = array(
	'path' => __FILE__,
	'name' => 'Genealogy',
	'author' => "Sam Wilson <[mailto:sam@samwilson.id.au sam@samwilson.id.au]>",
	'url' => "http://www.mediawiki.org/wiki/Extension:Genealogy",
	'descriptionmsg' => 'genealogy-desc',
	'license-name' => 'GPL-3.0+',
	'version' => '0.1.0',
);

/**
 * Messages
 */
$wgExtensionMessagesFiles['Genealogy'] = __DIR__ . '/Genealogy.i18n.php';
$wgExtensionMessagesFiles['GenealogyMagic'] = __DIR__ . '/Genealogy.i18n.magic.php';

/**
 * Class loading and the Special page
 */
$wgAutoloadClasses['Genealogy'] = __FILE__;
$wgAutoloadClasses['GenealogyPerson'] = __DIR__ . '/Person.php';
$wgAutoloadClasses['GenealogySpecial'] = __DIR__ . '/Special.php';
$wgAutoloadClasses['GenealogyCore'] = __DIR__ . '/Core.php';
$wgSpecialPages['Genealogy'] = 'GenealogySpecial';

/**
 * Parser function
 */
$wgHooks['ParserFirstCallInit'][] = 'GenealogyCore::SetupParserFunction';
