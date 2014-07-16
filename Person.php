<?php

class GenealogyPerson {

	private $title;

	private $parents;

	private $siblings;

	private $partners;

	private $children;

	public function __construct($title) {
		$this->title = $title;
		$this->magicRegex = MagicWord::get('genealogy')->getBaseRegex();
	}

	/**
	 * Get this person's wiki title.
	 *
	 * @return Title
	 */
	public function getTitle() {
		return $this->title;
	}

	/**
	 * Get all parents.
	 *
	 * @return array|GenealogyPerson An array of parents, possibly empty.
	 */
	public function getParents() {
		if (is_array($this->parents)) {
			return $this->parents;
		}
		$this->parents = array();
		$selfPage = new WikiPage($this->title);
		$text = ContentHandler::getContentText($selfPage->getContent());
		$pattern = '/{{\#'.$this->magicRegex.':\s*parent\s*\|\s*([^|}]*)/';
		preg_match_all($pattern, $text, $matches);
		if (isset($matches[1])) {
			foreach ($matches[1] as $match) {
				$parentTitle = Title::newFromText($match);
				$this->parents[$parentTitle->getPrefixedDBkey()] = new GenealogyPerson($parentTitle);
			}
		}
		return $this->parents;
	}

	/**
	 * Get all siblings.
	 *
	 * @return array|GenealogyPerson An array of siblings, possibly empty.
	 */
	public function getSiblings() {
		if (is_array($this->siblings)) {
			return $this->siblings;
		}
		$this->siblings = array();
		foreach ($this->getParents() as $parent) {
			foreach ($parent->getChildren() as $child) {
				$this->siblings[$child->getTitle()->getPrefixedDBkey()] = $child;
			}
		}
		return $this->siblings;
	}

	/**
	 * Get all partners.
	 *
	 * @return array|GenealogyPerson An array of partners, possibly empty.
	 */
	public function getPartners() {
		if (is_array($this->partners)) {
			return $this->partners;
		}
		$this->partners = $this->whatLinksHere('partner');
		return $this->partners;
	}

	/**
	 * Get all children.
	 *
	 * @return array|GenealogyPerson An array of children, possibly empty.
	 */
	public function getChildren() {
		if (is_array($this->children)) {
			return $this->children;
		}
		$this->children = array();
		$prefexedTitle = $this->title->getPrefixedDBkey();
		$dbr = wfGetDB(DB_SLAVE);
		$res = $dbr->select(
			array('pl' => 'pagelinks', 'p' => 'page'),
			array('page_namespace', 'page_title'), // columns
			array(// conditions
				'pl_title' => $prefexedTitle,
				'pl_from = page_id',
				'pl_namespace = page_namespace'
			),
			__METHOD__,
			array(),
			array('page' => array())
		);
		foreach ($res as $row) {
			$childTitle = Title::makeTitle($row->page_namespace, $row->page_title);
			$poss_child = new WikiPage($childTitle);
			$content = $poss_child->getContent();
			$text = ContentHandler::getContentText($content);
			$pattern = '/{{\#'.$this->magicRegex.':\s*parent\s*\|\s*'.$prefexedTitle.'/';
			if(preg_match($pattern, $text)===1) {
				$this->children[] = new GenealogyPerson($childTitle);
			}
		}
		return $this->children;
	}

	/**
	 * Get an array of GenealogyPerson objects built from pages that link to this one with the
	 * relationship of $as.
	 * @param string $as The relationship type, either 'parent' or 'partner'.
	 */
	private function whatLinksHere($as) {
		$out = array();
		$prefexedTitle = $this->title->getPrefixedDBkey();
		$dbr = wfGetDB(DB_SLAVE);
		$res = $dbr->select(
			array('pl' => 'pagelinks', 'p' => 'page'),
			array('page_namespace', 'page_title'), // columns
			array(// conditions
				'pl_title' => $prefexedTitle,
				'pl_from = page_id',
				'pl_namespace = page_namespace'
			),
			__METHOD__,
			array(),
			array('page' => array())
		);
		foreach ($res as $row) {
			$linkTitle = Title::makeTitle($row->page_namespace, $row->page_title);
			$possibleLink = new WikiPage($linkTitle);
			$text = ContentHandler::getContentText($possibleLink->getContent());
			$pattern = '/{{\#'.$this->magicRegex.':\s*'.$as.'\s*\|\s*'.$prefexedTitle.'/';
			if(preg_match($pattern, $text)===1) {
				$out[$linkTitle->getPrefixedDBkey()] = new GenealogyPerson($linkTitle);
			}
		}
		return $out;
	}
}
