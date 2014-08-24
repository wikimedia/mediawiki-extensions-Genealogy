<?php

class GenealogyCore {

	static function SetupParserFunction(Parser &$parser) {
		$parser->setFunctionHook('genealogy', 'GenealogyCore::RenderParserFunction');
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
	static function RenderParserFunction(Parser $parser) {
		$params = array();
		$args = func_get_args();
		array_shift($args); // Remove $parser
		$type = array_shift($args); // Get param 1, the function type
		foreach ($args as $arg) { // Everything that's left must be named
			$pair = explode('=', $arg, 2);
			if (count($pair) == 2) {
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
				if (isset($params['birth date'])) {
					$out .= 'b.&nbsp;' . $params['birth date'];
					self::SaveProp($parser, 'birth date', $params['birth date'], false);
				}
				if (isset($params['death date'])) {
					$out .= 'd.&nbsp;' . $params['death date'];
					self::SaveProp($parser, 'death date', $params['death date'], false);
				}
				break;
			case 'parent':
				$parentTitle = Title::newFromText($params[0]);
				if ($parentTitle and $parentTitle->exists()) {
					$person = new GenealogyPerson($parentTitle);
					$out .= $person->getWikiLink();
				} else {
					$out .= "[[" . $params[0] . "]]";
				}
				self::SaveProp($parser, 'parent', $params[0]);
				break;
			case 'siblings':
				$person = new GenealogyPerson($parser->getTitle());
				$out .= self::PeopleList($person->getSiblings());
				break;
			case 'partner':
				//$out .= "[[".$params[0]."]]";
				self::SaveProp($parser, 'partner', $params[0]);
				break;
			case 'partners':
				$person = new GenealogyPerson($parser->getTitle());
				$out .= self::PeopleList($person->getPartners());
				break;
			case 'children':
				$person = new GenealogyPerson($parser->getTitle());
				$out .= self::PeopleList($person->getChildren());
				break;
			case 'tree':
				$tree = new GenealogyTree();
				if (isset($params['ancestors'])) {
					$tree->addAncestors(explode("\n", $params['ancestors']));
				}
				//$tree->setAncestorDepth($params['ancestor depth']);
				if (isset($params['descendants'])) {
					$tree->addDescendants(explode("\n", $params['descendants']));
				}
				//$tree->setDescendantDepth($params['descendant depth']);
				$graphviz = $tree->getGraphviz();
				$out .= $parser->recursiveTagParse("<graphviz>\n$graphviz\n</graphviz>");
				break;
			default:
				$out .= '<span class="error">'
					. 'Genealogy parser function type not recognised: "' . $type . '".'
					. '</span>';
				break;
		}
		return $out;
	}

	static function SaveProp($parser, $prop, $val, $multi = true) {
		if ($multi) {
			$propNum = 1;
			while ($par = $parser->getOutput()->getProperty("genealogy $prop $propNum")
					and $par != $val) {
				$propNum++;
			}
			$parser->getOutput()->setProperty("genealogy $prop $propNum", $val);
		} else {
			$parser->getOutput()->setProperty("genealogy $prop", $val);
		}
	}

	/**
	 * Get a wikitext list of people.
	 * @todo Replace with a proper templating system.
	 * @param array|GenealogyPerson $people
	 * @return string
	 */
	static function PeopleList($people) {
		$out = '';
		foreach ($people as $person) {
			$out .= "* " . $person->getWikiLink() . "\n";
		}
		return $out;
	}

}
