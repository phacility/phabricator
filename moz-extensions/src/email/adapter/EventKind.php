<?php


class EventKind {
  public static string $ABANDON = 'revision-abandoned';
  public static string $METADATA_EDIT = 'revision-metadata-edited';
  public static string $RECLAIM = 'revision-reclaimed';
  public static string $ACCEPT = 'revision-accepted';
  public static string $COMMENT = 'revision-commented';
  public static string $UPDATE = 'revision-updated';
  public static string $REJECT = 'revision-requested-changes';
  public static string $REQUEST_REVIEW = 'revision-requested-review';
  public static string $CLOSE = 'revision-closed';
  public static string $LAND = 'revision-landed';
  public static string $CREATE = 'revision-created';
  public static string $PINGED = 'revision-comment-pinged';

  public string $publicKind;
  private ?string $mainTransactionType;

  /**
   * @param string $publicKind public identifier value in "kind" property of {@link PublicEmailContext}
   * @param string|null $phabricatorType internal type for main transaction
   */
  public function __construct(string $publicKind, ?string $phabricatorType) {
    $this->publicKind = $publicKind;
    $this->mainTransactionType = $phabricatorType;
  }

  /**
   * For all events, we need to find the "main transaction" so we can get the revision and author of the event.
   * However, not all events are equal - for some, there's a particular transaction that is the most important.
   * For example, the "differential.revision.abandon" transaction is most important for "abandon" events.
   * However, others (such as the "metadata edited" or "comment" events) don't have a single transaction type
   * that's guaranteed to exist, so we instead consider each transaction equally important and grab any one of them.
   */
  public function findMainTransaction(TransactionList $transactionList): DifferentialTransaction {
    if ($this->mainTransactionType) {
      return $transactionList->getTransactionWithType($this->mainTransactionType);
    } else {
      return $transactionList->getAnyTransaction();
    }
  }


  public function findActor(TransactionList $transactions, DifferentialRevision $revision) {
    if ($this->publicKind != self::$CREATE) {
      // Most of the time, the transaction actor is the actor of the event.
      return $this->findMainTransaction($transactions)->getAuthorPHID();
    } else {
      // However, for "revision-created", the "main transaction" here (phab-bot setting visibility) isn't actually the
      // real event we want to email about (<user> created the revision).
      return $revision->getAuthorPHID();
    }
  }


  public static function mainKind(TransactionList $transactions, PhabricatorUserStore $userStore): ?EventKind
  {
    if ($transactions->containsType('differential.revision.abandon')) {
      return new EventKind(self::$ABANDON, 'differential.revision.abandon');
    } else if ($transactions->containsType('differential.revision.reclaim')) {
      return new EventKind(self::$RECLAIM, 'differential.revision.reclaim');
    } else if ($transactions->containsType('differential.revision.accept')) {
      return new EventKind(self::$ACCEPT, 'differential.revision.accept');
    } else if ($transactions->containsType('differential.revision.reject')) {
      return new EventKind(self::$REJECT, 'differential.revision.reject');
    } else if ($transactions->containsType('differential.revision.close')) {
      // Check for "revision has been closed" before "revision has been updated", because
      // revisions are automatically _updated_ to match their associated landed revision in the
      // repository. We want these to be properly recognized as landings and not misrepresented
      // as changes to the patch by the author.
      $revisionCloseTx = $transactions->getTransactionWithType('differential.revision.close');
      $kind = $revisionCloseTx->getMetadataValue('isCommitClose') ? self::$LAND : self::$CLOSE;
      return new EventKind($kind, 'differential.revision.close');
    } else if ($transactions->containsType('differential:update')) {
      return new EventKind(self::$UPDATE, 'differential:update');
    } else if ($transactions->containsOneOfType([
      'core:customfield',
      'differential.revision.title',
      'differential.revision.reviewers',
    ])) {
      return new EventKind(self::$METADATA_EDIT, null);
    } else if ($transactions->containsType('differential.revision.request')) {
      $reviewRequestTx = $transactions->getTransactionWithType('differential.revision.request');
      $rawActor = $userStore->find($reviewRequestTx->getAuthorPHID());
      if ($rawActor->getUserName() != 'phab-bot') {
        return new EventKind(self::$REQUEST_REVIEW, 'differential.revision.request');
      }

      // Identifying a "revision created" event is ... tricky. We can't just look for "core:create", because
      // that event happens before we identify if a revision is secure or not. So, instead, we try to identify when
      // "phab-bot" does its "secure-revision detection" work and marks the revision as ready-for-review.
      // The heuristic for identifying this is:
      // * A review was requested.
      // * The review requester was "phab-bot".
      // * In addition to requesting the review, there are also transactions for:
      //    * "core:view-policy"
      //    * "core:edit-policy"
      //    * "core:edge"
      if ($transactions->containsAllOfTypes([
        'core:view-policy',
        'core:edit-policy',
        'core:edge',
      ])) {
        return new EventKind(self::$CREATE, null);
      }
    } else if ($transactions->containsOneOfType([
      'core:comment',
      'differential:inline',
    ])) {
      return new EventKind(self::$COMMENT, null);
    }

    return null;
  }
}