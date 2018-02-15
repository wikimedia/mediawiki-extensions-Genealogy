<?php

namespace MediaWiki\Extensions\Genealogy;

use EditPage;
use Html;
use MediaWiki\MediaWikiServices;
use OutputPage;
use Parser;
use Title;

class Hooks {

	/**
	 * Hooked to ParserFirstCallInit.
	 * @param Parser &$parser The parser.
	 * @return bool
	 */
	public static function onParserFirstCallInit( Parser &$parser ) {
		$parser->setFunctionHook( 'genealogy', self::class.'::renderParserFunction' );
		return true;
	}

	/**
	 * This method is called by the EditPage::showEditForm:initial hook and adds a list of the
	 * current page's Genealogy partners that *are not* a result of a {{#genealogy:partner|…}} call
	 * in the current page.
	 * @param EditPage &$editPage The current page that's being edited.
	 * @param OutputPage &$output The output.
	 * @return void
	 */
	public static function onEditPageShowEditFormInitial( EditPage &$editPage, OutputPage &$output ) {
		$person = new Person( $editPage->getTitle() );
		$peopleList = [];
		$renderer = MediaWikiServices::getInstance()->getLinkRenderer();
		foreach ( $person->getPartners( true ) as $partner ) {
			$peopleList[] = $renderer->makeKnownLink( $partner->getTitle() );
		}
		if ( count( $peopleList ) > 0 ) {
			$msg = $output->msg( 'genealogy-existing-partners', count( $peopleList ) );
			$partnersMsg = $msg . implode( ', ', $peopleList );
			$output->addHTML( Html::rawElement( 'p', [], $partnersMsg ) );
		}
	}

	/**
	 * Render the output of the parser function.
	 * The input parameters are wikitext with templates expanded.
	 * The output should be wikitext too.
	 * @param Parser $parser The parser.
	 * @return string|mixed[] The wikitext with which to replace the parser function call.
	 */
	public static function renderParserFunction( Parser $parser ) {
		$params = [];
		$args = func_get_args();
		// Remove $parser from the args.
		array_shift( $args );
		// Get param 1, the function type.
		$type = array_shift( $args );
		// Everything that remains is required to be named (i.e. we discard other unnamed args).
		foreach ( $args as $arg ) {
			$pair = explode( '=', $arg, 2 );
			if ( count( $pair ) == 2 ) {
				$name = trim( $pair[0] );
				$value = trim( $pair[1] );
				$params[$name] = $value;
			} else {
				$params[] = $arg;
			}
		}
		$out = '';
		$isHtml = false;
		switch ( $type ) {
			case 'person':
				if ( isset( $params['birth date'] ) ) {
					$out .= $params['birth date'];
					self::saveProp( $parser, 'birth date', $params['birth date'], false );
				}
				if ( isset( $params['death date'] ) ) {
					$out .= $params['death date'];
					self::saveProp( $parser, 'death date', $params['death date'], false );
				}
				break;
			case 'parent':
				$parentTitle = Title::newFromText( $params[0] );
				if ( !$parentTitle instanceof Title ) {
					$invalidTitle = '<nowiki>' . $params[0] . '</nowiki>';
					$isHtml = true;
					$msg = wfMessage( 'genealogy-invalid-parent-title', $invalidTitle );
					$out .= "<span class='error'>$msg</span>";
				} else {
					$parent = new Person( $parentTitle );
					$out .= $parent->getWikiLink();
					self::saveProp( $parser, 'parent', $parentTitle );
				}
				break;
			case 'siblings':
				$person = new Person( $parser->getTitle() );
				$out .= self::peopleList( $parser, $person->getSiblings() );
				break;
			case 'partner':
				$partnerTitle = Title::newFromText( $params[0] );
				if ( !$partnerTitle instanceof Title ) {
					$invalidTitle = '<nowiki>' . $params[0] . '</nowiki>';
					$isHtml = true;
					$msg = wfMessage( 'genealogy-invalid-partner-title', $invalidTitle );
					$out .= "<span class='error'>$msg</span>";
				} else {
					self::saveProp( $parser, 'partner', $partnerTitle );
				}
				break;
			case 'partners':
				$person = new Person( $parser->getTitle() );
				$out .= self::peopleList( $parser, $person->getPartners() );
				break;
			case 'children':
				$person = new Person( $parser->getTitle() );
				$out .= self::peopleList( $parser, $person->getChildren() );
				break;
			case 'tree':
				$tree = new Tree();
				if ( isset( $params['ancestors'] ) ) {
					$tree->addAncestors( explode( "\n", $params['ancestors'] ) );
				}
				if ( isset( $params['ancestor depth'] ) ) {
					$tree->setAncestorDepth( $params['ancestor depth'] );
				}
				if ( isset( $params['descendants'] ) ) {
					$tree->addDescendants( explode( "\n", $params['descendants'] ) );
				}
				if ( isset( $params['descendant depth'] ) ) {
					$tree->setDescendantDepth( $params['descendant depth'] );
				}
				$out = $tree->getWikitext( $parser );
				break;
			default:
				$msg = wfMessage( 'genealogy-parser-function-not-found', [ $type ] );
				$out .= "<span class='error'>$msg</span>";
				break;
		}
		// Return format is documented in Parser::setFunctionHook().
		return $isHtml ? [ 0 => $out, 'isHTML' => true ] : $out;
	}

	/**
	 * Save a page property.
	 * @param Parser $parser The parser object.
	 * @param string $prop The property name; it will be prefixed with 'genealogy '.
	 * @param string|Title $val The property value ('full text' will be used if this is a Title).
	 * @param bool $multi Whether this property can have multiple values (will be stored as
	 * multiple properties, with an integer appended to their name.
	 */
	public static function saveProp( Parser $parser, $prop, $val, $multi = true ) {
		$output = $parser->getOutput();
		$valString = ( $val instanceof Title ) ? $val->getFullText() : $val;
		if ( $multi ) {
			// Figure out what number we're up to for this property.
			$propNum = 1;
			$propVal = $output->getProperty( "genealogy $prop $propNum" );
			while ( $propVal !== false && $propVal !== $valString ) {
				$propNum++;
				$propVal = $output->getProperty( "genealogy $prop $propNum" );
			}
			// Save the property.
			$output->setProperty( "genealogy $prop $propNum", $valString );
		} else {
			// A single-valued property.
			$output->setProperty( "genealogy $prop", $valString );
		}
		// For page-linking properties, add the referenced page as a dependency for this page.
		// https://www.mediawiki.org/wiki/Manual:Tag_extensions#How_do_I_disable_caching_for_pages_using_my_extension.3F
		if ( $val instanceof Title ) {
			// Register the dependency in templatelinks table.
			$output->addTemplate( $val, $val->getArticleID(), $val->getLatestRevID() );
		}
	}

	/**
	 * Get a wikitext list of siblings, partners, or children.
	 * @param Parser $parser The parser to use to render each line.
	 * @param Person[] $people The people to list.
	 * @return string Wikitext list of people.
	 */
	public static function peopleList( Parser $parser, $people ) {
		$templateName = wfMessage( 'genealogy-person-list-item' );
		$out = '';
		$index = 1;
		$peopleCount = count( $people );
		$templateExists = Title::newFromText( $templateName->toString() )->exists();
		foreach ( $people as $person ) {
			if ( $templateExists ) {
				$template = '{{' . $templateName
					. '|title=' . $person->getTitle()->getFullText()
					. '|link=' . $person->getWikiLink()
					. '|index=' . $index
					. '|count=' . $peopleCount
					. '}}';
				$out .= $parser->recursivePreprocess( $template );
				$index ++;
			} else {
				$out .= "* " . $person->getWikiLink() . "\n";
			}
		}
		return $out;
	}

}
