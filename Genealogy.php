<?php

/**
 * Prevent direct execution of this script.
 */
if (!defined('MEDIAWIKI')) die(1);

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
$wgSpecialPages['Genealogy'] = 'GenealogySpecial';

/**
 * Parser function
 */
$wgHooks['ParserFirstCallInit'][] = 'GenealogySetupParserFunction';

function GenealogySetupParserFunction(Parser &$parser) {
	$parser->setFunctionHook('genealogy', 'GenealogyRenderParserFunction');
	return true;
}

/**
 * Render the output of the parser function.
 * The input parameters are wikitext with templates expanded.
 * The output should be wikitext too.
 *
 * @param Parser $parser
 * @param string $type
 * @param string $param2
 * @param string $param3
 * @return string The wikitext with which to replace the parser function call.
 */
function GenealogyRenderParserFunction(Parser $parser) {
	$params = array();
	$args = func_get_args();
	array_shift($args); // Remove $parser
	$type = array_shift($args); // Get param 1, the function type
	foreach ( $args as $arg ) { // Everything that's left must be named
		$pair = explode('=', $arg, 2);
		if ( count( $pair ) == 2 ) {
			$name = trim($pair[0]);
			$value = trim($pair[1]);
			$params[$name] = $value;
		} else {
			$params[] = $arg;
		}
	}
	$out = ''; //"<pre>".print_r($params, true)."</pre>";
	switch ($type) {
		case 'person':
			$out .= 'b.&nbsp;'.$params['birth date'];
			break;
		case 'parent':
			$out .= "[[".$params[0]."]]";
			break;
		case 'siblings':
			$person = new GenealogyPerson($parser->getTitle());
			$out .= GenealogyPeopleList($person->getSiblings());
			break;
		case 'partner':
			$out .= "[[".$params[0]."]]";
			break;
		case 'partners':
			$person = new GenealogyPerson($parser->getTitle());
			$out .= GenealogyPeopleList($person->getPartners());
			break;
		case 'children':
			$person = new GenealogyPerson($parser->getTitle());
			$out .= GenealogyPeopleList($person->getChildren());
			break;
		default:
			$out .= '<span class="error">'
				.'Genealogy parser function type not recognised: "'.$type.'".'
				.'</span>';
			break;
	}
	return $out;
}

/**
 * Get a wikitext list of people.
 * @todo Replace with a proper templating system.
 * @param array|GenealogyPerson $people
 * @return string
 */
function GenealogyPeopleList($people) {
	$out = '';
	foreach ($people as $person) {
		$date = ($person->hasDates()) ? " (".$person->getBirthDate().")" : "";
		$out .= "* [[".$person->getTitle()->getPrefixedText()."]]$date\n";
	}
	return $out;
}
