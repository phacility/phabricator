<?php

final class PhabricatorPolicyDataTestCase extends PhabricatorTestCase {

  protected function getPhabricatorTestCaseConfiguration() {
    return array(
      self::PHABRICATOR_TESTCONFIG_BUILD_STORAGE_FIXTURES => true,
    );
  }

  public function testProjectPolicyMembership() {
    $author = $this->generateNewTestUser();

    $proj_a = id(new PhabricatorProject())
      ->setName('A')
      ->setAuthorPHID($author->getPHID())
      ->save();
    $proj_b = id(new PhabricatorProject())
      ->setName('B')
      ->setAuthorPHID($author->getPHID())
      ->save();

    $proj_a->setViewPolicy($proj_b->getPHID())->save();
    $proj_b->setViewPolicy($proj_a->getPHID())->save();

    $user = new PhabricatorUser();

    $results = id(new PhabricatorProjectQuery())
      ->setViewer($user)
      ->execute();

    $this->assertEqual(0, count($results));
  }
}
