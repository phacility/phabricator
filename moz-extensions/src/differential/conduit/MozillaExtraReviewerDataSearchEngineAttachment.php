<?php

final class MozillaExtraReviewerDataSearchEngineAttachment
  extends PhabricatorSearchEngineAttachment {

  public function getAttachmentName() {
    return pht('Differential Reviewers (Extra Data)');
  }

  public function getAttachmentDescription() {
    return pht('Get additional data about reviewers for each revision.');
  }

  public function willLoadAttachmentData($query, $spec) {
    $query->needReviewers(true);
  }

  public function getAttachmentForObject($object, $data, $spec) {
    $reviewers = $object->getReviewers();

    $list = array();
    foreach ($reviewers as $reviewer) {
      $list[] = array(
        'reviewerPHID' => $reviewer->getReviewerPHID(),
        'voidedPHID' => $reviewer->getVoidedPHID(),
        'diffPHID' => $reviewer->getLastActionDiffPHID(),
      );
    }

    return array(
      'reviewers-extra' => $list,
    );
  }
}
