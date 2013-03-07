<?php

final class DifferentialBlameRevisionFieldSpecification
  extends DifferentialFieldSpecification {

  private $value;

  public function getStorageKey() {
    return 'phabricator:blame-revision';
  }

  public function getValueForStorage() {
    return $this->value;
  }

  public function setValueFromStorage($value) {
    $this->value = $value;
    return $this;
  }

  public function shouldAppearOnEdit() {
    return true;
  }

  public function setValueFromRequest(AphrontRequest $request) {
    $this->value = $request->getStr($this->getStorageKey());
    return $this;
  }

  public function renderEditControl() {
    return id(new AphrontFormTextControl())
      ->setLabel(pht('Blame Revision'))
      ->setCaption(
        pht('Revision which broke the stuff which this change fixes.'))
      ->setName($this->getStorageKey())
      ->setValue($this->value);
  }

  public function shouldAppearOnRevisionView() {
    return true;
  }

  public function renderLabelForRevisionView() {
    return pht('Blame Revision:');
  }

  public function renderValueForRevisionView() {
    if (!$this->value) {
      return null;
    }
    $engine = PhabricatorMarkupEngine::newDifferentialMarkupEngine();
    $engine->setConfig('viewer', $this->getUser());
    return $engine->markupText($this->value);
  }

  public function shouldAppearOnConduitView() {
    return true;
  }

  public function getValueForConduit() {
    return $this->value;
  }

  public function shouldAppearOnCommitMessage() {
    return true;
  }

  public function getCommitMessageKey() {
    return 'blameRevision';
  }

  public function setValueFromParsedCommitMessage($value) {
    $this->value = $value;
    return $this;
  }

  public function shouldOverwriteWhenCommitMessageIsEdited() {
    return true;
  }

  public function renderLabelForCommitMessage() {
    return 'Blame Revision';
  }

  public function renderValueForCommitMessage($is_edit) {
    return $this->value;
  }

  public function getSupportedCommitMessageLabels() {
    return array(
      'Blame Revision',
      'Blame Rev',
    );
  }

  public function parseValueFromCommitMessage($value) {
    return $value;
  }

}
