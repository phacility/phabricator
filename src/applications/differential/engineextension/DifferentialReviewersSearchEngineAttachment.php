<?php

final class DifferentialReviewersSearchEngineAttachment
  extends PhabricatorSearchEngineAttachment {

  public function getAttachmentName() {
    return pht('Differential Reviewers');
  }

  public function getAttachmentDescription() {
    return pht('Get the reviewers for each revision.');
  }

  public function willLoadAttachmentData($query, $spec) {
    $query->needReviewers(true);
    $query->needActiveDiffs(true);
  }

  public function getAttachmentForObject($object, $data, $spec) {
    $reviewers = $object->getReviewers();
    $diff_phid = $object->getActiveDiff()->getPHID();

    $status_blocking = DifferentialReviewerStatus::STATUS_BLOCKING;

    $list = array();
    foreach ($reviewers as $reviewer) {
      $status = $reviewer->getReviewerStatus();
      $is_blocking = ($status == $status_blocking);

      $list[] = array(
        'reviewerPHID' => $reviewer->getReviewerPHID(),
        'status' => $status,
        'isBlocking' => $is_blocking,
        'actorPHID' => $reviewer->getLastActorPHID(),
        'isCurrentAction' => $reviewer->isCurrentAction($diff_phid),
      );
    }

    return array(
      'reviewers' => $list,
    );
  }

}
