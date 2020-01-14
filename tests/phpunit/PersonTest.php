<?php

namespace MediaWiki\Extensions\Genealogy\Test;

use MediaWiki\Extensions\Genealogy\Person;
use Title;
use WikiPage;
use WikitextContent;

/**
 * @group extensions
 * @group Genealogy
 * @covers \MediaWiki\Extensions\Genealogy\Person
 */
class PersonTest extends GenealogyTestCase {

	public function testEmptyDescription() {
		$this->setPageContent( 'DescTest', '{{#genealogy:description}}' );
		$person = new Person( Title::newFromText( 'DescTest' ) );
		$this->assertSame( '', $person->getDescription() );
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
		$this->assertSame( [ 'Charles' ], array_keys( $dianna->getPartners() ) );
		$elizabeth = new Person( Title::newFromText( 'Elizabeth' ) );
		$this->assertSame( [ 'Charles' ], array_keys( $elizabeth->getChildren() ) );

		// Then edit the first page to remove the partner.
		$wikiText2 = '{{#genealogy:parent|Elizabeth}}{{#genealogy:partner|Bob}}';
		$this->setPageContent( 'Charles', $wikiText2 );
		$this->assertSame( [ 'Bob' ], array_keys( $charles->getPartners() ) );
		$this->assertEmpty( $dianna->getPartners() );
	}

	public function testChildren() {
		$wikiText = '{{#genealogy:parent|Alice}}';
		$this->setPageContent( 'Bob', $wikiText );
		// Add one child in a different namespace, to confirm that there's no issue with that.
		$this->setPageContent( 'Help:Carly', $wikiText );
		$alice = new Person( Title::newFromText( 'Alice' ) );
		$this->assertSame( [ 'Bob', 'Help:Carly' ], array_keys( $alice->getChildren() ) );
	}

	public function testDates() {
		$person = new Person( Title::newFromText( 'Will' ) );
		$this->assertSame( '', $person->getDateYear( '' ) );
		$this->assertSame( '1804', $person->getDateYear( '1804' ) );
		$this->assertSame( '2014', $person->getDateYear( '2014-10-01' ) );
		$this->assertSame( '2014', $person->getDateYear( '1 September 2014' ) );
		$this->assertSame( '1803', $person->getDateYear( 'June 1803' ) );
		$this->assertSame( '1890', $person->getDateYear( 'c. 1890' ) );
	}

	public function testParentsInAlphabeticalOrder() {
		$alice = new Person( Title::newFromText( 'Alice' ) );
		$this->setPageContent( 'Alice', '{{#genealogy:parent|Clara}}{{#genealogy:parent|Bob}}' );
		$parents = $alice->getParents();
		$this->assertSame( [ 'Bob', 'Clara' ], array_keys( $parents ) );
	}

	public function testPartnersInAlphabeticalOrder() {
		$this->setPageContent( 'P1', '{{#genealogy:partner|P2}}{{#genealogy:partner|P3}}' );
		$this->setPageContent( 'P4', '{{#genealogy:partner|P1}}' );
		$personA = new Person( Title::newFromText( 'P1' ) );
		$this->assertSame( [ 'P2', 'P3', 'P4' ], array_keys( $personA->getPartners() ) );
	}

	/**
	 * A ╤ B
	 * ┌─┼─┐
	 * C D E
	 */
	public function testSiblingsInDescriptionOrder() {
		$this->setPageContent( 'DA', '{{#genealogy:partner|DB}}' );
		$this->setPageContent( 'DB', '' );
		$parents = '{{#genealogy:parent|DA}}{{#genealogy:parent|DB}}';
		$this->setPageContent( 'DC', "$parents{{#genealogy:description|1. first}}" );
		$this->setPageContent( 'DD', "$parents{{#genealogy:description|3. third}}" );
		$this->setPageContent( 'DE', "$parents{{#genealogy:description|2. second}}" );
		$c = new Person( Title::newFromText( 'DC' ) );
		$this->assertSame( '1. first', $c->getDescription() );
		$this->assertSame( [ 'DC', 'DE', 'DD' ], array_keys( $c->getSiblings() ) );
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
		$this->assertSame( 'Diana', $charles->getPartners()['Diana']->getTitle()->getText() );
		// Redirect Diana to Dianna.
		$diannaPage->doEditContent( new WikitextContent( "#REDIRECT [[Dianna]]" ), 'Redirecting' );
		$diana = new Person( Title::newFromText( 'Diana' ) );
		$this->assertSame( 'Dianna', $diana->getTitle()->getText() );
		$this->assertSame( [ 'Diana', 'Dianna' ], array_keys( $diana->getTitles() ) );
		// Check that Charles and Dianna have the expected partners.
		$this->assertCount( 1, $charles->getPartners() );
		$this->assertSame( 'Dianna', $charles->getPartners()['Dianna']->getTitle()->getText() );
		$this->assertCount( 1, $diana->getPartners() );
		$this->assertSame( 'Charles', $diana->getPartners()['Charles']->getTitle()->getText() );
		// Then redirect Charles and check everything again.
		$charlesPage->doEditContent( new WikitextContent( "#REDIRECT [[King Charles]]" ), '' );
		$kingChPage = new WikiPage( Title::newFromText( 'King Charles' ) );
		$kingChPage->doEditContent( new WikitextContent( '{{#genealogy:partner|Diana}}' ), '' );
		$this->assertSame( [ 'Charles', 'King_Charles' ], array_keys( $charles->getTitles() ) );
		$this->assertCount( 1, $charles->getPartners() );
		$this->assertSame( 'Dianna', $charles->getPartners()['Dianna']->getTitle()->getText() );
		$this->assertCount( 1, $diana->getPartners() );
		$this->assertSame(
			'King Charles',
			$diana->getPartners()['King_Charles']->getTitle()->getText()
		);
		// Redirect Charles again, and make sure all is okay.
		$kingChPage->doEditContent( new WikitextContent( '#REDIRECT [[King Charles III]]' ), '' );
		$kingCh3Page = new WikiPage( Title::newFromText( 'King Charles III' ) );
		$kingCh3Page->doEditContent( new WikitextContent( '{{#genealogy:partner|Diana}}' ), '' );
		$this->assertSame( 'King_Charles_III', $charles->getTitle()->getPrefixedDBkey() );
		$this->assertSame(
			[ 'Charles', 'King_Charles', 'King_Charles_III' ],
			array_keys( $charles->getTitles() )
		);
		$this->assertCount( 1, $charles->getPartners() );
		$this->assertSame( 'Dianna', $charles->getPartners()['Dianna']->getTitle()->getText() );
		$this->assertCount( 1, $diana->getPartners() );
		$this->assertSame(
			'King Charles III',
			$diana->getPartners()['King_Charles_III']->getTitle()->getText()
		);
	}
}
