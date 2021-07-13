<?php


class EmailMetadataEditedReviewer {
  public string $name;
  public bool $isActionable;
  /** either 'accepted', 'requested-changes' 'unreviewed' or 'blocking' */
  public string $status;
  /** either 'added', 'removed' or 'no-change' */
  public string $metadataChange;
  /** @var EmailRecipient[] */
  public array $recipients;

  /**
   * @param string $name
   * @param bool $isActionable
   * @param string $status
   * @param string $metadataChange
   * @param EmailRecipient[] $recipients
   */
  public function __construct(string $name, bool $isActionable, string $status, string $metadataChange, array $recipients) {
    $this->name = $name;
    $this->isActionable = $isActionable;
    $this->status = $status;
    $this->metadataChange = $metadataChange;
    $this->recipients = $recipients;
  }

  public static function from(string $reviewerPHID, DifferentialRevision $rawRevision, ReviewersTransaction $reviewersTx, PhabricatorUserStore $userStore, bool $revisionChangedToNeedReview, string $actorEmail): EmailMetadataEditedReviewer
  {
    $status = $reviewersTx->getReviewerStatus($reviewerPHID);
    $metadataChange = $reviewersTx->getReviewerChange($reviewerPHID);

    $rawReviewers = $rawRevision->getReviewers();
    $rawReviewer = current(array_filter($rawReviewers, function($rawReviewer) use ($reviewerPHID) {
      return $rawReviewer->getReviewerPHID() == $reviewerPHID;
    }));

    if ($metadataChange == 'added') {
      // Reviewer was added to this revision, properly inform them if they are actionable
      $isActionable = EmailReviewer::isActionable($reviewersTx->getAllReviewerStatuses(), $rawReviewer);
    } else if ($metadataChange == 'no-change') {
      // Reviewer was already part of this revision. Only notify them as actionable if the revision freshly
      // needs a review
      $isActionable = EmailReviewer::isActionable($reviewersTx->getAllReviewerStatuses(), $rawReviewer)
        && $revisionChangedToNeedReview;
    } else {
      // Reviewer was removed, they are not actionable
      $isActionable = false;
    }

    $reviewer = $userStore->findReviewerByPHID($reviewerPHID);
    return new EmailMetadataEditedReviewer($reviewer->name(), $isActionable, $status, $metadataChange, $reviewer->toRecipients($actorEmail));
  }
}