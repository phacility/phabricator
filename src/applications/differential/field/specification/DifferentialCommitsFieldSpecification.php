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

    return implode('<br />', $links);
  }

  private function getCommitPHIDs() {
    $revision = $this->getRevision();
    return $revision->getCommitPHIDs();
  }

  public function renderValueForMail($phase) {
    $revision = $this->getRevision();

    if ($revision->getStatus() != ArcanistDifferentialRevisionStatus::CLOSED) {
      return null;
    }

    $phids = $revision->loadCommitPHIDs();
    if (!$phids) {
      return null;
    }

    $body = array();
    $handles = id(new PhabricatorObjectHandleData($phids))->loadHandles();
    $body[] = pht('COMMIT(S)', count($handles));

    foreach ($handles as $handle) {
      $body[] = '  '.PhabricatorEnv::getProductionURI($handle->getURI());
    }

    return implode("\n", $body);
  }

}
