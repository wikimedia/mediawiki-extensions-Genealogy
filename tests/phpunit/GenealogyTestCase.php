<?php

namespace MediaWiki\Extensions\Genealogy\Test;

use MediaWikiTestCase;
use Title;
use WikiPage;
use WikitextContent;

class GenealogyTestCase extends MediaWikiTestCase {

	/**
	 * Set the wikitext contents of a test page.
	 * @param string|Title $title The title of the page.
	 * @param string $wikitext The page contents.
	 * @return WikiPage
	 */
	protected function setPageContent( $title, $wikitext ) {
		if ( is_string( $title ) ) {
			$title = Title::newFromText( $title );
		}
		$page = new WikiPage( $title );
		$page->doEditContent( new WikitextContent( $wikitext ), '' );
		return $page;
	}
}
