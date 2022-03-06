<?php

namespace MediaWiki\Extension\Genealogy\Test;

use MediaWiki\Extension\Genealogy\Tree;

/**
 * @group Database
 * @group extensions
 * @group Genealogy
 * @covers \MediaWiki\Extension\Genealogy\Tree
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
		$this->assertStringContainsString(
			'DescB_269 [ label=<DescB<BR/><FONT POINT-SIZE="9">A description with HTML</FONT>>,',
			$tree->getTreeSource()
		);
	}

	/**
	 * Help:A
	 *  |
	 *  Б  C = D
	 *   \/
	 *   E = F
	 *   |
	 *   G
	 */
	public function testGraphVizTree() {
		$this->setPageContent( 'Б', '{{#genealogy:parent|Help:A}}' );
		$this->setPageContent( 'C', '{{#genealogy:partner|D}}' );
		$this->setPageContent( 'D', '' );
		$this->setPageContent( 'E',
			'{{#genealogy:partner|F}}{{#genealogy:parent|Б}}{{#genealogy:parent|C}}'
		);
		$this->setPageContent( 'F', '' );
		$this->setPageContent( 'G', '{{#genealogy:parent|E}}' );
		$tree1 = new Tree();
		$tree1->addDescendants( [ 'Help:A' ] );
		$tree1->setDescendantDepth( 2 );
		$editUrl = '%atitle=Help:A&preload=Template%3APerson%2Fpreload&action=edit';
		$this->assertStringMatchesFormat(
			'%a
/* People */
HelpA_c80 [ label=<A>,  URL="[' . $editUrl . ']",  tooltip="Help:A",  fontcolor="red" ]
B_3b6 [ label=<Б>,  URL="[[Б]]",  tooltip="Б",  fontcolor="black" ]
E_3a3 [ label=<E>,  URL="[[E]]",  tooltip="E",  fontcolor="black" ]

/* Partners */
HelpA_GROUP_652 [label="", shape="point"]
HelpA_c80 -> HelpA_GROUP_652 [style="dashed"]
C_AND_B_GROUP_a11 [label="", shape="point"]
B_3b6 -> C_AND_B_GROUP_a11 [style="dashed"]

/* Children */
HelpA_GROUP_652 -> B_3b6
C_AND_B_GROUP_a11 -> E_3a3

}
',
			$tree1->getTreeSource()
		);

		$tree2 = new Tree();
		$tree2->addAncestors( [ 'G' ] );
		$tree2->setAncestorDepth( 3 );
		$this->assertStringMatchesFormat(
			'%a
/* People */
G_dfc [ label=<G>,  URL="[[G]]",  tooltip="G",  fontcolor="black" ]
E_3a3 [ label=<E>,  URL="[[E]]",  tooltip="E",  fontcolor="black" ]
C_0d6 [ label=<C>,  URL="[[C]]",  tooltip="C",  fontcolor="black" ]
B_3b6 [ label=<Б>,  URL="[[Б]]",  tooltip="Б",  fontcolor="black" ]
F_800 [ label=<F>,  URL="[[F]]",  tooltip="F",  fontcolor="black" ]
D_f62 [ label=<D>,  URL="[[D]]",  tooltip="D",  fontcolor="black" ]
HelpA_c80 [ label=<A>,  URL="[' . $editUrl . ']",  tooltip="Help:A",  fontcolor="red" ]

/* Partners */
E_GROUP_21d [label="", shape="point"]
E_3a3 -> E_GROUP_21d [style="dashed"]
C_AND_B_GROUP_a11 [label="", shape="point"]
C_0d6 -> C_AND_B_GROUP_a11 [style="dashed"]
B_3b6 -> C_AND_B_GROUP_a11 [style="dashed"]
E_AND_F_GROUP_25b [label="", shape="point"]
E_3a3 -> E_AND_F_GROUP_25b [style="dashed"]
F_800 -> E_AND_F_GROUP_25b [style="dashed"]
C_AND_D_GROUP_20f [label="", shape="point"]
C_0d6 -> C_AND_D_GROUP_20f [style="dashed"]
D_f62 -> C_AND_D_GROUP_20f [style="dashed"]
HelpA_GROUP_652 [label="", shape="point"]
HelpA_c80 -> HelpA_GROUP_652 [style="dashed"]

/* Children */
E_GROUP_21d -> G_dfc
C_AND_B_GROUP_a11 -> E_3a3
HelpA_GROUP_652 -> B_3b6
%a',
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

%% Partners
A2_AND_B2_GROUP_9d7{" "};
A2_c6b --> A2_AND_B2_GROUP_9d7;

%% Children
A2_AND_B2_GROUP_9d7 --> C2_f1a;

', $tree1->getTreeSource() );
	}

	/**
	 * @covers \MediaWiki\Extension\Genealogy\Tree::hasAncestorsOrDescendants()
	 */
	public function testHasAncestorsOrDescendants() {
		$tree = new Tree();
		static::assertFalse( $tree->hasAncestorsOrDescendants() );
		$tree->addAncestors( [ 'Alice' ] );
		static::assertTrue( $tree->hasAncestorsOrDescendants() );
	}

	public function testParamEscapedQuotes() {
		$pageName = '"Q"';
		$this->setPageContent( $pageName, '' );
		$tree1 = new Tree();
		$tree1->addDescendants( [ $pageName ] );
		$tree1->setDescendantDepth( 1 );
		$this->assertStringContainsString(
			'Q_b10 [ label=<"Q">,  URL="[[\"Q\"]]",  tooltip="\"Q\"",  fontcolor="black" ]',
			$tree1->getTreeSource()
		);
	}
}
