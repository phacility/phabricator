<?php

final class PhabricatorObjectListQueryTestCase extends PhabricatorTestCase {

  protected function getPhabricatorTestCaseConfiguration() {
    return array(
      self::PHABRICATOR_TESTCONFIG_BUILD_STORAGE_FIXTURES => true,
    );
  }

  public function testObjectListQuery() {
    $user = $this->generateNewTestUser();
    $name = $user->getUsername();
    $phid = $user->getPHID();


    $result = $this->parseObjectList("@{$name}");
    $this->assertEqual(array($phid), $result);

    $result = $this->parseObjectList("{$name}");
    $this->assertEqual(array($phid), $result);

    $result = $this->parseObjectList("{$name}, {$name}");
    $this->assertEqual(array($phid), $result);

    $result = $this->parseObjectList("@{$name}, {$name}");
    $this->assertEqual(array($phid), $result);

    $result = $this->parseObjectList('');
    $this->assertEqual(array(), $result);

    // Expect failure when loading a user if objects must be of type "DUCK".
    $caught = null;
    try {
      $result = $this->parseObjectList("{$name}", array('DUCK'));
    } catch (Exception $ex) {
      $caught = $ex;
    }
    $this->assertTrue($caught instanceof Exception);


    // Expect failure when loading an invalid object.
    $caught = null;
    try {
      $result = $this->parseObjectList('invalid');
    } catch (Exception $ex) {
      $caught = $ex;
    }
    $this->assertTrue($caught instanceof Exception);


    // Expect failure when loading ANY invalid object, by default.
    $caught = null;
    try {
      $result = $this->parseObjectList("{$name}, invalid");
    } catch (Exception $ex) {
      $caught = $ex;
    }
    $this->assertTrue($caught instanceof Exception);


    // With partial results, this should load the valid user.
    $result = $this->parseObjectList("{$name}, invalid", array(), true);
    $this->assertEqual(array($phid), $result);
  }

  private function parseObjectList(
    $list,
    array $types = array(),
    $allow_partial = false) {

    $query = id(new PhabricatorObjectListQuery())
      ->setViewer(PhabricatorUser::getOmnipotentUser())
      ->setObjectList($list);

    if ($types) {
      $query->setAllowedTypes($types);
    }

    if ($allow_partial) {
      $query->setAllowPartialResults(true);
    }

    return $query->execute();
  }

}
