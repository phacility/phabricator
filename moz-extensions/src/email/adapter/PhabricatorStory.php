<?php


class PhabricatorStory {
  public EventKind $eventKind;
  public TransactionList $transactions;
  public DifferentialRevision $revision;
  public PhabricatorUser $actor;
  public string $key;
  public int $timestamp;

  public function __construct(EventKind $eventKind, TransactionList $transactions, DifferentialRevision $revision, PhabricatorUser $actor, string $key, int $timestamp) {
    $this->eventKind = $eventKind;
    $this->transactions = $transactions;
    $this->revision = $revision;
    $this->actor = $actor;
    $this->key = $key;
    $this->timestamp = $timestamp;
  }

  public function getTransactionLink(): string {
    $anyTransaction = $this->transactions->getFirstTransaction();
    $link = '/' . $this->revision->getMonogram() . '#' . $anyTransaction->getID();
    return PhabricatorEnv::getProductionURI($link);
  }

  public static function queryStories(PhabricatorUserStore $userStore, int $limit, ?int $sinceKey): StoryQueryResult {
    $pager = (new AphrontCursorPagerView())
      ->setPageSize($limit);

    if ($sinceKey) {
      $pager->setAfterID($sinceKey);
    }

    $rawStories = (new PhabricatorFeedQuery())
      ->setOrder('oldest')
      ->setViewer(PhabricatorUser::getOmnipotentUser())
      ->executeWithCursorPager($pager);

    $lastStory = end($rawStories);
    if ($lastStory) {
      $lastKey = $lastStory->getStoryData()->getChronologicalKey();
    } else {
      $lastKey = $sinceKey;
    }

    /** @var PhabricatorStoryBuilder[] $builders */
    $builders = [];
    $revisionPHIDs = [];
    foreach ($rawStories as $rawStory) {
      $storyData = $rawStory->getStoryData();
      $internalData = $storyData->getStoryData();
      if (strpos($internalData['objectPHID'], 'PHID-DREV') !== 0) {
        continue; // We only email about events about revisions
      }

      $transactions = [];
      foreach ($internalData['transactionPHIDs'] ?? [] as $phid) {
        $transaction = $rawStory->getObject($phid);
        // Don't include transactions by other piggy-backing authors, like Herald.
        if ($transaction->getAuthorPHID() == $storyData->getAuthorPHID()) {
          $transactions[] = $transaction;
        }
      }
      $transactions = new TransactionList($transactions);

      $eventKind = EventKind::mainKind($transactions, $userStore);
      if (!$eventKind) {
        // There's nothing to email about
        continue;
      }

      $builder = new PhabricatorStoryBuilder($eventKind, $transactions, $storyData->getChronologicalKey(), $storyData->getDateCreated());
      $revisionPHIDs[] = $builder->revisionPHID;
      $builders[] = $builder;
    }

    if (!$builders) {
      // No stories were relevant
      return new StoryQueryResult($lastKey, []);
    }

    $rawRevisions = (new DifferentialRevisionQuery())
      ->setViewer(PhabricatorUser::getOmnipotentUser())
      ->withPHIDs($revisionPHIDs)
      ->needReviewers(true)
      ->needActiveDiffs(true)
      ->execute();

    $actorPHIDs = [];
    foreach ($builders as $builder) {
      foreach ($rawRevisions as $rawRevision) {
        if ($builder->revisionPHID == $rawRevision->getPHID()) {
          if ($rawRevision->getShouldBroadcast()) {
            $actorPHID = $builder->eventKind->findActor($builder->transactions, $rawRevision);
            $builder->associateRevision($rawRevision, $actorPHID);
            $actorPHIDs[] = $actorPHID;
          } else {
            // Don't publish emails for "draft" revisions
            $builder->isBroadcastable = false;
          }
          break;
        }
      }
    }

    $builders = array_filter($builders, function(PhabricatorStoryBuilder $builder) {
      return $builder->isBroadcastable;
    });
    if (empty($builders)) {
      // All new stories were for "draft" revisions
      return new StoryQueryResult($lastKey, []);
    }

    $rawUsers = $userStore->queryAll($actorPHIDs);
    $stories = [];
    foreach ($builders as $builder) {
      foreach ($rawUsers as $rawUser) {
        if ($builder->actorPHID == $rawUser->getPHID()) {
          $builder->associateActor($rawUser);
          $stories[] = $builder->asStory();
          break;
        }
      }
    }

    return new StoryQueryResult($lastKey, $stories);
  }
}