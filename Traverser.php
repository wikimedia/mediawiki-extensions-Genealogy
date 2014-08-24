<?php

class GenealogyTraverser {

	private $callbacks;

	private $ancestor_depth = 0;

	private $descendant_depth = 0;

	/**
	 * Callbacks will be called for each page crawled.
	 * 
	 * @param callable $callback The callable function etc.
	 */
	public function register($callback) {
		$this->callbacks[] = $callback;
	}

	public function ancestors(GenealogyPerson $person, $depth = null) {
		$this->visit($person);
		if ($this->ancestor_depth > $depth) {
			return;
		}
		foreach ($person->getParents() as $parent) {
			$this->ancestors($parent);
		}
		$this->ancestor_depth++;
	}

	public function descendants(GenealogyPerson $person, $depth = null) {
		$this->visit($person);
		if ($this->descendant_depth > $depth) {
			return;
		}
		foreach ($person->getChildren() as $parent) {
			$this->descendants($parent);
		}
		$this->descendant_depth++;
	}

	private function visit($person) {
		// Call each callback
		foreach ($this->callbacks as $callback) {
			call_user_func($callback, $person);
		}
	}

}
