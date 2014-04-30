<?php

final class ReleephCommitMessageFieldSpecification
  extends ReleephFieldSpecification {

  public function getFieldKey() {
    return 'commit:apply';
  }

  public function getName() {
    return '__only_for_commit_message!';
  }

  public function shouldAppearInPropertyView() {
    return false;
  }

  public function shouldAppearOnCommitMessage() {
    return true;
  }

  public function renderLabelForCommitMessage() {
    return $this->renderCommonLabel();
  }

  public function renderValueForCommitMessage() {
    return $this->renderCommonValue(
      DifferentialReleephRequestFieldSpecification::ACTION_PICKS);
  }

  public function shouldAppearOnRevertMessage() {
    return true;
  }

  public function renderLabelForRevertMessage() {
    return $this->renderCommonLabel();
  }

  public function renderValueForRevertMessage() {
    return $this->renderCommonValue(
      DifferentialReleephRequestFieldSpecification::ACTION_REVERTS);
  }

  private function renderCommonLabel() {
    return id(new DifferentialReleephRequestFieldSpecification())
      ->renderLabelForCommitMessage();
  }

  private function renderCommonValue($action) {
    $rq = 'RQ'.$this->getReleephRequest()->getID();
    return "{$action} {$rq}";
  }

}
