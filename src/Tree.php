<?php

namespace MediaWiki\Extensions\Genealogy;

use Title;

class Tree {

	/** @var string[] All the lines of the output GraphViz source code. */
	private $graph_source_code;

	/** @var Person[] */
	protected $ancestors = [];

	/** @var Person[] */
	protected $descendants = [];

	/** @var integer */
	protected $ancestor_depth;

	/** @var integer */
	protected $descendant_depth;

	/**
	 * Set the number of levels the tree will go up to from the ancestors' starting points.
	 * @param integer $ancestor_depth The new ancestor depth.
	 */
	public function setAncestorDepth( $ancestor_depth ) {
		$this->ancestor_depth = $ancestor_depth;
	}

	/**
	 * Set the number of levels the tree will go down to from the descendants' starting points.
	 * @param integer $descendant_depth The new descendant depth.
	 */
	public function setDescendantDepth( $descendant_depth ) {
		$this->descendant_depth = $descendant_depth;
	}

	/**
	 * Add ancestor starting points to this tree, from which to traverse upwards.
	 * @param string[] $ancestors Array of page titles.
	 */
	public function addAncestors( $ancestors ) {
		$this->addAncestorsOrDescendants( 'ancestors', $ancestors );
	}

	/**
	 * Add descendant starting points to this tree, from which to traverse downwards.
	 * @param string[] $descendants Array of page titles.
	 */
	public function addDescendants( $descendants ) {
		$this->addAncestorsOrDescendants( 'descendants', $descendants );
	}

	/**
	 * Add ancestor or descendant starting points to this tree.
	 * @param string $type Either 'ancestors' or 'descendants'.
	 * @param string[] $list Array of page titles.
	 */
	protected function addAncestorsOrDescendants( $type, $list ) {
		foreach ( $list as $a ) {
			$title = Title::newFromText( $a );
			if ( $title ) {
				$person = new Person( $title );
				$this->{$type}[] = $person;
			}
		}
	}

	/**
	 * Whether any ancestors or descendants have been added to this tree.
	 * @return boolean
	 */
	public function hasAncestorsOrDescendants() {
		return 0 < ( count( $this->ancestors ) + count( $this->descendants ) );
	}

	/**
	 * Get the Dot source code for the graph of this tree.
	 * @return string
	 */
	public function getGraphviz() {
		$treeName = md5( join( '', $this->ancestors ) . join( '', $this->descendants ) );
		$this->out( 'top', 'start', "digraph GenealogyTree_$treeName {" );
		$this->out( 'top', 'graph-attrs', 'graph [rankdir=LR]' );
		$this->out( 'top', 'edge-attrs', 'edge [arrowhead=none]' );

		$traverser = new Traverser();
		$traverser->register( [ $this, 'visit' ] );

		foreach ( $this->ancestors as $ancestor ) {
			$traverser->ancestors( $ancestor, $this->ancestor_depth );
		}

		foreach ( $this->descendants as $descendant ) {
			$traverser->descendants( $descendant, $this->descendant_depth );
		}

		// Do nothing if there are no people listed.
		if ( !isset( $this->graph_source_code['person'] ) ) {
			return '<span class="error">No people found</span>';
		}

		// Combine all parts of the graph output.
		$out = join( "\n", $this->graph_source_code['top'] ) . "\n\n"
			. "node [ shape=plaintext ]\n"
			. join( "\n", $this->graph_source_code['person'] ) . "\n\n";
		if ( isset( $this->graph_source_code['partner'] ) ) {
			$out .= join( "\n", $this->graph_source_code['partner'] ) . "\n\n";
		}
		if ( isset( $this->graph_source_code['child'] ) ) {
			$out .= join( "\n", $this->graph_source_code['child'] ) . "\n\n";
		}
		return $out . "}";
	}

	/**
	 * Output one GraphViz line for the given person.
	 * @param Person $person The person.
	 */
	protected function outputPersonLine( Person $person ) {
		$birthYear = $person->getBirthDate();
		$deathYear = $person->getDeathDate();
		if ( !empty( $birthYear ) && !empty( $deathYear ) ) {
			$date = '\n'.$birthYear.' &ndash; '.$deathYear;
		} elseif ( !empty( $birthYear ) ) {
			$date = '\nb.&nbsp;'.$birthYear;
		} elseif ( !empty( $deathYear ) ) {
			$date = '\nd.&nbsp;'.$deathYear;
		} else {
			$date = '';
		}
		if ( $person->getTitle()->exists() ) {
			$url = '[[' . $person->getTitle()->getText() . ']]';
			$colour = 'black';
		} else {
			$queryString = [
				'preload' => wfMessage( 'genealogy-person-preload' ),
				'action' => 'edit',
			];
			$url = '['
				. $person->getTitle()->getFullURL( $queryString )
				. ' ' . $person->getTitle()->getText()
				. ']';
			$colour = 'red';
		}
		$title = $person->getTitle()->getText();
		$personId = $this->esc( $title );
		$line = $personId." ["
			. " label=\"$title$date\", "
			. " URL=\"$url\", "
			. " tooltip=\"$title\", "
			. " fontcolor=\"$colour\" "
			. "]";
		$this->out( 'person', $personId, $line );
	}

	/**
	 * When traversing the tree, each node is visited and this method run on the current person.
	 * @param Person $person The current node's person.
	 */
	public function visit( Person $person ) {
		$this->outputPersonLine( $person );

		$personId = $this->esc( $person->getTitle()->getText() );
		$partnerStyle = 'dashed';

		// Output links to parents.
		if ( $person->getParents() ) {
			$parentsId = $this->esc( join( '', $person->getParents() ) );
			$this->out( 'partner', $parentsId, $parentsId . ' [label="", shape="point"]' );
			$this->out( 'child', $parentsId.$personId, $parentsId . ' -> ' . $personId );
			foreach ( $person->getParents() as $parent ) {
				$parentId = $this->esc( $parent->getTitle()->getText() );
				// Add any non-included parent.
				$this->outputPersonLine( $parent );
				$this->out(
					'partner',
					$parentId.$parentsId,
					$parentId . ' -> ' . $parentsId . " [style=$partnerStyle]"
				);
			}
		}

		// Output links to partners.
		foreach ( $person->getPartners() as $partner ) {
			// Create a point node for each partnership.
			$partnerId = $this->esc( $partner->getTitle()->getDBkey() );
			$partners = [ $personId, $partnerId ];
			sort( $partners );
			$partnersId = $this->esc( join( '', $partners ) );
			$this->out( 'partner', $partnersId, $partnersId.' [label="", shape="point"]' );
			// Link this person and this partner to that point node.
			$personPartnerLine = $personId .' -> '. $partnersId." [style=$partnerStyle]";
			$this->out( 'partner', $personId.$partnersId, $personPartnerLine );
			$partnerLine = $partnerId .' -> '. $partnersId." [style=$partnerStyle]";
			$this->out( 'partner', $partnerId.$partnersId, $partnerLine );
			// Create a node for any non-included partner.
			$this->outputPersonLine( $partner );
		}

		// Output links to children.
		foreach ( $person->getChildren() as $child ) {
			$parentsId = $this->esc( join( '', $child->getParents() ) );
			$this->out( 'partner', $parentsId, $parentsId.' [label="", shape="point"]' );
			$personParentsLine = $personId.' -> '.$parentsId." [style=$partnerStyle]";
			$this->out( 'partner', $personId.$parentsId, $personParentsLine );
			$childId = $this->esc( $child->getTitle()->getDBkey() );
			$this->out( 'child', $parentsId.$childId, $parentsId.' -> ' . $childId );
			// Add this child in case they don't get included directly in this tree.
			$this->outputPersonLine( $child );
		}
	}

	/**
	 * Store a single line of Dot source output. This means we can avoid duplicate output lines,
	 * and also group source by different categories ('partner', 'child', etc.).
	 * @param string $group The group this line should go in.
	 * @param string $key The line's unique key.
	 * @param string $line The line of Dot source code.
	 */
	private function out( $group, $key, $line ) {
		if ( !is_array( $this->graph_source_code ) ) {
			$this->graph_source_code = [];
		}
		if ( !isset( $this->graph_source_code[$group] ) ) {
			$this->graph_source_code[$group] = [];
		}
		$this->graph_source_code[$group][$key] = $line;
	}

	/**
	 * Create a Dot-compatible variable name from any string (replace parentheses, spaces, and
	 * hyphens with underscores).
	 * @todo Complete the disallowed character list.
	 * @param string $title
	 * @return string
	 */
	private function esc( $title ) {
		return strtr( $title, '( )-.', '_____' );
	}

}
