<?php

final class PhabricatorUserReviewQueueField
  extends PhabricatorUserCustomField {

  private $value;

  public function getFieldKey() {
    return 'user:review_queue';
  }

  public function getFieldName() {
    return pht('Review Queue');
  }

  public function getFieldDescription() {
    return pht('Show the length of the user\'s review queue.');
  }

  public function shouldAppearInPropertyView() {
    return true;
  }

  public function renderPropertyViewValue(array $handles) {
    $user_phid = $this->getObject()->getPHID();
    $revisions = id(new DifferentialRevisionQuery())
      ->setViewer($this->getViewer())
      ->withReviewers(array($user_phid))
      ->withIsOpen(true)
      ->needReviewers(true)
      ->setLimit(100)
      ->execute();

    $len = 0;
    foreach ($revisions as $revision) {
      if (!$revision->isNeedsReview()) {
        // Draft, Change Planned, etc.
        continue;
      }

      foreach ($revision->getReviewers() as $reviewer) {
        if ($reviewer->getReviewerPHID() != $user_phid) {
          continue;
        }

        if ($reviewer->getReviewerStatus() == 'accepted') {
          // Waiting for other reviewer.
          continue;
        }
        $len++;
      }
    }

    $url = '/differential/?responsiblePHIDs%5B0%5D=' . $user_phid
         . '&statuses%5B0%5D=open()'
         . '&order=newest'
         . '&bucket=action';
    return new PhutilSafeHTML('<a href="' . $url . '">' . $len . '</a>');
  }

}
