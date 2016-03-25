<?php

final class PhabricatorSpacesTestCase extends PhabricatorTestCase {

  protected function getPhabricatorTestCaseConfiguration() {
    return array(
      self::PHABRICATOR_TESTCONFIG_BUILD_STORAGE_FIXTURES => true,
    );
  }

  public function testSpacesAnnihilation() {
    $this->destroyAllSpaces();

    // Test that our helper methods work correctly.

    $actor = $this->generateNewTestUser();

    $default = $this->newSpace($actor, pht('Test Space'), true);
    $this->assertEqual(1, count($this->loadAllSpaces()));
    $this->assertEqual(
      1,
      count(PhabricatorSpacesNamespaceQuery::getAllSpaces()));
    $cache_default = PhabricatorSpacesNamespaceQuery::getDefaultSpace();
    $this->assertEqual($default->getPHID(), $cache_default->getPHID());

    $this->destroyAllSpaces();
    $this->assertEqual(0, count($this->loadAllSpaces()));
    $this->assertEqual(
      0,
      count(PhabricatorSpacesNamespaceQuery::getAllSpaces()));
    $this->assertEqual(
      null,
      PhabricatorSpacesNamespaceQuery::getDefaultSpace());
  }

  public function testSpacesSeveralSpaces() {
    $this->destroyAllSpaces();

    // Try creating a few spaces, one of which is a default space. This should
    // work fine.

    $actor = $this->generateNewTestUser();
    $default = $this->newSpace($actor, pht('Default Space'), true);
    $this->newSpace($actor, pht('Alternate Space'), false);
    $this->assertEqual(2, count($this->loadAllSpaces()));
    $this->assertEqual(
      2,
      count(PhabricatorSpacesNamespaceQuery::getAllSpaces()));

    $cache_default = PhabricatorSpacesNamespaceQuery::getDefaultSpace();
    $this->assertEqual($default->getPHID(), $cache_default->getPHID());
  }

  public function testSpacesRequireNames() {
    $this->destroyAllSpaces();

    // Spaces must have nonempty names.

    $actor = $this->generateNewTestUser();

    $caught = null;
    try {
      $options = array(
        'continueOnNoEffect' => true,
      );
      $this->newSpace($actor, '', true, $options);
    } catch (PhabricatorApplicationTransactionValidationException $ex) {
      $caught = $ex;
    }

    $this->assertTrue(($caught instanceof Exception));
  }

  public function testSpacesUniqueDefaultSpace() {
    $this->destroyAllSpaces();

    // It shouldn't be possible to create two default spaces.

    $actor = $this->generateNewTestUser();
    $this->newSpace($actor, pht('Default Space'), true);

    $caught = null;
    try {
      $this->newSpace($actor, pht('Default Space #2'), true);
    } catch (AphrontDuplicateKeyQueryException $ex) {
      $caught = $ex;
    }

    $this->assertTrue(($caught instanceof Exception));
  }

  public function testSpacesPolicyFiltering() {
    $this->destroyAllSpaces();

    $creator = $this->generateNewTestUser();
    $viewer = $this->generateNewTestUser();

    // Create a new paste.
    $paste = PhabricatorPaste::initializeNewPaste($creator)
      ->setViewPolicy(PhabricatorPolicies::POLICY_USER)
      ->setFilePHID('')
      ->setLanguage('')
      ->save();

    // It should be visible.
    $this->assertTrue(
      PhabricatorPolicyFilter::hasCapability(
        $viewer,
        $paste,
        PhabricatorPolicyCapability::CAN_VIEW));

    // Create a default space with an open view policy.
    $default = $this->newSpace($creator, pht('Default Space'), true)
      ->setViewPolicy(PhabricatorPolicies::POLICY_USER)
      ->save();
    PhabricatorSpacesNamespaceQuery::destroySpacesCache();

    // The paste should now be in the space implicitly, but still visible
    // because the space view policy is open.
    $this->assertTrue(
      PhabricatorPolicyFilter::hasCapability(
        $viewer,
        $paste,
        PhabricatorPolicyCapability::CAN_VIEW));

    // Make the space view policy restrictive.
    $default
      ->setViewPolicy(PhabricatorPolicies::POLICY_NOONE)
      ->save();
    PhabricatorSpacesNamespaceQuery::destroySpacesCache();

    // The paste should be in the space implicitly, and no longer visible.
    $this->assertFalse(
      PhabricatorPolicyFilter::hasCapability(
        $viewer,
        $paste,
        PhabricatorPolicyCapability::CAN_VIEW));

    // Put the paste in the space explicitly.
    $paste
      ->setSpacePHID($default->getPHID())
      ->save();
    PhabricatorSpacesNamespaceQuery::destroySpacesCache();

    // This should still fail, we're just in the space explicitly now.
    $this->assertFalse(
      PhabricatorPolicyFilter::hasCapability(
        $viewer,
        $paste,
        PhabricatorPolicyCapability::CAN_VIEW));

    // Create an alternate space with more permissive policies, then move the
    // paste to that space.
    $alternate = $this->newSpace($creator, pht('Alternate Space'), false)
      ->setViewPolicy(PhabricatorPolicies::POLICY_USER)
      ->save();
    $paste
      ->setSpacePHID($alternate->getPHID())
      ->save();
    PhabricatorSpacesNamespaceQuery::destroySpacesCache();

    // Now the paste should be visible again.
    $this->assertTrue(
      PhabricatorPolicyFilter::hasCapability(
        $viewer,
        $paste,
        PhabricatorPolicyCapability::CAN_VIEW));
  }

  private function loadAllSpaces() {
    return id(new PhabricatorSpacesNamespaceQuery())
      ->setViewer(PhabricatorUser::getOmnipotentUser())
      ->execute();
  }

  private function destroyAllSpaces() {
    PhabricatorSpacesNamespaceQuery::destroySpacesCache();
    $spaces = $this->loadAllSpaces();
    foreach ($spaces as $space) {
      $engine = new PhabricatorDestructionEngine();
      $engine->destroyObject($space);
    }
  }

  private function newSpace(
    PhabricatorUser $actor,
    $name,
    $is_default,
    array $options = array()) {

    $space = PhabricatorSpacesNamespace::initializeNewNamespace($actor);

    $type_name = PhabricatorSpacesNamespaceTransaction::TYPE_NAME;
    $type_default = PhabricatorSpacesNamespaceTransaction::TYPE_DEFAULT;
    $type_view = PhabricatorTransactions::TYPE_VIEW_POLICY;
    $type_edit = PhabricatorTransactions::TYPE_EDIT_POLICY;

    $xactions = array();

    $xactions[] = id(new PhabricatorSpacesNamespaceTransaction())
      ->setTransactionType($type_name)
      ->setNewValue($name);

    $xactions[] = id(new PhabricatorSpacesNamespaceTransaction())
      ->setTransactionType($type_view)
      ->setNewValue($actor->getPHID());

    $xactions[] = id(new PhabricatorSpacesNamespaceTransaction())
      ->setTransactionType($type_edit)
      ->setNewValue($actor->getPHID());

    if ($is_default) {
      $xactions[] = id(new PhabricatorSpacesNamespaceTransaction())
        ->setTransactionType($type_default)
        ->setNewValue($is_default);
    }

    $content_source = $this->newContentSource();

    $editor = id(new PhabricatorSpacesNamespaceEditor())
      ->setActor($actor)
      ->setContentSource($content_source);

    if (isset($options['continueOnNoEffect'])) {
      $editor->setContinueOnNoEffect(true);
    }

    $editor->applyTransactions($space, $xactions);

    return $space;
  }

}
