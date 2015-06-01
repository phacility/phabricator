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
    $this->newSpace($actor, pht('Test Space'), true);
    $this->assertEqual(1, count($this->loadAllSpaces()));
    $this->destroyAllSpaces();
    $this->assertEqual(0, count($this->loadAllSpaces()));
  }

  public function testSpacesSeveralSpaces() {
    $this->destroyAllSpaces();

    // Try creating a few spaces, one of which is a default space. This should
    // work fine.

    $actor = $this->generateNewTestUser();
    $this->newSpace($actor, pht('Default Space'), true);
    $this->newSpace($actor, pht('Alternate Space'), false);
    $this->assertEqual(2, count($this->loadAllSpaces()));
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

  private function loadAllSpaces() {
    return id(new PhabricatorSpacesNamespaceQuery())
      ->setViewer(PhabricatorUser::getOmnipotentUser())
      ->execute();
  }

  private function destroyAllSpaces() {
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

    $content_source = PhabricatorContentSource::newConsoleSource();

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
