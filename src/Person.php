<?php

namespace MediaWiki\Extension\Genealogy;

use MediaWiki\MediaWikiServices;
use Title;

class Person {

	/** @var Title */
	private $title;

	/** @var Person[] */
	private $siblings;

	/** @var Person[] */
	private $children;

	/**
	 * Create a new Person based on a page in the wiki.
	 * @param Title $title The page title.
	 */
	public function __construct( Title $title ) {
		$this->title = $title;
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
		$wikiPageFactory = MediaWikiServices::getInstance()->getWikiPageFactory();
		$page = $wikiPageFactory->newFromTitle( $this->title );
		while ( $page->isRedirect() ) {
			$page = $wikiPageFactory->newFromTitle( $page->getRedirectTarget() );
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
		$wikiPageFactory = MediaWikiServices::getInstance()->getWikiPageFactory();
		$page = $wikiPageFactory->newFromTitle( $this->title );
		while ( $page->isRedirect() ) {
			$title = $page->getRedirectTarget();
			$titles[$title->getPrefixedDBkey()] = $title;
			$page = $wikiPageFactory->newFromTitle( $title );
		}
		// Find all the incoming redirects that come here.
		foreach ( $this->title->getRedirectsHere() as $inwardRedirect ) {
			$titles[$inwardRedirect->getPrefixedDBkey()] = $inwardRedirect;
		}
		return $titles;
	}

	/**
	 * Get wikitext for a link to this Person. Non-existent people will get an 'external'-style
	 * link that has the 'preload' parameter added. The dates of birth and death are appended,
	 * outside the link.
	 * @return string The wikitext.
	 */
	public function getWikiLink() {
		$birthYear = $this->getDateYear( $this->getBirthDate() );
		$deathYear = $this->getDateYear( $this->getDeathDate() );
		$dateString = '';
		if ( !empty( $birthYear ) && !empty( $deathYear ) ) {
			$dateString = wfMessage( 'genealogy-born-and-died', $birthYear, $deathYear )->text();
		} elseif ( !empty( $birthYear ) && empty( $deathYear ) ) {
			$dateString = wfMessage( 'genealogy-born', $birthYear )->text();
		} elseif ( empty( $birthYear ) && !empty( $deathYear ) ) {
			$dateString = wfMessage( 'genealogy-died', $deathYear )->text();
		}
		$title = $this->getTitle();
		if ( $title->exists() ) {
			// If it exists, create a link (piping if not in the main namespace).
			$link = $title->inNamespace( NS_MAIN )
				? "[[" . $title->getFullText() . "]]"
				: "[[" . $title->getFullText() . "|" . $title->getText() . "]]";
		} else {
			// If it doesn't exist, create an edit link with a preload parameter.
			$query = [
				'action' => 'edit',
				'preload' => wfMessage( 'genealogy-person-preload' )->text(),
			];
			$url = $title->getFullURL( $query );
			$link = '[' . $url . ' ' . $title->getText() . ']';
		}
		$date = ( $this->hasDates() ) ? " $dateString" : "";
		return $link . $date;
	}

	/**
	 * Whether or not this person has a birth or death date.
	 * @return bool
	 */
	public function hasDates() {
		return $this->getBirthDate() !== false || $this->getDeathDate() !== false;
	}

	/**
	 * Get the birth date of this person.
	 * @return string
	 */
	public function getBirthDate() {
		return $this->getPropSingle( 'birth date' );
	}

	/**
	 * Get the death date of this person.
	 * @return string
	 */
	public function getDeathDate() {
		return $this->getPropSingle( 'death date' );
	}

	/**
	 * Get a year out of a date if possible.
	 * @param string $date The date to parse.
	 * @return string The year as a string, or the full date.
	 */
	public function getDateYear( $date ) {
		preg_match( '/(\d{3,4})/', $date, $matches );
		if ( isset( $matches[1] ) ) {
			return $matches[1];
		}
		return $date;
	}

	/**
	 * Get this person's description.
	 * @return string
	 */
	public function getDescription() {
		$desc = $this->getPropSingle( 'description' );
		if ( !$desc ) {
			$desc = '';
		}
		return $desc;
	}

	/**
	 * Get all parents.
	 * @return Person[] An array of parents, possibly empty.
	 */
	public function getParents() {
		$parents = $this->getPropMulti( 'parent' );
		ksort( $parents );
		return $parents;
	}

	/**
	 * Get all siblings.
	 *
	 * @param bool|null $excludeSelf Whether to excluding this person from the list.
	 * @return Person[] An array of siblings, possibly empty.
	 */
	public function getSiblings( ?bool $excludeSelf = false ) {
		if ( !is_array( $this->siblings ) ) {
			$this->siblings = [];
			$descriptions = [];
			foreach ( $this->getParents() as $parent ) {
				foreach ( $parent->getChildren() as $child ) {
					$key = $child->getTitle()->getPrefixedDBkey();
					$descriptions[ $key ] = $child->getDescription();
					$this->siblings[ $key ] = $child;
				}
			}
			array_multisort( $descriptions, $this->siblings );
		}
		if ( $excludeSelf ) {
			unset( $this->siblings[ $this->getTitle()->getPrefixedDBkey() ] );
		}
		return $this->siblings;
	}

	/**
	 * Get all partners (optionally excluding those that are defined within the current page).
	 * @param bool $onlyDefinedElsewhere Only return those partners that are *not* defined
	 * within this Person's page.
	 * @return Person[] An array of partners, possibly empty. Keyed by the partner's page DB key.
	 */
	public function getPartners( $onlyDefinedElsewhere = false ) {
		$partners = $this->getPropInbound( 'partner' );
		if ( $onlyDefinedElsewhere === false ) {
			$partners = array_merge( $partners, $this->getPropMulti( 'partner' ) );
		}
		ksort( $partners );
		return $partners;
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
	 * @param string $type The property type.
	 * @return Person[] Keyed by the prefixed DB key.
	 */
	protected function getPropInbound( $type ) {
		$dbr = wfGetDB( DB_REPLICA );
		$tables = [ 'pp' => 'page_props', 'p' => 'page' ];
		$columns = [ 'pp_value', 'page_title', 'page_namespace' ];

		$where = [
			'pp_value' => $this->getTitles(),
			'pp_propname' . $dbr->buildLike( 'genealogy ', $type . ' ', $dbr->anyString() ),
			'pp_page = page_id',
		];
		$results = $dbr->select( $tables, $columns, $where, __METHOD__, [], [ 'page' => [] ] );
		$out = [];
		foreach ( $results as $res ) {
			$title = Title::newFromText( $res->page_title, $res->page_namespace );
			$person = new Person( $title );
			$out[$person->getTitle()->getPrefixedDBkey()] = $person;
		}
		return $out;
	}

	/**
	 * Get the value of a single-valued page property.
	 * @param string $prop The property.
	 * @return string|bool The property value, or false if not found.
	 */
	public function getPropSingle( $prop ) {
		$dbr = wfGetDB( DB_REPLICA );
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
		$dbr = wfGetDB( DB_REPLICA );
		$articleIds = [];
		foreach ( $this->getTitles() as $t ) {
			$articleIds[] = $t->getArticleID();
		}
		$results = $dbr->select(
			// Table to use.
			'page_props',
			// Field to select.
			'pp_value',
			[
				// Where conditions.
				'pp_page' => $articleIds,
				'pp_propname' . $dbr->buildLike( 'genealogy ', $type . ' ', $dbr->anyString() ),
			],
			__METHOD__,
			[ 'ORDER BY' => 'pp_value' ]
		);
		foreach ( $results as $result ) {
			$title = Title::newFromText( $result->pp_value );
			if ( $title === null ) {
				// Do nothing, if this isn't a valid title.
				continue;
			}
			$person = new Person( $title );
			$out[$person->getTitle()->getPrefixedDBkey()] = $person;
		}
		return $out;
	}
}
