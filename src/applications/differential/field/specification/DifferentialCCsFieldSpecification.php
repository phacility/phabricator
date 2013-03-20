<?php

final class DifferentialCCsFieldSpecification
  extends DifferentialFieldSpecification {

  private $ccs = array();

  public function shouldAppearOnRevisionView() {
    return true;
  }

  public function getRequiredHandlePHIDsForRevisionView() {
    return $this->getCCPHIDs();
  }

  public function renderLabelForRevisionView() {
    return 'CCs:';
  }

  public function renderValueForRevisionView() {
    return $this->renderUserList($this->getCCPHIDs());
  }

  private function getCCPHIDs() {
    $revision = $this->getRevision();
    return $revision->getCCPHIDs();
  }

  public function shouldAppearOnEdit() {
    return true;
  }

  protected function didSetRevision() {
    $this->ccs = $this->getCCPHIDs();
  }

  public function getRequiredHandlePHIDsForRevisionEdit() {
    return $this->ccs;
  }

  public function getRequiredHandlePHIDsForCommitMessage() {
    return $this->ccs;
  }

  public function setValueFromRequest(AphrontRequest $request) {
    $this->ccs = $request->getArr('cc');
    return $this;
  }

  public function renderEditControl() {
    $cc_map = array();
    foreach ($this->ccs as $phid) {
      $cc_map[$phid] = $this->getHandle($phid)->getFullName();
    }
    return id(new AphrontFormTokenizerControl())
      ->setLabel('CC')
      ->setName('cc')
      ->setUser($this->getUser())
      ->setDatasource('/typeahead/common/mailable/')
      ->setValue($cc_map);
  }

  public function willWriteRevision(DifferentialRevisionEditor $editor) {
    $editor->setCCPHIDs($this->ccs);
  }

  public function shouldAppearOnCommitMessage() {
    return true;
  }

  public function getCommitMessageKey() {
    return 'ccPHIDs';
  }

  public function setValueFromParsedCommitMessage($value) {
    $this->ccs = array_unique(nonempty($value, array()));
    return $this;
  }

  public function renderLabelForCommitMessage() {
    return 'CC';
  }

  public function renderValueForCommitMessage($is_edit) {
    if (!$this->ccs) {
      return null;
    }

    $names = array();
    foreach ($this->ccs as $phid) {
      $handle = $this->getHandle($phid);
      if ($handle->isComplete()) {
        $names[] = $handle->getName();
      }
    }
    return implode(', ', $names);
  }

  public function getSupportedCommitMessageLabels() {
    return array(
      'CC',
      'CCs',
    );
  }

  public function parseValueFromCommitMessage($value) {
    return $this->parseCommitMessageMailableList($value);
  }

}
