<?php

final class DifferentialGitSVNIDFieldSpecification
  extends DifferentialFieldSpecification {

  private $gitSVNID;

  public function shouldAppearOnCommitMessage() {
    return true;
  }

  public function shouldAppearOnCommitMessageTemplate() {
    return false;
  }

  public function getCommitMessageKey() {
    return 'gitSVNID';
  }

  public function setValueFromParsedCommitMessage($value) {
    $this->gitSVNID = $value;
    return $this;
  }

  public function renderLabelForCommitMessage() {
    return 'git-svn-id';
  }

  public function renderValueForCommitMessage($is_edit) {
    return null;
  }

  public function parseValueFromCommitMessage($value) {
    return $value;
  }

}
