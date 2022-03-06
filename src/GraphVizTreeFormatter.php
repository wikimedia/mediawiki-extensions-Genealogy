<?php

namespace MediaWiki\Extension\Genealogy;

use Sanitizer;

class GraphVizTreeFormatter extends TreeFormatter {

	/**
	 * @inheritDoc
	 */
	public function getOutput() {
		// Start the tree.
		$this->out( 'top', 'start', "digraph GenealogyTree_$this->name {" );
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
			$url = '[[' . $person->getTitle()->getPrefixedText() . ']]';
			$colour = 'black';
		} else {
			$queryString = [
				'preload' => wfMessage( 'genealogy-person-preload' ),
				'action' => 'edit',
			];
			$url = '[' . $person->getTitle()->getFullURL( $queryString ) . ']';
			$colour = 'red';
		}
		$personId = $this->varId( $person->getTitle()->getPrefixedText() );
		$desc = '';
		if ( $person->getDescription() ) {
			$desc = '<BR/><FONT POINT-SIZE="9">'
				. Sanitizer::stripAllTags( $person->getDescription() )
				. '</FONT>';
		}
		$title = $person->getTitle()->getText();
		$line = $personId . " ["
			. " label=<$title$desc>, "
			. " URL=" . $this->quoteValue( $url ) . ", "
			. " tooltip=" . $this->quoteValue( $person->getTitle()->getPrefixedText() ) . ", "
			. " fontcolor=\"$colour\" "
			. "]";
		$this->out( 'person', $personId, $line );
	}

	/**
	 * Quote and escape a string.
	 * The GraphViz docs say: In quoted strings in DOT, the only escaped character is double-quote (").
	 * That is, in quoted strings, the dyad \" is converted to "; all other characters are left unchanged.
	 * In particular, \\ remains \\. Layout engines may apply additional escape sequences.
	 * @param string $val
	 * @return string The escaped value, with surrounding quotes.
	 */
	private function quoteValue( string $val ): string {
		return '"' . str_replace( '"', '\"', $val ) . '"';
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
