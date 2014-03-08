<?php

final class DifferentialCommitsFieldSpecification
  extends DifferentialFieldSpecification {

  public function shouldAppearOnRevisionView() {
    return true;
  }

  public function getRequiredHandlePHIDsForRevisionView() {
    return $this->getCommitPHIDs();
  }

  public function renderLabelForRevisionView() {
    return 'Commits:';
  }

  public function renderValueForRevisionView() {
    $commit_phids = $this->getCommitPHIDs();
    if (!$commit_phids) {
      return null;
    }

    $links = array();
    foreach ($commit_phids as $commit_phid) {
      $links[] = $this->getHandle($commit_phid)->renderLink();
    }

    return phutil_implode_html(phutil_tag('br'), $links);
  }

  private function getCommitPHIDs() {
    $revision = $this->getRevision();
    return $revision->getCommitPHIDs();
  }

}
