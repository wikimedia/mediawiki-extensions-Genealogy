<?php

namespace MediaWiki\Extensions\Genealogy\Test;

use MediaWiki\Extensions\Genealogy\Tree;

/**
 * @group extensions
 * @group Genealogy
 * @covers \MediaWiki\Extensions\Genealogy\Tree
 */
class TreeTest extends GenealogyTestCase {

	public function testDescriptionsWithRedirects() {
		$this->setPageContent( 'DescA', '#REDIRECT [[DescB]]' );
		$this->setPageContent(
			'DescB',
			'{{#genealogy:description|A&nbsp;description with <span>HTML</span>}}'
		);
		$tree = new Tree();
		$tree->addDescendants( [ 'DescA' ] );
		$this->assertContains(
			'"DescB" [ label=<DescB<BR/><FONT POINT-SIZE="9">A description with HTML</FONT>>,',
			$tree->getGraphvizSource()
		);
	}

	/**
	 * A
	 * |
	 * B  C = D
	 *  \/
	 *  E = F
	 *  |
	 *  G
	 */
	public function testGraphVizTree() {
		$this->setPageContent( 'A', '' );
		$this->setPageContent( 'B', '{{#genealogy:parent|A}}' );
		$this->setPageContent( 'C', '{{#genealogy:partner|D}}' );
		$this->setPageContent( 'D', '' );
		$this->setPageContent( 'E',
			'{{#genealogy:partner|F}}{{#genealogy:parent|B}}{{#genealogy:parent|C}}'
		);
		$this->setPageContent( 'F', '' );
		$this->setPageContent( 'G', '{{#genealogy:parent|E}}' );
		$tree1 = new Tree();
		$tree1->addDescendants( [ 'A' ] );
		$tree1->setDescendantDepth( 1 );
		$this->assertContains(
			'
/* People */
"A" [ URL="[[A]]",  tooltip="A",  fontcolor="black" ]
"B" [ URL="[[B]]",  tooltip="B",  fontcolor="black" ]
"E" [ URL="[[E]]",  tooltip="E",  fontcolor="black" ]

/* Partners */
"A (GROUP)" [label="", shape="point"]
"A" -> "A (GROUP)" [style=dashed]
"B AND C (GROUP)" [label="", shape="point"]
"B" -> "B AND C (GROUP)" [style=dashed]

/* Children */
"A (GROUP)" -> "B"
"B AND C (GROUP)" -> "E"
',
			$tree1->getGraphvizSource()
		);

		$tree2 = new Tree();
		$tree2->addAncestors( [ 'G' ] );
		$tree2->setAncestorDepth( 2 );
		$this->assertContains(
			'
/* People */
"G" [ URL="[[G]]",  tooltip="G",  fontcolor="black" ]
"E" [ URL="[[E]]",  tooltip="E",  fontcolor="black" ]
"B" [ URL="[[B]]",  tooltip="B",  fontcolor="black" ]
"C" [ URL="[[C]]",  tooltip="C",  fontcolor="black" ]
"F" [ URL="[[F]]",  tooltip="F",  fontcolor="black" ]
"A" [ URL="[[A]]",  tooltip="A",  fontcolor="black" ]
"D" [ URL="[[D]]",  tooltip="D",  fontcolor="black" ]

/* Partners */
"E (GROUP)" [label="", shape="point"]
"E" -> "E (GROUP)" [style=dashed]
"B AND C (GROUP)" [label="", shape="point"]
"B" -> "B AND C (GROUP)" [style=dashed]
"C" -> "B AND C (GROUP)" [style=dashed]
"E AND F (GROUP)" [label="", shape="point"]
"E" -> "E AND F (GROUP)" [style=dashed]
"F" -> "E AND F (GROUP)" [style=dashed]
"A (GROUP)" [label="", shape="point"]
"A" -> "A (GROUP)" [style=dashed]
"C AND D (GROUP)" [label="", shape="point"]
"C" -> "C AND D (GROUP)" [style=dashed]
"D" -> "C AND D (GROUP)" [style=dashed]

/* Children */
"E (GROUP)" -> "G"
"B AND C (GROUP)" -> "E"
"A (GROUP)" -> "B"
',
			$tree2->getGraphvizSource()
		);
	}
}
