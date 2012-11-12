<?php

final class DifferentialReviewedByFieldSpecification
  extends DifferentialFieldSpecification {

  private $reviewedBy;

  protected function didSetRevision() {
    $this->reviewedBy = array();
    $revision = $this->getRevision();
    $reviewer = $revision->loadReviewedBy();

    if ($reviewer) {
      $this->reviewedBy = array($reviewer);
    }
  }

  public function shouldAppearOnCommitMessage() {
    return true;
  }

  public function getCommitMessageKey() {
    return 'reviewedByPHIDs';
  }

  public function setValueFromParsedCommitMessage($value) {
    $this->reviewedBy = $value;
    return $this;
  }

  public function shouldAppearOnCommitMessageTemplate() {
    return false;
  }

  public function renderLabelForCommitMessage() {
    return 'Reviewed By';
  }

  public function getRequiredHandlePHIDsForCommitMessage() {
    return $this->reviewedBy;
  }

  public function renderValueForCommitMessage($is_edit) {
    if ($is_edit) {
      return null;
    }

    if (!$this->reviewedBy) {
      return null;
    }

    $names = array();
    foreach ($this->reviewedBy as $phid) {
      $names[] = $this->getHandle($phid)->getName();
    }

    return implode(', ', $names);
  }

  public function parseValueFromCommitMessage($value) {
    return $this->parseCommitMessageUserList($value);
  }

}
