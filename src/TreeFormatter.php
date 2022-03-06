<?php

namespace MediaWiki\Extension\Genealogy;

abstract class TreeFormatter {

	/** @var string Unique tree name. */
	protected $name;

	/** @var Person[] */
	protected $ancestors = [];

	/** @var Person[] */
	protected $descendants = [];

	/** @var string[][] */
	protected $out;

	/**
	 * @param Person[] $ancestors
	 * @param Person[] $descendants
	 */
	public function __construct( array $ancestors, array $descendants ) {
		$this->ancestors = $ancestors;
		$this->descendants = $descendants;
	}

	/**
	 * Set the tree name.
	 * @param string $name
	 */
	public function setName( string $name ) {
		$this->name = $name;
	}

	/**
	 * Get the full tree output.
	 * @return string
	 */
	abstract public function getOutput();

	/**
	 * Output tree syntax for a single person.
	 * @param Person $person
	 * @return void
	 */
	abstract protected function outputPerson( Person $person );

	/**
	 * Output tree syntax for the junction of parents or partners.
	 * @param string $peopleId
	 * @return void
	 */
	abstract protected function outputJunction( $peopleId );

	/**
	 * Output syntax for a directed edge.
	 * @param string $group The group this line should go in.
	 * @param string $key The line's unique key.
	 * @param string $from The left-hand side of the arrow.
	 * @param string $to The right-hand side of the arrow.
	 * @param bool $towardsJunction Whether this edge is directed towards a junction.
	 * @return void
	 */
	abstract protected function outputEdge( $group, $key, $from, $to, $towardsJunction = false );

	/**
	 * Create a graph variable name from any string. It will only contain alphanumeric characters
	 * and the underscore.
	 *
	 * @param string $str
	 * @return string
	 */
	protected function varId( $str ) {
		$strTrans = transliterator_transliterate( 'Any-Latin; Latin-ASCII', $str );
		$strTransConv = iconv( 'UTF-8', 'ASCII//TRANSLIT//IGNORE', $strTrans );
		$var = preg_replace( '/[^a-zA-Z0-9_]/', '', str_replace( ' ', '_', $strTransConv ) );
		// Add a unique three-character suffix in case multiple input strings
		// normalize to the same output string above.
		$suffix = '_' . substr( md5( $str ), 0, 3 );
		return $var . $suffix;
	}

	/**
	 * Store a single line of Dot source output. This means we can avoid duplicate output lines,
	 * and also group source by different categories ('partner', 'child', etc.).
	 * @param string $group The group this line should go in.
	 * @param string $key The line's unique key.
	 * @param string $line The line of Dot source code.
	 */
	protected function out( $group, $key, $line ) {
		if ( !is_array( $this->out ) ) {
			$this->out = [];
		}
		if ( !isset( $this->out[$group] ) ) {
			$this->out[$group] = [];
		}
		$this->out[$group][$key] = $line;
	}

	/**
	 * When traversing the tree, each node is visited and this method run on the current person.
	 * @param Person $person The current node's person.
	 */
	public function visit( Person $person ) {
		$this->outputPerson( $person );

		$personId = $person->getTitle()->getPrefixedText();

		// Output links to parents.
		if ( $person->getParents() ) {
			$parentsId = $this->getPersonGroupIdent( $person->getParents() );
			$this->outputJunction( $parentsId );
			$this->outputEdge(
				'child',
				$parentsId . $personId,
				$parentsId,
				$personId
			);
			foreach ( $person->getParents() as $parent ) {
				$parentId = $parent->getTitle()->getPrefixedText();
				// Add any non-included parent.
				$this->outputPerson( $parent );
				$this->outputEdge(
					'partner',
					$parentId . $parentsId,
					$parentId,
					$parentsId,
					true
				);
			}
		}

		// Output links to partners.
		foreach ( $person->getPartners() as $partner ) {
			// Create a point node for each partnership.
			$partnerId = $partner->getTitle()->getPrefixedText();
			$partners = [ $personId, $partnerId ];
			sort( $partners );
			$partnersId = $this->getPersonGroupIdent( $partners );
			$this->outputJunction( $partnersId );
			// Link this person and this partner to that point node.
			$this->outputEdge(
				'partner',
				$personId . $partnersId,
				$personId,
				$partnersId,
				true
			);
			$this->outputEdge(
				'partner',
				$partnerId . $partnersId,
				$partnerId,
				$partnersId,
				true
			);
			// Create a node for any non-included partner.
			$this->outputPerson( $partner );
		}

		// Output links to children.
		foreach ( $person->getChildren() as $child ) {
			$parentsId = $this->getPersonGroupIdent( $child->getParents() );
			$this->outputJunction( $parentsId );
			$this->outputEdge(
				'partner',
				$personId . $parentsId,
				$personId,
				$parentsId,
				true
			);
			$childId = $child->getTitle()->getPrefixedText();
			$this->outputEdge(
				'child',
				$parentsId . $childId,
				$parentsId,
				$childId
			);
			// Add this child in case they don't get included directly in this tree.
			$this->outputPerson( $child );
		}
	}

	/**
	 * Create a node ID for a set of people (i.e. partners, parents, or children).
	 * @param string[]|Person[] $group The people to construct the ID out of.
	 * @return string The node ID, with no wrapping characters.
	 */
	protected function getPersonGroupIdent( $group ) {
		return implode( ' AND ', $group ) . ' (GROUP)';
	}
}
