<?php

namespace MediaWiki\Extension\Genealogy;

use ExtensionRegistry;
use Html;
use Parser;
use Title;

class Tree {

	/** @var string The tree's format, either 'graphviz' or 'mermaid'. */
	protected $format = 'graphviz';

	/** @var Person[] */
	protected $ancestors = [];

	/** @var Person[] */
	protected $descendants = [];

	/** @var int */
	protected $ancestorDepth;

	/** @var int */
	protected $descendantDepth;

	/**
	 * Set the number of levels the tree will go up to from the ancestors' starting points.
	 * @param int $ancestorDepth The new ancestor depth.
	 */
	public function setAncestorDepth( $ancestorDepth ) {
		$this->ancestorDepth = (int)$ancestorDepth;
	}

	/**
	 * Set the number of levels the tree will go down to from the descendants' starting points.
	 * @param int $descendantDepth The new descendant depth.
	 */
	public function setDescendantDepth( $descendantDepth ) {
		$this->descendantDepth = (int)$descendantDepth;
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
	 * @return bool
	 */
	public function hasAncestorsOrDescendants() {
		return ( count( $this->ancestors ) + count( $this->descendants ) ) > 0;
	}

	/**
	 * Set the output format for the tree.
	 *
	 * @param string $format Either 'graphviz' or 'mermaid' (case insensitive).
	 * @return void
	 */
	public function setFormat( $format ) {
		$this->format = strtolower( $format );
	}

	/**
	 * Get the wikitext output for the tree.
	 *
	 * @param Parser $parser The parser.
	 * @return string Unsafe half-parsed HTML, as returned by Parser::recursiveTagParse().
	 */
	public function getWikitext( Parser $parser ) {
		// If there's nothing to render, give up.
		if ( !$this->hasAncestorsOrDescendants() ) {
			return '';
		}

		$extenstionRegistry = ExtensionRegistry::getInstance();
		$diagramsInstalled = $extenstionRegistry->isLoaded( 'Diagrams' );
		$graphvizInstalled = $extenstionRegistry->isLoaded( 'GraphViz' )
			|| $diagramsInstalled;
		$mermaidInstalled = $extenstionRegistry->isLoaded( 'Mermaid' );
		$treeSource = $this->getTreeSource();
		if ( $this->format === 'mermaid' && $mermaidInstalled ) {
			$wikitext = "{{#mermaid:$treeSource|config.flowchart.useMaxWidth=0|config.theme=neutral}}";
			$out = $parser->recursiveTagParse( $wikitext );
		} elseif ( $this->format === 'mermaid' && $diagramsInstalled ) {
			$out = $parser->recursiveTagParse( "<mermaid>$treeSource</mermaid>" );
		} elseif ( $this->format === 'graphviz' && $graphvizInstalled ) {
			$out = $parser->recursiveTagParse( "<graphviz>$treeSource</graphviz>" );
		} else {
			$err = wfMessage( 'genealogy-invalid-tree-format', $this->format )->text();
			return Html::element( 'p', [ 'class' => 'error' ], $err );
		}

		// Debugging.
		// $out .= $parser->recursiveTagParse( "<pre>$treeSource</pre>" );

		return $out;
	}

	/**
	 * @return string
	 */
	public function getTreeSource() {
		$traverser = new Traverser();
		$formatter = $this->format === 'mermaid'
			? new MermaidTreeFormatter( $this->ancestors, $this->descendants )
			: new GraphVizTreeFormatter( $this->ancestors, $this->descendants );
		$formatter->setName( md5(
			implode( '', $this->ancestors )
			. implode( '', $this->descendants )
			. $this->ancestorDepth
			. $this->descendantDepth
		) );
		$traverser->register( [ $formatter, 'visit' ] );

		foreach ( $this->ancestors as $ancestor ) {
			$traverser->ancestors( $ancestor, $this->ancestorDepth );
		}

		foreach ( $this->descendants as $descendant ) {
			$traverser->descendants( $descendant, $this->descendantDepth );
		}

		return $formatter->getOutput();
	}
}
