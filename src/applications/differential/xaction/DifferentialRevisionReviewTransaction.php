<?php

abstract class DifferentialRevisionReviewTransaction
  extends DifferentialRevisionActionTransaction {

  protected function getRevisionActionGroupKey() {
    return DifferentialRevisionEditEngine::ACTIONGROUP_REVIEW;
  }

  protected function isViewerAnyReviewer(
    DifferentialRevision $revision,
    PhabricatorUser $viewer) {
    return ($this->getViewerReviewerStatus($revision, $viewer) !== null);
  }

  protected function isViewerFullyAccepted(
    DifferentialRevision $revision,
    PhabricatorUser $viewer) {
    return $this->isViewerReviewerStatusFullyAmong(
      $revision,
      $viewer,
      array(
        DifferentialReviewerStatus::STATUS_ACCEPTED,
      ));
  }

  protected function isViewerFullyRejected(
    DifferentialRevision $revision,
    PhabricatorUser $viewer) {
    return $this->isViewerReviewerStatusFullyAmong(
      $revision,
      $viewer,
      array(
        DifferentialReviewerStatus::STATUS_REJECTED,
      ));
  }

  protected function getViewerReviewerStatus(
    DifferentialRevision $revision,
    PhabricatorUser $viewer) {

    if (!$viewer->getPHID()) {
      return null;
    }

    foreach ($revision->getReviewerStatus() as $reviewer) {
      if ($reviewer->getReviewerPHID() != $viewer->getPHID()) {
        continue;
      }

      return $reviewer->getStatus();
    }

    return null;
  }

  protected function isViewerReviewerStatusFullyAmong(
    DifferentialRevision $revision,
    PhabricatorUser $viewer,
    array $status_list) {

    // If the user themselves is not a reviewer, the reviews they have
    // authority over can not all be in any set of states since their own
    // personal review has no state.
    $status = $this->getViewerReviewerStatus($revision, $viewer);
    if ($status === null) {
      return false;
    }

    // Otherwise, check that all reviews they have authority over are in
    // the desired set of states.
    $status_map = array_fuse($status_list);
    foreach ($revision->getReviewerStatus() as $reviewer) {
      if (!$reviewer->hasAuthority($viewer)) {
        continue;
      }

      $status = $reviewer->getStatus();
      if (!isset($status_map[$status])) {
        return false;
      }
    }

    return true;
  }

  protected function applyReviewerEffect(
    DifferentialRevision $revision,
    PhabricatorUser $viewer,
    $value,
    $status) {

    $map = array();

    // When you accept or reject, you may accept or reject on behalf of all
    // reviewers you have authority for. When you resign, you only affect
    // yourself.
    $with_authority = ($status != DifferentialReviewerStatus::STATUS_RESIGNED);
    if ($with_authority) {
      foreach ($revision->getReviewerStatus() as $reviewer) {
        if ($reviewer->hasAuthority($viewer)) {
          $map[$reviewer->getReviewerPHID()] = $status;
        }
      }
    }

    // In all cases, you affect yourself.
    $map[$viewer->getPHID()] = $status;

    // Convert reviewer statuses into edge data.
    foreach ($map as $reviewer_phid => $reviewer_status) {
      $map[$reviewer_phid] = array(
        'data' => array(
          'status' => $reviewer_status,
        ),
      );
    }

    $src_phid = $revision->getPHID();
    $edge_type = DifferentialRevisionHasReviewerEdgeType::EDGECONST;

    $editor = new PhabricatorEdgeEditor();
    foreach ($map as $dst_phid => $edge_data) {
      if ($status == DifferentialReviewerStatus::STATUS_RESIGNED) {
        // TODO: For now, we just remove these reviewers. In the future, we will
        // store resignations explicitly.
        $editor->removeEdge($src_phid, $edge_type, $dst_phid);
      } else {
        $editor->addEdge($src_phid, $edge_type, $dst_phid, $edge_data);
      }
    }

    $editor->save();
  }

}
