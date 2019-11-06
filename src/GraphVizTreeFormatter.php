<?php

namespace MediaWiki\Extensions\Genealogy;

use Sanitizer;

class GraphVizTreeFormatter extends TreeFormatter {

	/**
	 * @inheritDoc
	 */
	public function getOutput() {
		// Start the tree.
		$treeName = md5( implode( '', $this->ancestors ) . implode( '', $this->descendants ) );
		$this->out( 'top', 'start', "digraph GenealogyTree_$treeName {" );
		$this->out( 'top', 'graph-attrs', 'graph [rankdir=LR, ranksep=0.55]' );
		$this->out( 'top', 'edge-attrs', 'edge [arrowhead=none, headport=w]' );
		$this->out( 'top', 'node-attrs', 'node [shape=plaintext, fontsize=12]' );

		// Combine all parts of the graph output.
		$out = implode( "\n", $this->out['top'] ) . "\n\n"
			. "/* People */\n"
			. implode( "\n", $this->out['person'] ) . "\n\n";
		if ( isset( $this->out['partner'] ) ) {
			$out .= "/* Partners */\n"
				. implode( "\n", $this->out['partner'] ) . "\n\n";
		}
		if ( isset( $this->out['child'] ) ) {
			$out .= "/* Children */\n"
				. implode( "\n", $this->out['child'] ) . "\n\n";
		}
		return $out . "}\n";
	}

	/**
	 * Output one GraphViz line for the given person.
	 * @param Person $person The person.
	 */
	protected function outputPerson( Person $person ) {
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
		$personId = $this->varId( $title );
		$desc = '';
		if ( $person->getDescription() ) {
			$desc = '<BR/><FONT POINT-SIZE="9">'
				. Sanitizer::stripAllTags( $person->getDescription() )
				. '</FONT>';
		}
		$label = ( $desc === '' && '"' . $title . '"' === $personId ) ? '' : " label=<$title$desc>, ";
		$line = $personId . " ["
			. $label
			. " URL=\"$url\", "
			. " tooltip=\"$title\", "
			. " fontcolor=\"$colour\" "
			. "]";
		$this->out( 'person', $personId, $line );
	}

	/**
	 * @inheritDoc
	 */
	protected function outputJunction( $peopleId ) {
		$this->out( 'partner', $peopleId, $this->varId( $peopleId ) . ' [label="", shape="point"]' );
	}

	/**
	 * @inheritDoc
	 */
	protected function outputEdge( $group, $key, $from, $to, $towardsJunction = false ) {
		$line = $this->varId( $from ) . ' -> ' . $this->varId( $to );
		if ( $towardsJunction ) {
			$line .= " [style=\"dashed\"]";
		}
		$this->out( $group, $key, $line );
	}
}
