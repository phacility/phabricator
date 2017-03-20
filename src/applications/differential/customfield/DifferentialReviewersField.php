<?php

final class DifferentialReviewersField
  extends DifferentialCoreCustomField {

  public function getFieldKey() {
    return 'differential:reviewers';
  }

  public function getFieldName() {
    return pht('Reviewers');
  }

  public function getFieldDescription() {
    return pht('Manage reviewers.');
  }

  protected function readValueFromRevision(
    DifferentialRevision $revision) {
    return $revision->getReviewers();
  }

  public function shouldAppearInPropertyView() {
    return true;
  }

  public function renderPropertyViewLabel() {
    return $this->getFieldName();
  }

  public function getRequiredHandlePHIDsForPropertyView() {
    return mpull($this->getUserReviewers(), 'getReviewerPHID');
  }

  public function renderPropertyViewValue(array $handles) {
    $reviewers = $this->getUserReviewers();
    if (!$reviewers) {
      return phutil_tag('em', array(), pht('None'));
    }

    $view = id(new DifferentialReviewersView())
      ->setUser($this->getViewer())
      ->setReviewers($reviewers)
      ->setHandles($handles);

    $diff = $this->getActiveDiff();
    if ($diff) {
      $view->setActiveDiff($diff);
    }

    return $view;
  }

  private function getUserReviewers() {
    $reviewers = array();
    foreach ($this->getObject()->getReviewers() as $reviewer) {
      if ($reviewer->isUser()) {
        $reviewers[] = $reviewer;
      }
    }
    return $reviewers;
  }

  public function getRequiredHandlePHIDsForRevisionHeaderWarnings() {
    return mpull($this->getValue(), 'getReviewerPHID');
  }

  public function getWarningsForRevisionHeader(array $handles) {
    $revision = $this->getObject();

    $status_needs_review = ArcanistDifferentialRevisionStatus::NEEDS_REVIEW;
    if ($revision->getStatus() != $status_needs_review) {
      return array();
    }

    foreach ($this->getValue() as $reviewer) {
      if (!$handles[$reviewer->getReviewerPHID()]->isDisabled()) {
        return array();
      }
    }

    $warnings = array();
    if ($this->getValue()) {
      $warnings[] = pht(
        'This revision needs review, but all specified reviewers are '.
        'disabled or inactive.');
    } else {
      $warnings[] = pht(
        'This revision needs review, but there are no reviewers specified.');
    }

    return $warnings;
  }

}
