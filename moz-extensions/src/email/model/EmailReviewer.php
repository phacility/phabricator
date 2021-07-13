<?php


class EmailReviewer {
  public string $name;
  public bool $isActionable;
  /** either 'accepted', 'requested-changes' 'unreviewed' or 'blocking' */
  public string $status;
  /** @var EmailRecipient[] */
  public array $recipients;

  /**
   * @param string $name
   * @param bool $isActionable
   * @param string $status
   * @param EmailRecipient[] $recipients
   */
  public function __construct(string $name, bool $isActionable, string $status, array $recipients) {
    $this->name = $name;
    $this->isActionable = $isActionable;
    $this->status = $status;
    $this->recipients = $recipients;
  }

  public static function translateStatus(string $rawStatus): string {
    if ($rawStatus == 'accepted') {
      return 'accepted';
    } else if ($rawStatus == 'rejected') {
      return 'requested-changes';
    } else if ($rawStatus == 'blocking') {
      return 'blocking';
    } else {
      return 'unreviewed';
    }
  }

  public static function isActionable($allReviewerStatuses, $rawReviewer): bool {
    $isAllNonblockingUnreviewed = count(array_filter($allReviewerStatuses, function($status): bool {
        return $status != 'added';
      })) == 0;

    $status = self::translateStatus($rawReviewer->getReviewerStatus());
    // This reviewer is actionable if they haven't already accepted (without it being voided with a "re-request
    //     review" action)
    // AND either this reviewer is blocking/has responded to this review request and needs to follow-up,
    //     or every reviewer is non-blocking and has not responded to this review request yet (and any
    //     review is sufficient)
    return !($status == 'accepted' && !$rawReviewer->getVoidedPHID())
      && ($status != 'unreviewed' || $isAllNonblockingUnreviewed);
  }
}