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

    $highlight = array();
    if ($this->getUser()->getPHID() != $this->getRevision()->getAuthorPHID()) {
      // Determine which of these projects the viewer is a member of, so we can
      // highlight them. (If the viewer is the author, skip this since they
      // can't review.)
      $phids = mpull($reviewers, 'getReviewerPHID');
      $projects = id(new PhabricatorProjectQuery())
        ->setViewer($this->getUser())
        ->withPHIDs($phids)
        ->withMemberPHIDs(array($this->getUser()->getPHID()))
        ->execute();
      $highlight = mpull($projects, 'getPHID');
    }

    $view = id(new DifferentialReviewersView())
      ->setReviewers($reviewers)
      ->setHandles($this->getLoadedHandles())
      ->setHighlightPHIDs($highlight);

    $diff = $this->getRevision()->loadActiveDiff();
    if ($diff) {
      $view->setActiveDiff($diff);
    }

    return $view;
  }

}
