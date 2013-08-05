<?php

final class DifferentialSummaryFieldSpecification
  extends DifferentialFreeformFieldSpecification {

  private $summary = '';
  private $controlID;

  public function shouldAppearOnEdit() {
    return true;
  }

  protected function didSetRevision() {
    $this->summary = (string)$this->getRevision()->getSummary();
  }

  public function setValueFromRequest(AphrontRequest $request) {
    $this->summary = $request->getStr('summary');
    return $this;
  }

  public function renderEditControl() {
    return id(new PhabricatorRemarkupControl())
      ->setLabel('Summary')
      ->setName('summary')
      ->setID($this->getControlID())
      ->setValue($this->summary);
  }

  public function renderEditPreview() {
    return id(new PHUIRemarkupPreviewPanel())
      ->setHeader(pht('Summary Preview'))
      ->setControlID($this->getControlID())
      ->setPreviewURI('/differential/preview/');
  }

  public function shouldExtractMentions() {
    return true;
  }

  public function willWriteRevision(DifferentialRevisionEditor $editor) {
    $this->getRevision()->setSummary($this->summary);
  }

  public function shouldAppearOnCommitMessage() {
    return true;
  }

  public function getCommitMessageKey() {
    return 'summary';
  }

  public function setValueFromParsedCommitMessage($value) {
    $this->summary = (string)$value;
    return $this;
  }

  public function shouldOverwriteWhenCommitMessageIsEdited() {
    return true;
  }

  public function renderLabelForCommitMessage() {
    return 'Summary';
  }

  public function renderValueForCommitMessage($is_edit) {
    return $this->summary;
  }

  public function parseValueFromCommitMessage($value) {
    return (string)$value;
  }

  public function renderValueForMail($phase) {
    if ($phase != DifferentialMailPhase::WELCOME) {
      return null;
    }

    if ($this->summary == '') {
      return null;
    }

    return $this->summary;
  }

  public function shouldAddToSearchIndex() {
    return true;
  }

  public function getValueForSearchIndex() {
    return $this->summary;
  }

  public function getKeyForSearchIndex() {
    return PhabricatorSearchField::FIELD_BODY;
  }

  private function getControlID() {
    if (!$this->controlID) {
      $this->controlID = celerity_generate_unique_node_id();
    }
    return $this->controlID;
  }

}
