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
  }

  public function getAttachmentForObject($object, $data, $spec) {
    $reviewers = $object->getReviewers();

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
      );
    }

    return array(
      'reviewers' => $list,
    );
  }

}
