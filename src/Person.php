<?php

namespace MediaWiki\Extensions\Genealogy;

use Linker;
use MagicWord;
use MediaWiki\Linker\LinkRenderer;
use Parser;
use Title;
use WikiPage;

class Person {

	/** @var Title */
	private $title;

	/** @var Person[] */
	private $parents;

	/** @var Person[] */
	private $siblings;

	/** @var Person[] */
	private $partners;

	/** @var Person[] */
	private $children;

	/**
	 * Create a new Person based on a page in the wiki.
	 * @param Title $title
	 */
	public function __construct( Title $title ) {
		$this->title = $title;
		$this->magicRegex = MagicWord::get( 'genealogy' )->getBaseRegex();
	}

	/**
	 * Get some basic info about this person.
	 * @todo Add dates.
	 * @return string
	 */
	public function __toString() {
		return $this->getTitle()->getPrefixedText();
	}

	/**
	 * Get this person's wiki title, following redirects (to any depth) when present.
	 *
	 * @return Title
	 */
	public function getTitle() {
		$page = WikiPage::factory( $this->title );
		if ( $page->isRedirect() ) {
			return $page->getRedirectTarget();
		}
		return $page->getTitle();
	}

	/**
	 * Get all Titles that refer to this Person (i.e. all redirects both inward and outward, and
	 * the actual Title).
	 * @return Title[] An array of the Titles, some of which might not actually exist, keyed by the
	 * prefixed DB key.
	 */
	public function getTitles() {
		$titles = [ $this->title->getPrefixedDBkey() => $this->title ];
		// Find all the outgoing redirects that leave from here.
		$page = WikiPage::factory( $this->title );
		while ( $page->isRedirect() ) {
			$title = $page->getRedirectTarget();
			$titles[$title->getPrefixedDBkey()] = $title;
			$page = WikiPage::factory( $title );
		}
		// Find all the incoming redirects that come here.
		foreach ( $this->title->getRedirectsHere() as $inwardRedirect ) {
			$titles[$inwardRedirect->getPrefixedDBkey()] = $inwardRedirect;
		}
		return $titles;
	}

	/**
	 * Get wikitext for a link to this Person; non-existant people will have the preload
	 * parameter added.
	 * @return string The wikitext.
	 */
	public function getWikiLink() {
		$birthYear = $this->getDateYear( $this->getBirthDate() );
		$deathYear = $this->getDateYear( $this->getDeathDate() );
		$dateString = '';
		if ( !empty( $birthYear ) && !empty( $deathYear ) ) {
			$dateString = "($birthYear&ndash;$deathYear)";
		} elseif ( !empty( $birthYear ) && empty( $deathYear ) ) {
			$dateString = "(".wfMessage( 'genealogy-born' )->text()."&nbsp;$birthYear)";
		} elseif ( empty( $birthYear ) && !empty( $deathYear ) ) {
			$dateString = "(".wfMessage( 'genealogy-died' )->text()."&nbsp;$deathYear)";
		}
		$date = ( $this->hasDates() ) ? " $dateString" : "";
		if ( $this->getTitle()->exists() ) {
			return "[[" . $this->getTitle()->getFullText() . "]]$date";
		} else {
			$query = [
				'action' => 'edit',
				'preload' => wfMessage( 'genealogy-person-preload' )->text(),
			];
			$url = $this->getTitle()->getFullURL( $query );
			return '[' . $url . ' ' . $this->getTitle()->getFullText() . ']';
		}
	}

	/**
	 * Whether or not this person has a birth or death date.
	 * @return boolean
	 */
	public function hasDates() {
		return $this->getBirthDate() !== false || $this->getDeathDate() !== false;
	}

	/**
	 * Get the birth date of this person.
	 * @return string
	 */
	public function getBirthDate() {
		return $this->getPropSingle( "birth date" );
	}

	/**
	 * Get the death date of this person.
	 * @return string
	 */
	public function getDeathDate() {
		return $this->getPropSingle( "death date" );
	}

	/**
	 * Get a year out of a date if possible.
	 * @param string $date
	 * @return string The year as a string, or the full date.
	 */
	public function getDateYear( $date ) {
// 	if (empty($rawDate)) {
// 		return false;
// 	}
// 	try {
// 		$date = new DateTime($rawDate);
// 		return $date->format('Y');
// 	} catch (Exception $e) {
// 		echo $e->getMessage();
// 		return $date;
// 	}
		preg_match( '/(\d{4})/', $date, $matches );
		if ( isset( $matches[1] ) ) {
			return $matches[1];
		} else {
			return $date;
		}
	}

	/**
	 * Get all parents.
	 *
	 * @return Person[] An array of parents, possibly empty.
	 */
	public function getParents() {
		$parents = $this->getPropMulti( 'parent' );
		return $parents;
	}

	/**
	 * Get all siblings.
	 *
	 * @return Person[] An array of siblings, possibly empty.
	 */
	public function getSiblings() {
		if ( is_array( $this->siblings ) ) {
			return $this->siblings;
		}
		$this->siblings = [];
		foreach ( $this->getParents() as $parent ) {
			foreach ( $parent->getChildren() as $child ) {
				$this->siblings[$child->getTitle()->getPrefixedDBkey()] = $child;
			}
		}
		return $this->siblings;
	}

	/**
	 * Get all partners (optionally excluding those that are defined within the current page).
	 * @param boolean $onlyDefinedElsewhere Only return those partners that are *not* defined
	 * within this Person's page.
	 * @return Person[] An array of partners, possibly empty. Keyed by the partner's page DB key.
	 */
	public function getPartners( $onlyDefinedElsewhere = false ) {
		if ( $onlyDefinedElsewhere === true ) {
			return $this->getPropInbound( 'partner' );
		}
		$this->partners = array_merge(
			$this->getPropInbound( 'partner' ),
			$this->getPropMulti( 'partner' )
		);
		return $this->partners;
	}

	/**
	 * Get all children.
	 * @return Person[] An array of children, possibly empty, keyed by the prefixed DB key.
	 */
	public function getChildren() {
		$this->children = $this->getPropInbound( 'parent' );
		return $this->children;
	}

	/**
	 * Find people with properties that are equal to one of this page's titles.
	 * @param string $type
	 * @return Person[] Keyed by the prefixed DB key.
	 */
	protected function getPropInbound( $type ) {
		$dbr = wfGetDB( DB_SLAVE );
		$tables = [ 'pp'=>'page_props', 'p'=>'page' ];
		$columns = [ 'pp_value', 'page_title' ];

		$where = [
			'pp_value' => $this->getTitles(),
			'pp_propname' . $dbr->buildLike( 'genealogy ', $type.' ', $dbr->anyString() ),
			'pp_page = page_id',
		];
		$results = $dbr->select( $tables, $columns, $where, __METHOD__, [], [ 'page'=>[] ] );
		$out = [];
		foreach ( $results as $res ) {
			$title = Title::newFromText( $res->page_title );
			$person = new Person( $title );
			$out[$person->getTitle()->getPrefixedDBkey()] = $person;
		}
		return $out;
	}

	public function getPropSingle( $prop ) {
		$dbr = wfGetDB( DB_SLAVE );
		$where = [
			'pp_page' => $this->getTitle()->getArticleID(),
			'pp_propname' => "genealogy $prop"
		];
		return $dbr->selectField( 'page_props', 'pp_value', $where, __METHOD__ );
	}

	/**
	 * Get a multi-valued relationship property of this Person.
	 * @param string $type The property name ('genealogy ' will be prepended).
	 * @return Person[] The related people.
	 */
	protected function getPropMulti( $type ) {
		$out = [];
		$dbr = wfGetDB( DB_SLAVE );
		$articleIds = [];
		foreach ( $this->getTitles() as $t ) {
			$articleIds[] = $t->getArticleID();
		}
		$results = $dbr->select(
				'page_props', // table to use
				'pp_value', // Field to select
			[ // where conditions
				'pp_page' => $articleIds,
				'pp_propname' . $dbr->buildLike( 'genealogy ', $type.' ', $dbr->anyString() ),
			],
			__METHOD__,
			[ 'ORDER BY' => 'pp_value' ]
		);
		foreach ( $results as $result ) {
			$title = Title::newFromText( $result->pp_value );
			if ( is_null( $title ) ) {
				// Do nothing, if this isn't a valid title.
				continue;
			}
			$person = new Person( $title );
			$out[$person->getTitle()->getPrefixedDBkey()] = $person;
		}
		return $out;
	}
}
