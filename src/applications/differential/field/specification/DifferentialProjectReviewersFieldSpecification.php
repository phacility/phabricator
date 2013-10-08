<?php

final class DifferentialProjectReviewersFieldSpecification
  extends DifferentialFieldSpecification {

  public function shouldAppearOnRevisionView() {
    return true;
  }

  public function getRequiredHandlePHIDsForRevisionView() {
    return $this->getRevision()->getReviewers();
  }

  public function renderLabelForRevisionView() {
    return pht('Project Reviewers');
  }

  public function renderValueForRevisionView() {
    $reviewers = array();
    foreach ($this->getRevision()->getReviewerStatus() as $reviewer) {
      if (!$reviewer->isUser()) {
        $reviewers[] = $reviewer;
      }
    }

    if (!$reviewers) {
      return null;
    }

    $view = id(new DifferentialReviewersView())
      ->setUser($this->getUser())
      ->setReviewers($reviewers)
      ->setHandles($this->getLoadedHandles());

    $diff = $this->getRevision()->loadActiveDiff();
    if ($diff) {
      $view->setActiveDiff($diff);
    }

    return $view;
  }

}
