<?php

class GenealogyPerson {

	/** @var Title */
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
	 * Get some basic info about this person.
	 * @todo Add dates.
	 * @return string
	 */
	public function __toString() {
		return $this->getTitle()->getPrefixedText();
	}

	/**
	 * Get this person's wiki title.
	 *
	 * @return Title
	 */
	public function getTitle() {
		return $this->title;
	}

	public function getWikiLink() {
		$birthYear = $this->getDateYear($this->getBirthDate());
		$deathYear = $this->getDateYear($this->getDeathDate());
		$dateString = '';
		if (!empty($birthYear) && !empty($deathYear)) {
			$dateString = "($birthYear&ndash;$deathYear)";
		} elseif (!empty($birthYear) && empty($deathYear)) {
			$dateString = "(".wfMessage('genealogy-born')."&nbsp;$birthYear)";
		} elseif (empty($birthYear) && !empty($deathYear)) {
			$dateString = "(".wfMessage('genealogy-died')."&nbsp;$deathYear)";
		}
		$date = ($this->hasDates()) ? " $dateString" : "";
		return "[[" . $this->getTitle()->getPrefixedText() . "]]$date";
	}

	/**
	 * Whether or not this person has a birth or death date.
	 * @return boolean
	 */
	public function hasDates() {
		return $this->getBirthDate() !== false || $this->getDeathDate() !== false;
	}

	/**
	 * Get the birth date of this person.
	 * @return string
	 */
	public function getBirthDate() {
		return $this->getPropSingle("birth date");
	}

	/**
	 * Get the death date of this person.
	 * @return string
	 */
	public function getDeathDate() {
		return $this->getPropSingle("death date");
	}

	/**
	 * Get a year out of a date if possible.
	 * @param string $date
	 * @return string The year as a string, or the full date.
	 */
	public function getDateYear($date) {
//		if (empty($rawDate)) {
//			return false;
//		}
//		try {
//			$date = new DateTime($rawDate);
//			return $date->format('Y');
//		} catch (Exception $e) {
//			echo $e->getMessage();
//			return $date;
//		}
		preg_match('/(\d{4})/', $date, $matches);
		if (isset($matches[1])) {
			return $matches[1];
		} else {
			return $date;
		}
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
		$this->parents = $this->getPropMulti('parent');
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
		$this->partners = array_merge(
			$this->getPropInbound('partner'),
			$this->getPropMulti('partner')
		);
		//unset($this->partners[$this->title->getPrefixedDBkey()]);
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
		$this->children = $this->getPropInbound('parent');
		return $this->children;
	}

	private function getPropInbound($type) {
		$out = array();
		$dbr = wfGetDB(DB_SLAVE);
		$results = $dbr->select(
			array('pp'=>'page_props', 'p'=>'page'), // tables
				array('pp_value', 'page_title'), // columns
			array( // where conditions
			'pp_value' => $this->title->getPrefixedText(),
			"pp_propname LIKE 'genealogy $type %'",
			'pp_page = page_id',
			),
			__METHOD__,
			array(),
			array('page'=>array())
		);
		foreach ($results as $res) {
			$title = Title::newFromText($res->page_title);
			$out[$title->getPrefixedDBkey()] = new GenealogyPerson($title);
		}
		return $out;
	}

	public function getPropSingle($prop) {
		$dbr = wfGetDB(DB_SLAVE);
		return $dbr->selectField(
						'page_props', // table to use
						'pp_value', // Field to select
			array( // where conditions
					'pp_page' => $this->title->getArticleID(),
					'pp_propname' => "genealogy $prop"
			),
			__METHOD__
		);
	}

	private function getPropMulti($type) {
		$out = array();
		$dbr = wfGetDB(DB_SLAVE);
		$results = $dbr->select(
				'page_props', // table to use
				'pp_value', // Field to select
			array( // where conditions
			'pp_page' => $this->title->getArticleID(),
			"pp_propname LIKE 'genealogy $type %'"
			),
			__METHOD__
		);
		foreach ($results as $result) {
			$title = Title::newFromText($result->pp_value);
			$out[$title->getPrefixedDBkey()] = new GenealogyPerson($title);
		}
		return $out;
	}

}
