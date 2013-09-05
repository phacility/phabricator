<?php

final class DoorkeeperFeedWorkerJIRA extends FeedPushWorker {

  private $provider;
  private $publisher;
  private $workspaceID;
  private $feedStory;
  private $storyObject;

  private function getProvider() {
    if (!$this->provider) {
      $provider = PhabricatorAuthProviderOAuth1JIRA::getJIRAProvider();
      if (!$provider) {
        throw new PhabricatorWorkerPermanentFailureException(
          'No JIRA provider configured.');
      }
      $this->provider = $provider;
    }
    return $this->provider;
  }

  private function getFeedStory() {
    if (!$this->feedStory) {
      $story = $this->loadFeedStory();
      $this->feedStory = $story;
    }
    return $this->feedStory;
  }

  private function getViewer() {
    return PhabricatorUser::getOmnipotentUser();
  }

  private function getPublisher() {
    return $this->publisher;
  }

  private function getStoryObject() {
    if (!$this->storyObject) {
      $story = $this->getFeedStory();
      try {
        $object = $story->getPrimaryObject();
      } catch (Exception $ex) {
        throw new PhabricatorWorkerPermanentFailureException(
          $ex->getMessage());
      }
      $this->storyObject = $object;
    }
    return $this->storyObject;
  }

  protected function doWork() {
    $story = $this->getFeedStory();
    $data = $story->getStoryData();

    $viewer = $this->getViewer();
    $provider = $this->getProvider();

    $object = $this->getStoryObject();
    $src_phid = $object->getPHID();

    $chronological_key = $story->getChronologicalKey();

    $publishers = id(new PhutilSymbolLoader())
      ->setAncestorClass('DoorkeeperFeedStoryPublisher')
      ->loadObjects();
    foreach ($publishers as $publisher) {
      if ($publisher->canPublishStory($story, $object)) {
        $publisher
          ->setViewer($viewer)
          ->setFeedStory($story);

        $object = $publisher->willPublishStory($object);
        $this->storyObject = $object;

        $this->publisher = $publisher;
        $this->log("Using publisher '%s'.\n", get_class($publisher));
        break;
      }
    }

    if (!$this->publisher) {
      $this->log("Story is about an unsupported object type.\n");
      return;
    }

    $jira_issue_phids = PhabricatorEdgeQuery::loadDestinationPHIDs(
      $object->getPHID(),
      PhabricatorEdgeConfig::TYPE_PHOB_HAS_JIRAISSUE);
    if (!$jira_issue_phids) {
      $this->log("Story is about an object with no linked JIRA issues.\n");
      return;
    }

    $xobjs = id(new DoorkeeperExternalObjectQuery())
      ->setViewer($viewer)
      ->withPHIDs($jira_issue_phids)
      ->execute();

    if (!$xobjs) {
      $this->log("Story object has no corresponding external JIRA objects.\n");
      return;
    }

    $try_users = $this->findUsersToPossess();
    if (!$try_users) {
      $this->log("No users to act on linked JIRA objects.\n");
      return;
    }

    $story_text = $publisher->getStoryText($object);

    $xobjs = mgroup($xobjs, 'getApplicationDomain');
    foreach ($xobjs as $domain => $xobj_list) {
      $accounts = id(new PhabricatorExternalAccountQuery())
        ->setViewer($viewer)
        ->withUserPHIDs($try_users)
        ->withAccountTypes(array($provider->getProviderType()))
        ->withAccountDomains(array($domain))
        ->execute();
      // Reorder accounts in the original order.
      // TODO: This needs to be adjusted if/when we allow you to link multiple
      // accounts.
      $accounts = mpull($accounts, null, 'getUserPHID');
      $accounts = array_select_keys($accounts, $try_users);

      foreach ($xobj_list as $xobj) {
        foreach ($accounts as $account) {
          try {
            $provider->newJIRAFuture(
              $account,
              'rest/api/2/issue/'.$xobj->getObjectID().'/comment',
              'POST',
              array(
                'body' => $story_text,
              ))->resolveJSON();
            break;
          } catch (HTTPFutureResponseStatus $ex) {
            phlog($ex);
            $this->log(
              "Failed to update object %s using user %s.\n",
              $xobj->getObjectID(),
              $account->getUserPHID());
          }
        }
      }
    }
  }

  public function getMaximumRetryCount() {
    return 4;
  }

  public function getWaitBeforeRetry(PhabricatorWorkerTask $task) {
    $count = $task->getFailureCount();
    return (5 * 60) * pow(8, $count);
  }

  private function findUsersToPossess() {
    $object = $this->getStoryObject();
    $publisher = $this->getPublisher();
    $data = $this->getFeedStory()->getStoryData();

    // Figure out all the users related to the object. Users go into one of
    // four buckets. For JIRA integration, we don't care about which bucket
    // a user is in, since we just want to publish an update to linked objects.

    $owner_phid = $publisher->getOwnerPHID($object);
    $active_phids = $publisher->getActiveUserPHIDs($object);
    $passive_phids = $publisher->getPassiveUserPHIDs($object);
    $follow_phids = $publisher->getCCUserPHIDs($object);

    $all_phids = array_merge(
      array($owner_phid),
      $active_phids,
      $passive_phids,
      $follow_phids);
    $all_phids = array_unique(array_filter($all_phids));

    // Even if the actor isn't a reviewer, etc., try to use their account so
    // we can post in the correct voice. If we miss, we'll try all the other
    // related users.

    $try_users = array_merge(
      array($data->getAuthorPHID()),
      $all_phids);
    $try_users = array_filter($try_users);

    return $try_users;
  }

}
