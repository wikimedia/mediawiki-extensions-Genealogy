<?php

namespace MediaWiki\Extension\Genealogy;

class Traverser {

	/** @var callable[] */
	private $callbacks;

	/** @var int */
	private $ancestorDepth = 0;

	/** @var int */
	private $descendantDepth = 0;

	/**
	 * Callbacks will be called for each page crawled.
	 *
	 * @param callable $callback The callable function etc.
	 */
	public function register( $callback ) {
		$this->callbacks[] = $callback;
	}

	/**
	 * Traverse all ancestors.
	 * @param Person $person The person to start at.
	 * @param int|null $depth The height to ascend to.
	 */
	public function ancestors( Person $person, $depth = null ) {
		// Visit this person and their partners.
		$this->visit( $person );
		foreach ( $person->getPartners() as $partner ) {
			$this->visit( $partner );
		}
		// Give up if we're being limited.
		if ( $depth !== null ) {
			$this->ancestorDepth++;
			if ( $this->ancestorDepth >= $depth ) {
				return;
			}
		}
		// Carry on to their ancestors.
		foreach ( $person->getParents() as $parent ) {
			$this->ancestors( $parent, $depth );
		}
	}

	/**
	 * Traverse all descendants.
	 * @param Person $person The person to start at.
	 * @param int|null $depth The depth to descend to.
	 */
	public function descendants( Person $person, $depth = null ) {
		// Visit this person and their partners.
		$this->visit( $person );
		foreach ( $person->getPartners() as $partner ) {
			$this->visit( $partner );
		}
		// Give up if we're being limited.
		if ( $depth !== null ) {
			$this->descendantDepth++;
			if ( $this->descendantDepth >= $depth ) {
				return;
			}
		}
		// Carry on to their descendants.
		foreach ( $person->getChildren() as $child ) {
			$this->descendants( $child, $depth );
		}
	}

	/**
	 * When traversing a tree, each node is 'visited' and its callbacks called.
	 * @param Person $person
	 */
	protected function visit( $person ) {
		// Call each callback
		foreach ( $this->callbacks as $callback ) {
			call_user_func( $callback, $person );
		}
	}

}
