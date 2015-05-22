<?php

final class DifferentialProjectReviewersField
  extends DifferentialCustomField {

  public function getFieldKey() {
    return 'differential:project-reviewers';
  }

  public function getFieldName() {
    return pht('Project Reviewers');
  }

  public function getFieldDescription() {
    return pht('Display project reviewers.');
  }

  public function shouldAppearInPropertyView() {
    return true;
  }

  public function canDisableField() {
    return false;
  }

  public function renderPropertyViewLabel() {
    return $this->getFieldName();
  }

  public function getRequiredHandlePHIDsForPropertyView() {
    return mpull($this->getProjectReviewers(), 'getReviewerPHID');
  }

  public function renderPropertyViewValue(array $handles) {
    $reviewers = $this->getProjectReviewers();
    if (!$reviewers) {
      return null;
    }

    $view = id(new DifferentialReviewersView())
      ->setUser($this->getViewer())
      ->setReviewers($reviewers)
      ->setHandles($handles);

    // TODO: Active diff stuff.

    return $view;
  }

  private function getProjectReviewers() {
    $reviewers = array();
    foreach ($this->getObject()->getReviewerStatus() as $reviewer) {
      if (!$reviewer->isUser()) {
        $reviewers[] = $reviewer;
      }
    }
    return $reviewers;
  }

  public function getProTips() {
    return array(
      pht(
        'You can add a project as a subscriber or reviewer by writing '.
        '"%s" in the appropriate field.',
        '#projectname'),
    );
  }

}
