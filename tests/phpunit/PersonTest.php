<?php
use MediaWiki\Extensions\Genealogy\Person;

/**
 * @group extensions
 * @group Genealogy
 * @covers \MediaWiki\Extensions\Genealogy\Person
 */
class PersonTest extends MediaWikiTestCase {

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

	public function testCreatePerson() {
		$charlesTitle = Title::newFromText( 'Charles' );
		$wikiText1 = '{{#genealogy:parent|Elizabeth}}{{#genealogy:partner|Dianna}}';
		$this->setPageContent( 'Charles', $wikiText1 );
		$charles = new Person( $charlesTitle );
		$this->assertCount( 1, $charles->getPartners() );

		// Create the partner page, and check that all's as it should be.
		$this->setPageContent( 'Dianna', 'Lorem' );
		$dianna = new Person( Title::newFromText( 'Dianna' ) );
		$this->assertEquals( [ 'Charles' ], array_keys( $dianna->getPartners() ) );
		$elizabeth = new Person( Title::newFromText( 'Elizabeth' ) );
		$this->assertEquals( [ 'Charles' ], array_keys( $elizabeth->getChildren() ) );

		// Then edit the first page to remove the partner.
		$wikiText2 = '{{#genealogy:parent|Elizabeth}}{{#genealogy:partner|Bob}}';
		$this->setPageContent( 'Charles', $wikiText2 );
		$this->assertEquals( [ 'Bob' ], array_keys( $charles->getPartners() ) );
		$this->assertEmpty( $dianna->getPartners() );
	}

	public function testChildren() {
		$wikiText = '{{#genealogy:parent|Alice}}';
		$this->setPageContent( 'Bob', $wikiText );
		$this->setPageContent( 'Carly', $wikiText );
		$alice = new Person( Title::newFromText( 'Alice' ) );
		$this->assertEquals( [ 'Bob', 'Carly' ], array_keys( $alice->getChildren() ) );
	}

	public function testDates() {
		$person = new Person( Title::newFromText( 'Will' ) );
		$this->assertEquals( false, $person->getDateYear( '' ) );
		$this->assertEquals( '1804', $person->getDateYear( '1804' ) );
		$this->assertEquals( '2014', $person->getDateYear( '2014-10-01' ) );
		$this->assertEquals( '2014', $person->getDateYear( '1 September 2014' ) );
		$this->assertEquals( '1803', $person->getDateYear( 'June 1803' ) );
		$this->assertEquals( '1890', $person->getDateYear( 'c. 1890' ) );
	}

	public function testParentsInAlphabeticalOrder() {
		$alice = new Person( Title::newFromText( 'Alice' ) );
		$this->setPageContent( 'Alice', '{{#genealogy:parent|Clara}}{{#genealogy:parent|Bob}}' );
		$parents = $alice->getParents();
		$this->assertEquals( [ 'Bob', 'Clara' ], array_keys( $parents ) );
	}

	public function testPartnersInAlphabeticalOrder() {
		$alice = new Person( Title::newFromText( 'Alice' ) );
		$this->setPageContent( 'Alice', '{{#genealogy:parent|Clara}}{{#genealogy:parent|Bob}}' );
		$parents = $alice->getParents();
		$this->assertEquals( [ 'Bob', 'Clara' ], array_keys( $parents ) );
	}

	public function testRedirectPartner() {
		// Create Charles.
		$charlesTitle = Title::newFromText( 'Charles' );
		$charlesPage = new WikiPage( $charlesTitle );
		$charlesPage->doEditContent( new WikitextContent( '{{#genealogy:partner|Diana}}' ), '' );
		$charles = new Person( $charlesTitle );
		// Create Diana and made sure she's Charles' partner.
		$diannaTitle = Title::newFromText( 'Diana' );
		$diannaPage = new WikiPage( $diannaTitle );
		$diannaPage->doEditContent( new WikitextContent( "Dianna" ), '' );
		$this->assertEquals( 'Diana', $charles->getPartners()['Diana']->getTitle() );
		// Redirect Diana to Dianna.
		$diannaPage->doEditContent( new WikitextContent( "#REDIRECT [[Dianna]]" ), 'Redirecting' );
		$diana = new Person( Title::newFromText( 'Diana' ) );
		$this->assertEquals( 'Dianna', $diana->getTitle()->getText() );
		$this->assertEquals( [ 'Diana', 'Dianna' ], array_keys( $diana->getTitles() ) );
		// Check that Charles and Dianna have the expected partners.
		$this->assertCount( 1, $charles->getPartners() );
		$this->assertEquals( 'Dianna', $charles->getPartners()['Dianna']->getTitle() );
		$this->assertCount( 1, $diana->getPartners() );
		$this->assertEquals( 'Charles', $diana->getPartners()['Charles']->getTitle() );
		// Then redirect Charles and check everything again.
		$charlesPage->doEditContent( new WikitextContent( "#REDIRECT [[King Charles]]" ), '' );
		$kingChPage = new WikiPage( Title::newFromText( 'King Charles' ) );
		$kingChPage->doEditContent( new WikitextContent( '{{#genealogy:partner|Diana}}' ), '' );
		$this->assertEquals( [ 'Charles', 'King_Charles' ], array_keys( $charles->getTitles() ) );
		$this->assertCount( 1, $charles->getPartners() );
		$this->assertEquals( 'Dianna', $charles->getPartners()['Dianna']->getTitle() );
		$this->assertCount( 1, $diana->getPartners() );
		$this->assertEquals(
			'King Charles',
			$diana->getPartners()['King_Charles']->getTitle()->getText()
		);
		// Redirect Charles again, and make sure all is okay.
		$kingChPage->doEditContent( new WikitextContent( '#REDIRECT [[King Charles III]]' ), '' );
		$kingCh3Page = new WikiPage( Title::newFromText( 'King Charles III' ) );
		$kingCh3Page->doEditContent( new WikitextContent( '{{#genealogy:partner|Diana}}' ), '' );
		$this->assertEquals( 'King_Charles_III', $charles->getTitle()->getPrefixedDBkey() );
		$this->assertEquals(
			[ 'Charles', 'King_Charles', 'King_Charles_III' ],
			array_keys( $charles->getTitles() )
		);
		$this->assertCount( 1, $charles->getPartners() );
		$this->assertEquals( 'Dianna', $charles->getPartners()['Dianna']->getTitle() );
		$this->assertCount( 1, $diana->getPartners() );
		$this->assertEquals(
			'King Charles III',
			$diana->getPartners()['King_Charles_III']->getTitle()->getText()
		);
	}
}
