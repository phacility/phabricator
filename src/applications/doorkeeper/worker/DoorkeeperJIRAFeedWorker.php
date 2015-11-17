<?php

/**
 * Publishes feed stories into JIRA, using the "JIRA Issues" field to identify
 * linked issues.
 */
final class DoorkeeperJIRAFeedWorker extends DoorkeeperFeedWorker {

  private $provider;


/* -(  Publishing Stories  )------------------------------------------------- */


  /**
   * This worker is enabled when a JIRA authentication provider is active.
   */
  public function isEnabled() {
    return (bool)PhabricatorJIRAAuthProvider::getJIRAProvider();
  }


  /**
   * Publishes stories into JIRA using the JIRA API.
   */
  protected function publishFeedStory() {
    $story = $this->getFeedStory();
    $viewer = $this->getViewer();
    $provider = $this->getProvider();
    $object = $this->getStoryObject();
    $publisher = $this->getPublisher();

    $jira_issue_phids = PhabricatorEdgeQuery::loadDestinationPHIDs(
      $object->getPHID(),
      PhabricatorJiraIssueHasObjectEdgeType::EDGECONST);
    if (!$jira_issue_phids) {
      $this->log(
        "%s\n",
        pht('Story is about an object with no linked JIRA issues.'));
      return;
    }

    $do_anything = ($this->shouldPostComment() || $this->shouldPostLink());
    if (!$do_anything) {
      $this->log(
        "%s\n",
        pht('JIRA integration is configured not to post anything.'));
      return;
    }

    $xobjs = id(new DoorkeeperExternalObjectQuery())
      ->setViewer($viewer)
      ->withPHIDs($jira_issue_phids)
      ->execute();

    if (!$xobjs) {
      $this->log(
        "%s\n",
        pht('Story object has no corresponding external JIRA objects.'));
      return;
    }

    $try_users = $this->findUsersToPossess();
    if (!$try_users) {
      $this->log(
        "%s\n",
        pht('No users to act on linked JIRA objects.'));
      return;
    }


    $xobjs = mgroup($xobjs, 'getApplicationDomain');
    foreach ($xobjs as $domain => $xobj_list) {
      $accounts = id(new PhabricatorExternalAccountQuery())
        ->setViewer($viewer)
        ->withUserPHIDs($try_users)
        ->withAccountTypes(array($provider->getProviderType()))
        ->withAccountDomains(array($domain))
        ->requireCapabilities(
          array(
            PhabricatorPolicyCapability::CAN_VIEW,
            PhabricatorPolicyCapability::CAN_EDIT,
          ))
        ->execute();
      // Reorder accounts in the original order.
      // TODO: This needs to be adjusted if/when we allow you to link multiple
      // accounts.
      $accounts = mpull($accounts, null, 'getUserPHID');
      $accounts = array_select_keys($accounts, $try_users);

      foreach ($xobj_list as $xobj) {
        foreach ($accounts as $account) {
          try {
            $jira_key = $xobj->getObjectID();

            if ($this->shouldPostComment()) {
              $this->postComment($account, $jira_key);
            }

            if ($this->shouldPostLink()) {
              $this->postLink($account, $jira_key);
            }

            break;
          } catch (HTTPFutureResponseStatus $ex) {
            phlog($ex);
            $this->log(
              "%s\n",
              pht(
                'Failed to update object %s using user %s.',
                $xobj->getObjectID(),
                $account->getUserPHID()));
          }
        }
      }
    }
  }


/* -(  Internals  )---------------------------------------------------------- */


  /**
   * Get the active JIRA provider.
   *
   * @return PhabricatorJIRAAuthProvider Active JIRA auth provider.
   * @task internal
   */
  private function getProvider() {
    if (!$this->provider) {
      $provider = PhabricatorJIRAAuthProvider::getJIRAProvider();
      if (!$provider) {
        throw new PhabricatorWorkerPermanentFailureException(
          pht('No JIRA provider configured.'));
      }
      $this->provider = $provider;
    }
    return $this->provider;
  }


  /**
   * Get a list of users to act as when publishing into JIRA.
   *
   * @return list<phid> Candidate user PHIDs to act as when publishing this
   *                    story.
   * @task internal
   */
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

  private function shouldPostComment() {
    return $this->getProvider()->shouldCreateJIRAComment();
  }

  private function shouldPostLink() {
    return $this->getProvider()->shouldCreateJIRALink();
  }

  private function postComment($account, $jira_key) {
    $provider = $this->getProvider();

    $provider->newJIRAFuture(
      $account,
      'rest/api/2/issue/'.$jira_key.'/comment',
      'POST',
      array(
        'body' => $this->renderStoryText(),
      ))->resolveJSON();
  }

  private function renderStoryText() {
    $object = $this->getStoryObject();
    $publisher = $this->getPublisher();

    $text = $publisher->getStoryText($object);

    if ($this->shouldPostLink()) {
      return $text;
    } else {
      // include the link in the comment
      return $text."\n\n".$publisher->getObjectURI($object);
    }
  }

  private function postLink($account, $jira_key) {
    $provider = $this->getProvider();
    $object = $this->getStoryObject();
    $publisher = $this->getPublisher();
    $icon_uri = celerity_get_resource_uri('rsrc/favicons/favicon-16x16.png');

    $provider->newJIRAFuture(
      $account,
      'rest/api/2/issue/'.$jira_key.'/remotelink',
      'POST',

      // format documented at http://bit.ly/1K5T0Li
      array(
        'globalId' => $object->getPHID(),
        'application' => array(
          'type' => 'com.phacility.phabricator',
          'name' => 'Phabricator',
        ),
        'relationship' => 'implemented in',
        'object' => array(
          'url'     => $publisher->getObjectURI($object),
          'title'   => $publisher->getObjectTitle($object),
          'icon'    => array(
            'url16x16'  => $icon_uri,
            'title'     => 'Phabricator',
          ),
          'status' => array(
            'resolved' => $publisher->isObjectClosed($object),
          ),
        ),
      ))->resolveJSON();
  }
}
