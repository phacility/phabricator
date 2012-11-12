<?php

final class DifferentialAuthorFieldSpecification
  extends DifferentialFieldSpecification {

  public function shouldAppearOnRevisionView() {
    return true;
  }

  public function getRequiredHandlePHIDsForRevisionView() {
    return array($this->getAuthorPHID());
  }

  public function renderLabelForRevisionView() {
    return 'Author:';
  }

  public function renderValueForRevisionView() {
    return $this->renderUserList(array($this->getAuthorPHID()));
  }

  private function getAuthorPHID() {
    $revision = $this->getRevision();
    return $revision->getAuthorPHID();
  }

  public function shouldAppearOnRevisionList() {
    return true;
  }

  public function renderHeaderForRevisionList() {
    return 'Author';
  }

  public function renderValueForRevisionList(DifferentialRevision $revision) {
    return $this->getHandle($revision->getAuthorPHID())->renderLink();
  }

  public function getRequiredHandlePHIDsForRevisionList(
    DifferentialRevision $revision) {
    return array($revision->getAuthorPHID());
  }

}
