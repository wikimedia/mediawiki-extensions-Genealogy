<?php

/**
 * @group extensions
 * @group Genealogy
 */
class PersonTest extends MediaWikiTestCase {

	public function testName() {
		$person = new GenealogyPerson('Will');
		$this->assertEquals('Will', $person->getTitle());
	}

	public function testDates() {
		$person = new GenealogyPerson('Will');
		$this->assertEquals(false, $person->getDateYear(''));
		$this->assertEquals('1804', $person->getDateYear('1804'));
		$this->assertEquals('2014', $person->getDateYear('2014-10-01'));
		$this->assertEquals('2014', $person->getDateYear('1 September 2014'));
		$this->assertEquals('1803', $person->getDateYear('June 1803'));
		$this->assertEquals('1890', $person->getDateYear('c. 1890'));
	}

}
