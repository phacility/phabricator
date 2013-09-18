<?php

final class PonderTransactionFeedStory
  extends PhabricatorApplicationTransactionFeedStory {

  public function getRequiredObjectPHIDs() {
    $phids = parent::getRequiredObjectPHIDs();
    $answer_phid = $this->getValue('answerPHID');
    if ($answer_phid) {
      $phids[] = $answer_phid;
    }
    return $phids;
  }
}
