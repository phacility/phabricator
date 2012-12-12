<?php

final class DifferentialConflictsFieldSpecification
  extends DifferentialFieldSpecification {

  public function shouldAppearOnCommitMessage() {
    return true;
  }

  public function shouldAppearOnCommitMessageTemplate() {
    return false;
  }

  public function getCommitMessageKey() {
    return 'conflicts';
  }

  public function setValueFromParsedCommitMessage($value) {
    return $this;
  }

  public function renderLabelForCommitMessage() {
    return 'Conflicts';
  }

  public function renderValueForCommitMessage($is_edit) {
    return null;
  }

  public function parseValueFromCommitMessage($value) {
    return $value;
  }

}
