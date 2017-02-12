<?php

namespace MediaWiki\Extensions\Genealogy;

class Traverser {

	private $callbacks;

	private $ancestor_depth = 0;

	private $descendant_depth = 0;

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
	 * @param integer $depth The height to ascend to.
	 */
	public function ancestors( Person $person, $depth = null ) {
		// Visit this person and their partners.
		$this->visit( $person );
		foreach ( $person->getPartners() as $partner ) {
			$this->visit( $partner );
		}
		// Give up if we're being limited.
		if ( $this->ancestor_depth > $depth ) {
			return;
		}
		// Carry on to their ancestors.
		foreach ( $person->getParents() as $parent ) {
			$this->ancestors( $parent );
		}
		$this->ancestor_depth++;
	}

	/**
	 * Traverse all descendants.
	 * @param Person $person The person to start at.
	 * @param integer $depth The depth to descend to.
	 */
	public function descendants( Person $person, $depth = null ) {
		// Visit this person and their partners.
		$this->visit( $person );
		foreach ( $person->getPartners() as $partner ) {
			$this->visit( $partner );
		}
		// Give up if we're being limited.
		if ( $this->descendant_depth > $depth ) {
			return;
		}
		// Carry on to their descendants.
		foreach ( $person->getChildren() as $parent ) {
			$this->descendants( $parent );
		}
		$this->descendant_depth++;
	}

	private function visit( $person ) {
		// Call each callback
		foreach ( $this->callbacks as $callback ) {
			call_user_func( $callback, $person );
		}
	}

}
