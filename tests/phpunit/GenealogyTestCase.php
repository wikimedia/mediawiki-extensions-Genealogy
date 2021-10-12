<?php

namespace MediaWiki\Extensions\Genealogy\Test;

use MediaWikiIntegrationTestCase;
use Title;
use WikiPage;

class GenealogyTestCase extends MediaWikiIntegrationTestCase {

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
		$this->editPage( $page, $wikitext );
		return $page;
	}
}
