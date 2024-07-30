<?php

namespace MediaWiki\Extension\Genealogy\Test;

use MediaWiki\Page\WikiPageFactory;
use MediaWikiIntegrationTestCase;
use Title;
use Wikimedia\Rdbms\ILoadBalancer;
use WikiPage;

class GenealogyTestCase extends MediaWikiIntegrationTestCase {

	/** @var ILoadBalancer */
	protected ILoadBalancer $loadBalancer;

	/** @var WikiPageFactory */
	protected WikiPageFactory $wikiPageFactory;

	public function setUp(): void {
		parent::setUp();
		$this->loadBalancer = $this->getServiceContainer()->getDBLoadBalancer();
		$this->wikiPageFactory = $this->getServiceContainer()->getWikiPageFactory();
	}

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
		$page = $this->getServiceContainer()->getWikiPageFactory()->newFromTitle( $title );
		$this->editPage( $page, $wikitext );
		return $page;
	}
}
