<?php

namespace MediaWiki\Extension\Genealogy;

class MermaidTreeFormatter extends TreeFormatter {

	/**
	 * @inheritDoc
	 */
	protected function outputPerson( Person $person ) {
		$id = $this->varId( $person->getTitle()->getPrefixedText() );
		$this->out( 'person', $id, $id . '("' . $person->getTitle()->getText() . '")' );
		$this->out( 'person', $id . '_c', "click $id \"" . $person->getTitle()->getInternalURL() . '"' );
	}

	/**
	 * @inheritDoc
	 */
	protected function outputJunction( $peopleId ) {
		$this->out( 'partner', $peopleId, $this->varId( $peopleId ) . '{" "}' );
	}

	/**
	 * @inheritDoc
	 */
	protected function outputEdge( $group, $key, $from, $to, $towardsJunction = false ) {
		$line = $this->varId( $from ) . ' --> ' . $this->varId( $to );
		$this->out( $group, $key, $line );
	}

	/**
	 * @inheritDoc
	 */
	public function getOutput() {
		$out = "graph LR;\n\n"
			. "%% People\n"
			. implode( ";\n", $this->out['person'] ) . ";\n\n";
		if ( isset( $this->out['partner'] ) ) {
			$out .= "%% Partners\n"
				. implode( ";\n", $this->out['partner'] ) . ";\n\n";
		}
		if ( isset( $this->out['child'] ) ) {
			$out .= "%% Children\n"
				. implode( ";\n", $this->out['child'] ) . ";\n\n";
		}
		return $out;
	}
}
