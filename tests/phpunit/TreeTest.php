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
			'DescB_269 [ label=<DescB<BR/><FONT POINT-SIZE="9">A description with HTML</FONT>>,',
			$tree->getTreeSource()
		);
	}

	/**
	 * Help:A
	 *  |
	 *  B  C = D
	 *   \/
	 *   E = F
	 *   |
	 *   G
	 */
	public function testGraphVizTree() {
		$this->setPageContent( 'Help:A', '' );
		$this->setPageContent( 'B', '{{#genealogy:parent|Help:A}}' );
		$this->setPageContent( 'C', '{{#genealogy:partner|D}}' );
		$this->setPageContent( 'D', '' );
		$this->setPageContent( 'E',
			'{{#genealogy:partner|F}}{{#genealogy:parent|B}}{{#genealogy:parent|C}}'
		);
		$this->setPageContent( 'F', '' );
		$this->setPageContent( 'G', '{{#genealogy:parent|E}}' );
		$tree1 = new Tree();
		$tree1->addDescendants( [ 'Help:A' ] );
		$tree1->setDescendantDepth( 1 );
		$this->assertContains(
			'
/* People */
HelpA_c04 [ label=<A>,  URL="[[Help:A]]",  tooltip="Help:A",  fontcolor="black" ]
B_9d5 [ label=<B>,  URL="[[B]]",  tooltip="B",  fontcolor="black" ]
E_3a3 [ label=<E>,  URL="[[E]]",  tooltip="E",  fontcolor="black" ]

/* Partners */
HelpA_GROUP_930 [label="", shape="point"]
HelpA_c04 -> HelpA_GROUP_930 [style="dashed"]
B_AND_C_GROUP_533 [label="", shape="point"]
B_9d5 -> B_AND_C_GROUP_533 [style="dashed"]

/* Children */
HelpA_GROUP_930 -> B_9d5
B_AND_C_GROUP_533 -> E_3a3

}
',
			$tree1->getTreeSource()
		);

		$tree2 = new Tree();
		$tree2->addAncestors( [ 'G' ] );
		$tree2->setAncestorDepth( 2 );
		$this->assertContains(
			'
/* People */
G_dfc [ label=<G>,  URL="[[G]]",  tooltip="G",  fontcolor="black" ]
E_3a3 [ label=<E>,  URL="[[E]]",  tooltip="E",  fontcolor="black" ]
B_9d5 [ label=<B>,  URL="[[B]]",  tooltip="B",  fontcolor="black" ]
C_0d6 [ label=<C>,  URL="[[C]]",  tooltip="C",  fontcolor="black" ]
F_800 [ label=<F>,  URL="[[F]]",  tooltip="F",  fontcolor="black" ]
HelpA_c04 [ label=<A>,  URL="[[Help:A]]",  tooltip="Help:A",  fontcolor="black" ]
D_f62 [ label=<D>,  URL="[[D]]",  tooltip="D",  fontcolor="black" ]

/* Partners */
E_GROUP_e46 [label="", shape="point"]
E_3a3 -> E_GROUP_e46 [style="dashed"]
B_AND_C_GROUP_533 [label="", shape="point"]
B_9d5 -> B_AND_C_GROUP_533 [style="dashed"]
C_0d6 -> B_AND_C_GROUP_533 [style="dashed"]
E_AND_F_GROUP_88a [label="", shape="point"]
E_3a3 -> E_AND_F_GROUP_88a [style="dashed"]
F_800 -> E_AND_F_GROUP_88a [style="dashed"]
HelpA_GROUP_930 [label="", shape="point"]
HelpA_c04 -> HelpA_GROUP_930 [style="dashed"]
C_AND_D_GROUP_a81 [label="", shape="point"]
C_0d6 -> C_AND_D_GROUP_a81 [style="dashed"]
D_f62 -> C_AND_D_GROUP_a81 [style="dashed"]

/* Children */
E_GROUP_e46 -> G_dfc
B_AND_C_GROUP_533 -> E_3a3
HelpA_GROUP_930 -> B_9d5
',
			$tree2->getTreeSource()
		);
	}

	/**
	 *  A2   B2
	 *   \/
	 *   |
	 *   C2
	 */
	public function testMermaidTree() {
		$a2 = $this->setPageContent( 'A2', '' );
		$baseUrl = substr( $a2->getTitle()->getFullURL(), 0, -3 );
		$this->setPageContent( 'C2', '{{#genealogy:parent|A2}}{{#genealogy:parent|B2}}' );
		$tree1 = new Tree();
		$tree1->setFormat( 'mermaid' );
		$tree1->addDescendants( [ 'A2' ] );
		$tree1->setDescendantDepth( 1 );
		$this->assertEquals( 'graph LR;

%% People
A2_c6b("A2");
click A2_c6b "' . $baseUrl . '/A2";
C2_f1a("C2");
click C2_f1a "' . $baseUrl . '/C2";
B2_bbd("B2");
click B2_bbd "' . $baseUrl . '/B2";

%% Partners
A2_AND_B2_GROUP_03b{" "};
A2_c6b --> A2_AND_B2_GROUP_03b;
B2_bbd --> A2_AND_B2_GROUP_03b;

%% Children
A2_AND_B2_GROUP_03b --> C2_f1a;

', $tree1->getTreeSource() );
	}

	/**
	 * @covers \MediaWiki\Extensions\Genealogy\Tree::hasAncestorsOrDescendants()
	 */
	public function testHasAncestorsOrDescendants() {
		$tree = new Tree();
		static::assertFalse( $tree->hasAncestorsOrDescendants() );
		$tree->addAncestors( [ 'Alice' ] );
		static::assertTrue( $tree->hasAncestorsOrDescendants() );
	}
}
