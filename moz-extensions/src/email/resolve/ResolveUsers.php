<?php


class ResolveUsers {
  public DifferentialRevision $rawRevision;
  public string $actorEmail;
  public PhabricatorUserStore $userStore;

  public function __construct(DifferentialRevision $rawRevision, string $actorEmail, PhabricatorUserStore $userStore) {
    $this->rawRevision = $rawRevision;
    $this->actorEmail = $actorEmail;
    $this->userStore = $userStore;
  }


  /**
   * @return EmailRecipient
   */
  public function resolveAuthorAsRecipient(): ?EmailRecipient {
    $authorPHID = $this->rawRevision->getAuthorPHID();
    $rawAuthor = $this->userStore->find($authorPHID);

    return EmailRecipient::from($rawAuthor, $this->actorEmail);
  }

  /**
   * @return EmailRecipient[]
   */
  public function resolveReviewersAsRecipients(): array {
    $recipients = [];
    foreach ($this->rawRevision->getReviewers() as $reviewer) {
      if ($reviewer->isResigned()) {
        continue;
      }

      $reviewer = $this->userStore->findReviewerByPHID($reviewer->getReviewerPHID());
      foreach ($reviewer->toRecipients($this->actorEmail) as $recipient) {
        $recipients[] = $recipient;
      }
    }
    return $recipients;
  }

  /**
   * @param bool $revisionChangedToNeedReview
   * @return EmailReviewer[]
   */
  public function resolveReviewers(bool $revisionChangedToNeedReview): array {
    $rawReviewers = $this->resolveUnresignedReviewers();
    $reviewers = [];
    foreach ($rawReviewers as $reviewerPHID => $rawReviewer) {
      $allReviewerStatuses = array_map(function($reviewer) {
        return $reviewer->getReviewerStatus();
      }, $rawReviewers);
      $isActionable = EmailReviewer::isActionable($allReviewerStatuses, $rawReviewer) && $revisionChangedToNeedReview;
      $status = EmailReviewer::translateStatus($rawReviewer->getReviewerStatus());

      $reviewer = $this->userStore->findReviewerByPHID($reviewerPHID);
      $reviewers[] = new EmailReviewer($reviewer->name(), $isActionable, $status, $reviewer->toRecipients($this->actorEmail));
    }
    return $reviewers;
  }

  /**
   * @return EmailRecipient[]
   */
  public function resolveSubscribersAsRecipients(): array {
    $recipientPHIDs = PhabricatorSubscribersQuery::loadSubscribersForPHID($this->rawRevision->getPHID());
    $recipientUsers = array_map(fn (string $phid) => $this->userStore->findAllBySubscribersById($phid), $recipientPHIDs);
    $recipientUsers = array_merge(...$recipientUsers); // Flatten array of arrays
    $recipients = array_map(fn (PhabricatorUser $user) => EmailRecipient::from($user, $this->actorEmail), $recipientUsers);

    foreach (array_keys($this->resolveUnresignedReviewers()) as $reviewerPHID) {
      $reviewer = $this->userStore->findReviewerByPHID($reviewerPHID);
      $recipients = array_merge($recipients, $reviewer->getWatchersAsRecipients($this->actorEmail));
    }

    return array_values(array_filter($recipients));
  }

  /**
   * @return EmailRecipient[]
   */
  public function resolveAllPossibleRecipients(): array
  {
    $subscribers = $this->resolveSubscribersAsRecipients();
    $reviewers = $this->resolveReviewersAsRecipients();
    $author = $this->resolveAuthorAsRecipient();
    if ($author) {
      $authorList = [$author];
    } else {
      $authorList = [];
    }

    return array_merge($subscribers, $reviewers, $authorList);
  }

  /**
   * @return Phabricatoruser[]
   */
  private function resolveUnresignedReviewers(): array {
    $reviewers = $this->rawRevision->getReviewers();
    return array_filter($reviewers, function($rawReviewer) {
      // In the future, we could show resigned reviewers in the email body
      return !$rawReviewer->isResigned();
    });
  }
}
