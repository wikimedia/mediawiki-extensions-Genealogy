<?php

class GenealogyTree {

	private $dot_source;

	private $ancestors = array();

	private $descendants = array();

	private $ancestor_depth;

	private $descendant_depth;

	public function setAncestorDepth($ancestor_depth) {
		$this->ancestor_depth = $ancestor_depth;
	}

	public function setDescendantDepth($descendant_depth) {
		$this->descendant_depth = $descendant_depth;
	}

	public function addAncestors($ancestors) {
		$this->addAncestorsOrDescendants('ancestors', $ancestors);
	}

	public function addDescendants($descendants) {
		$this->addAncestorsOrDescendants('descendants', $descendants);
	}

	private function addAncestorsOrDescendants($type, $list) {
		foreach ($list as $a) {
			$title = Title::newFromText($a);
			if ($title and $title->exists()) {
				$person = new GenealogyPerson($title);
				$this->{$type}[] = $person;
			}
		}
	}

	public function getGraphviz() {
		$this->out('top', 'digraph GenealogyTree {');
		$this->out('top', 'graph [rankdir=LR, splines=ortho]');
		$this->out('top', 'edge [arrowhead=none]');

		$traverser = new GenealogyTraverser();
		$traverser->register(array($this, 'visit'));

		foreach ($this->ancestors as $ancestor) {
			$traverser->ancestors($ancestor, $this->ancestor_depth);
		}

		foreach ($this->descendants as $descendant) {
			$traverser->descendants($descendant, $this->descendant_depth);
		}

		// Do nothing if there are no people listed.
		if (!isset($this->dot_source['person'])) {
			return 'No people found';
		}

		// Combine all parts of the graph output.
		return join("\n", $this->dot_source['top']) . "\n\n"
			.join("\n", $this->dot_source['person']) . "\n\n"
			.join("\n", $this->dot_source['partner']) . "\n\n"
			.join("\n", $this->dot_source['child']) . "\n}";
	}

	public function visit(GenealogyPerson $person) {
		$birthYear = $person->getBirthDate('Y');
		$deathYear = $person->getDeathDate('Y');
		if (!empty($birthYear) && !empty($deathYear)) {
			$date = '\n'.$birthYear.' &ndash; '.$deathYear;
		} elseif (!empty($birthYear)) {
			$date = '\nb.&nbsp;'.$birthYear;
		} elseif (!empty($deathYear)) {
			$date = '\nd.&nbsp;'.$deathYear;
		} else {
			$date = '';
		}
		$personId = $this->esc($person->getTitle()->getDBkey());
		$url = $person->getTitle()->getFullURL();
		$title = $person->getTitle();
		$line = $personId." [label=\"$title$date\", shape=plaintext, URL=\"$url\", tooltip=\"$title\"]";
		$this->out('person', $line);
		foreach ($person->getChildren() as $child) {
			$parents = 'parents_'.$this->esc(join('', $child->getParents()));
			$this->out('partner', $parents.' [label="", shape="point"]');
			$this->out('partner', $personId.' -> '.$parents.' [style=dotted]');
			$this->out('child', $parents.' -> '.$this->esc($child->getTitle()->getDBkey()));
		}
	}

	private function out($group, $line, $permit_dupes = false) {
		if (!is_array($this->dot_source)) {
			$this->dot_source = array();
		}
		if (!isset($this->dot_source[$group])) {
			$this->dot_source[$group] = array();
		}
		if (!in_array($line, $this->dot_source[$group]) || $permit_dupes) {
			$this->dot_source[$group][] = $line;
		}
	}

	private function esc($title) {
		return strtr($title, '( )', '___');
	}

}
