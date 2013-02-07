<?php

final class DifferentialRevertPlanFieldSpecification
  extends DifferentialFieldSpecification {

  private $value;

  public function getStorageKey() {
    return 'phabricator:revert-plan';
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
    return id(new AphrontFormTextAreaControl())
      ->setLabel('Revert Plan')
      ->setName($this->getStorageKey())
      ->setCaption('Special steps required to safely revert this change.')
      ->setValue($this->value);
  }

  public function shouldAppearOnRevisionView() {
    return true;
  }

  public function renderLabelForRevisionView() {
    return 'Revert Plan:';
  }

  public function renderValueForRevisionView() {
    if (!$this->value) {
      return null;
    }
    return $this->value;
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
    return 'revertPlan';
  }

  public function setValueFromParsedCommitMessage($value) {
    $this->value = $value;
    return $this;
  }

  public function shouldOverwriteWhenCommitMessageIsEdited() {
    return true;
  }

  public function renderLabelForCommitMessage() {
    return 'Revert Plan';
  }


  public function renderValueForCommitMessage($is_edit) {
    return $this->value;
  }

  public function getSupportedCommitMessageLabels() {
    return array(
      'Revert Plan',
      'Revert',
    );
  }

  public function parseValueFromCommitMessage($value) {
    return $value;
  }

  public function shouldAddToSearchIndex() {
    return true;
  }

  public function getValueForSearchIndex() {
    return $this->value;
  }

  public function getKeyForSearchIndex() {
    return 'rpln';
  }

}
