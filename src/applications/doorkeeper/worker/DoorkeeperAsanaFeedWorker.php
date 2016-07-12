<?php

/**
 * Publishes tasks representing work that needs to be done into Asana, and
 * updates the tasks as the corresponding Phabricator objects are updated.
 */
final class DoorkeeperAsanaFeedWorker extends DoorkeeperFeedWorker {

  private $provider;


/* -(  Publishing Stories  )------------------------------------------------- */


  /**
   * This worker is enabled when an Asana workspace ID is configured with
   * `asana.workspace-id`.
   */
  public function isEnabled() {
    return (bool)$this->getWorkspaceID();
  }


  /**
   * Publish stories into Asana using the Asana API.
   */
  protected function publishFeedStory() {
    $story = $this->getFeedStory();
    $data = $story->getStoryData();

    $viewer = $this->getViewer();
    $provider = $this->getProvider();
    $workspace_id = $this->getWorkspaceID();

    $object = $this->getStoryObject();
    $src_phid = $object->getPHID();

    $publisher = $this->getPublisher();

    // Figure out all the users related to the object. Users go into one of
    // four buckets:
    //
    //   - Owner: the owner of the object. This user becomes the assigned owner
    //     of the parent task.
    //   - Active: users who are responsible for the object and need to act on
    //     it. For example, reviewers of a "needs review" revision.
    //   - Passive: users who are responsible for the object, but do not need
    //     to act on it right now. For example, reviewers of a "needs revision"
    //     revision.
    //   - Follow: users who are following the object; generally CCs.

    $owner_phid = $publisher->getOwnerPHID($object);
    $active_phids = $publisher->getActiveUserPHIDs($object);
    $passive_phids = $publisher->getPassiveUserPHIDs($object);
    $follow_phids = $publisher->getCCUserPHIDs($object);

    $all_phids = array();
    $all_phids = array_merge(
      array($owner_phid),
      $active_phids,
      $passive_phids,
      $follow_phids);
    $all_phids = array_unique(array_filter($all_phids));

    $phid_aid_map = $this->lookupAsanaUserIDs($all_phids);
    if (!$phid_aid_map) {
      throw new PhabricatorWorkerPermanentFailureException(
        pht('No related users have linked Asana accounts.'));
    }

    $owner_asana_id = idx($phid_aid_map, $owner_phid);
    $all_asana_ids = array_select_keys($phid_aid_map, $all_phids);
    $all_asana_ids = array_values($all_asana_ids);

    // Even if the actor isn't a reviewer, etc., try to use their account so
    // we can post in the correct voice. If we miss, we'll try all the other
    // related users.

    $try_users = array_merge(
      array($data->getAuthorPHID()),
      array_keys($phid_aid_map));
    $try_users = array_filter($try_users);

    $access_info = $this->findAnyValidAsanaAccessToken($try_users);
    list($possessed_user, $possessed_asana_id, $oauth_token) = $access_info;

    if (!$oauth_token) {
      throw new PhabricatorWorkerPermanentFailureException(
        pht(
          'Unable to find any Asana user with valid credentials to '.
          'pull an OAuth token out of.'));
    }

    $etype_main = PhabricatorObjectHasAsanaTaskEdgeType::EDGECONST;
    $etype_sub = PhabricatorObjectHasAsanaSubtaskEdgeType::EDGECONST;

    $equery = id(new PhabricatorEdgeQuery())
      ->withSourcePHIDs(array($src_phid))
      ->withEdgeTypes(
        array(
          $etype_main,
          $etype_sub,
        ))
      ->needEdgeData(true);

    $edges = $equery->execute();

    $main_edge = head($edges[$src_phid][$etype_main]);

    $main_data = $this->getAsanaTaskData($object) + array(
      'assignee' => $owner_asana_id,
    );

    $projects = $this->getAsanaProjectIDs();

    $extra_data = array();
    if ($main_edge) {
      $extra_data = $main_edge['data'];

      $refs = id(new DoorkeeperImportEngine())
        ->setViewer($possessed_user)
        ->withPHIDs(array($main_edge['dst']))
        ->execute();

      $parent_ref = head($refs);
      if (!$parent_ref) {
        throw new PhabricatorWorkerPermanentFailureException(
          pht('%s could not be loaded.', 'DoorkeeperExternalObject'));
      }

      if ($parent_ref->getSyncFailed()) {
        throw new Exception(
          pht('Synchronization of parent task from Asana failed!'));
      } else if (!$parent_ref->getIsVisible()) {
        $this->log(
          "%s\n",
          pht('Skipping main task update, object is no longer visible.'));
        $extra_data['gone'] = true;
      } else {
        $edge_cursor = idx($main_edge['data'], 'cursor', 0);

        // TODO: This probably breaks, very rarely, on 32-bit systems.
        if ($edge_cursor <= $story->getChronologicalKey()) {
          $this->log("%s\n", pht('Updating main task.'));
          $task_id = $parent_ref->getObjectID();

          $this->makeAsanaAPICall(
            $oauth_token,
            'tasks/'.$parent_ref->getObjectID(),
            'PUT',
            $main_data);
        } else {
          $this->log(
            "%s\n",
            pht('Skipping main task update, cursor is ahead of the story.'));
        }
      }
    } else {
      // If there are no followers (CCs), and no active or passive users
      // (reviewers or auditors), and we haven't synchronized the object before,
      // don't synchronize the object.
      if (!$active_phids && !$passive_phids && !$follow_phids) {
        $this->log(
          "%s\n",
          pht('Object has no followers or active/passive users.'));
        return;
      }

      $parent = $this->makeAsanaAPICall(
        $oauth_token,
        'tasks',
        'POST',
        array(
          'workspace' => $workspace_id,
          'projects' => $projects,
          // NOTE: We initially create parent tasks in the "Later" state but
          // don't update it afterward, even if the corresponding object
          // becomes actionable. The expectation is that users will prioritize
          // tasks in responses to notifications of state changes, and that
          // we should not overwrite their choices.
          'assignee_status' => 'later',
        ) + $main_data);

      $parent_ref = $this->newRefFromResult(
        DoorkeeperBridgeAsana::OBJTYPE_TASK,
        $parent);


      $extra_data = array(
        'workspace' => $workspace_id,
      );
    }

    // Synchronize main task followers.

    $task_id = $parent_ref->getObjectID();

    // Reviewers are added as followers of the parent task silently, because
    // they receive a notification when they are assigned as the owner of their
    // subtask, so the follow notification is redundant / non-actionable.
    $silent_followers = array_select_keys($phid_aid_map, $active_phids) +
                        array_select_keys($phid_aid_map, $passive_phids);
    $silent_followers = array_values($silent_followers);

    // CCs are added as followers of the parent task with normal notifications,
    // since they won't get a secondary subtask notification.
    $noisy_followers = array_select_keys($phid_aid_map, $follow_phids);
    $noisy_followers = array_values($noisy_followers);

    // To synchronize follower data, just add all the followers. The task might
    // have additional followers, but we can't really tell how they got there:
    // were they CC'd and then unsubscribed, or did they manually follow the
    // task? Assume the latter since it's easier and less destructive and the
    // former is rare. To be fully consistent, we should enumerate followers
    // and remove unknown followers, but that's a fair amount of work for little
    // benefit, and creates a wider window for race conditions.

    // Add the silent followers first so that a user who is both a reviewer and
    // a CC gets silently added and then implicitly skipped by then noisy add.
    // They will get a subtask notification.

    // We only do this if the task still exists.
    if (empty($extra_data['gone'])) {
      $this->addFollowers($oauth_token, $task_id, $silent_followers, true);
      $this->addFollowers($oauth_token, $task_id, $noisy_followers);

      // We're also going to synchronize project data here.
      $this->addProjects($oauth_token, $task_id, $projects);
    }

    $dst_phid = $parent_ref->getExternalObject()->getPHID();

    // Update the main edge.

    $edge_data = array(
      'cursor' => $story->getChronologicalKey(),
    ) + $extra_data;

    $edge_options = array(
      'data' => $edge_data,
    );

    id(new PhabricatorEdgeEditor())
      ->addEdge($src_phid, $etype_main, $dst_phid, $edge_options)
      ->save();

    if (!$parent_ref->getIsVisible()) {
      throw new PhabricatorWorkerPermanentFailureException(
        pht(
          '%s has no visible object on the other side; this '.
          'likely indicates the Asana task has been deleted.',
          'DoorkeeperExternalObject'));
    }

    // Now, handle the subtasks.

    $sub_editor = new PhabricatorEdgeEditor();

    // First, find all the object references in Phabricator for tasks that we
    // know about and import their objects from Asana.
    $sub_edges = $edges[$src_phid][$etype_sub];
    $sub_refs = array();
    $subtask_data = $this->getAsanaSubtaskData($object);
    $have_phids = array();

    if ($sub_edges) {
      $refs = id(new DoorkeeperImportEngine())
        ->setViewer($possessed_user)
        ->withPHIDs(array_keys($sub_edges))
        ->execute();

      foreach ($refs as $ref) {
        if ($ref->getSyncFailed()) {
          throw new Exception(
            pht('Synchronization of child task from Asana failed!'));
        }
        if (!$ref->getIsVisible()) {
          $ref->getExternalObject()->delete();
          continue;
        }
        $have_phids[$ref->getExternalObject()->getPHID()] = $ref;
      }
    }

    // Remove any edges in Phabricator which don't have valid tasks in Asana.
    // These are likely tasks which have been deleted. We're going to respawn
    // them.
    foreach ($sub_edges as $sub_phid => $sub_edge) {
      if (isset($have_phids[$sub_phid])) {
        continue;
      }

      $this->log(
        "%s\n",
        pht(
          'Removing subtask edge to %s, foreign object is not visible.',
          $sub_phid));
      $sub_editor->removeEdge($src_phid, $etype_sub, $sub_phid);
      unset($sub_edges[$sub_phid]);
    }


    // For each active or passive user, we're looking for an existing, valid
    // task. If we find one we're going to update it; if we don't, we'll
    // create one. We ignore extra subtasks that we didn't create (we gain
    // nothing by deleting them and might be nuking something important) and
    // ignore subtasks which have been moved across workspaces or replanted
    // under new parents (this stuff is too edge-casey to bother checking for
    // and complicated to fix, as it needs extra API calls). However, we do
    // clean up subtasks we created whose owners are no longer associated
    // with the object.

    $subtask_states = array_fill_keys($active_phids, false) +
                      array_fill_keys($passive_phids, true);

    // Continue with only those users who have Asana credentials.

    $subtask_states = array_select_keys(
      $subtask_states,
      array_keys($phid_aid_map));

    $need_subtasks = $subtask_states;

    $user_to_ref_map = array();
    $nuke_refs = array();
    foreach ($sub_edges as $sub_phid => $sub_edge) {
      $user_phid = idx($sub_edge['data'], 'userPHID');

      if (isset($need_subtasks[$user_phid])) {
        unset($need_subtasks[$user_phid]);
        $user_to_ref_map[$user_phid] = $have_phids[$sub_phid];
      } else {
        // This user isn't associated with the object anymore, so get rid
        // of their task and edge.
        $nuke_refs[$sub_phid] = $have_phids[$sub_phid];
      }
    }

    // These are tasks we know about but which are no longer relevant -- for
    // example, because a user has been removed as a reviewer. Remove them and
    // their edges.

    foreach ($nuke_refs as $sub_phid => $ref) {
      $sub_editor->removeEdge($src_phid, $etype_sub, $sub_phid);
      $this->makeAsanaAPICall(
        $oauth_token,
        'tasks/'.$ref->getObjectID(),
        'DELETE',
        array());
      $ref->getExternalObject()->delete();
    }

    // For each user that we don't have a subtask for, create a new subtask.
    foreach ($need_subtasks as $user_phid => $is_completed) {
      $subtask = $this->makeAsanaAPICall(
        $oauth_token,
        'tasks',
        'POST',
        $subtask_data + array(
          'assignee' => $phid_aid_map[$user_phid],
          'completed' => $is_completed,
          'parent' => $parent_ref->getObjectID(),
        ));

      $subtask_ref = $this->newRefFromResult(
        DoorkeeperBridgeAsana::OBJTYPE_TASK,
        $subtask);

      $user_to_ref_map[$user_phid] = $subtask_ref;

      // We don't need to synchronize this subtask's state because we just
      // set it when we created it.
      unset($subtask_states[$user_phid]);

      // Add an edge to track this subtask.
      $sub_editor->addEdge(
        $src_phid,
        $etype_sub,
        $subtask_ref->getExternalObject()->getPHID(),
        array(
          'data' => array(
            'userPHID' => $user_phid,
          ),
        ));
    }

    // Synchronize all the previously-existing subtasks.

    foreach ($subtask_states as $user_phid => $is_completed) {
      $this->makeAsanaAPICall(
        $oauth_token,
        'tasks/'.$user_to_ref_map[$user_phid]->getObjectID(),
        'PUT',
        $subtask_data + array(
          'assignee' => $phid_aid_map[$user_phid],
          'completed' => $is_completed,
        ));
    }

    foreach ($user_to_ref_map as $user_phid => $ref) {
      // For each subtask, if the acting user isn't the same user as the subtask
      // owner, remove the acting user as a follower. Currently, the acting user
      // will be added as a follower only when they create the task, but this
      // may change in the future (e.g., closing the task may also mark them
      // as a follower). Wipe every subtask to be sure. The intent here is to
      // leave only the owner as a follower so that the acting user doesn't
      // receive notifications about changes to subtask state. Note that
      // removing followers is silent in all cases in Asana and never produces
      // any kind of notification, so this isn't self-defeating.
      if ($user_phid != $possessed_user->getPHID()) {
        $this->makeAsanaAPICall(
          $oauth_token,
          'tasks/'.$ref->getObjectID().'/removeFollowers',
          'POST',
          array(
            'followers' => array($possessed_asana_id),
          ));
      }
    }

    // Update edges on our side.

    $sub_editor->save();

    // Don't publish the "create" story, since pushing the object into Asana
    // naturally generates a notification which effectively serves the same
    // purpose as the "create" story. Similarly, "close" stories generate a
    // close notification.
    if (!$publisher->isStoryAboutObjectCreation($object) &&
        !$publisher->isStoryAboutObjectClosure($object)) {
      // Post the feed story itself to the main Asana task. We do this last
      // because everything else is idempotent, so this is the only effect we
      // can't safely run more than once.

      $text = $publisher
        ->setRenderWithImpliedContext(true)
        ->getStoryText($object);

      $this->makeAsanaAPICall(
        $oauth_token,
        'tasks/'.$parent_ref->getObjectID().'/stories',
        'POST',
        array(
          'text' => $text,
        ));
    }
  }


/* -(  Internals  )---------------------------------------------------------- */

  private function getWorkspaceID() {
    return PhabricatorEnv::getEnvConfig('asana.workspace-id');
  }

  private function getProvider() {
    if (!$this->provider) {
      $provider = PhabricatorAsanaAuthProvider::getAsanaProvider();
      if (!$provider) {
        throw new PhabricatorWorkerPermanentFailureException(
          pht('No Asana provider configured.'));
      }
      $this->provider = $provider;
    }
    return $this->provider;
  }

  private function getAsanaTaskData($object) {
    $publisher = $this->getPublisher();

    $title = $publisher->getObjectTitle($object);
    $uri = $publisher->getObjectURI($object);
    $description = $publisher->getObjectDescription($object);
    $is_completed = $publisher->isObjectClosed($object);

    $notes = array(
      $description,
      $uri,
      $this->getSynchronizationWarning(),
    );

    $notes = implode("\n\n", $notes);

    return array(
      'name' => $title,
      'notes' => $notes,
      'completed' => $is_completed,
    );
  }

  private function getAsanaSubtaskData($object) {
    $publisher = $this->getPublisher();

    $title = $publisher->getResponsibilityTitle($object);
    $uri = $publisher->getObjectURI($object);
    $description = $publisher->getObjectDescription($object);

    $notes = array(
      $description,
      $uri,
      $this->getSynchronizationWarning(),
    );

    $notes = implode("\n\n", $notes);

    return array(
      'name' => $title,
      'notes' => $notes,
    );
  }

  private function getSynchronizationWarning() {
    return pht(
      "\xE2\x9A\xA0 DO NOT EDIT THIS TASK \xE2\x9A\xA0\n".
      "\xE2\x98\xA0 Your changes will not be reflected in Phabricator.\n".
      "\xE2\x98\xA0 Your changes will be destroyed the next time state ".
      "is synchronized.");
  }

  private function lookupAsanaUserIDs($all_phids) {
    $phid_map = array();

    $all_phids = array_unique(array_filter($all_phids));
    if (!$all_phids) {
      return $phid_map;
    }

    $provider = PhabricatorAsanaAuthProvider::getAsanaProvider();

    $accounts = id(new PhabricatorExternalAccountQuery())
      ->setViewer(PhabricatorUser::getOmnipotentUser())
      ->withUserPHIDs($all_phids)
      ->withAccountTypes(array($provider->getProviderType()))
      ->withAccountDomains(array($provider->getProviderDomain()))
      ->requireCapabilities(
        array(
          PhabricatorPolicyCapability::CAN_VIEW,
          PhabricatorPolicyCapability::CAN_EDIT,
        ))
      ->execute();

    foreach ($accounts as $account) {
      $phid_map[$account->getUserPHID()] = $account->getAccountID();
    }

    // Put this back in input order.
    $phid_map = array_select_keys($phid_map, $all_phids);

    return $phid_map;
  }

  private function findAnyValidAsanaAccessToken(array $user_phids) {
    if (!$user_phids) {
      return array(null, null, null);
    }

    $provider = $this->getProvider();
    $viewer = $this->getViewer();

    $accounts = id(new PhabricatorExternalAccountQuery())
      ->setViewer($viewer)
      ->withUserPHIDs($user_phids)
      ->withAccountTypes(array($provider->getProviderType()))
      ->withAccountDomains(array($provider->getProviderDomain()))
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
    $accounts = array_select_keys($accounts, $user_phids);

    $workspace_id = $this->getWorkspaceID();

    foreach ($accounts as $account) {
      // Get a token if possible.
      $token = $provider->getOAuthAccessToken($account);
      if (!$token) {
        continue;
      }

      // Verify we can actually make a call with the token, and that the user
      // has access to the workspace in question.
      try {
        id(new PhutilAsanaFuture())
          ->setAccessToken($token)
          ->setRawAsanaQuery("workspaces/{$workspace_id}")
          ->resolve();
      } catch (Exception $ex) {
        // This token didn't make it through; try the next account.
        continue;
      }

      $user = id(new PhabricatorPeopleQuery())
        ->setViewer($viewer)
        ->withPHIDs(array($account->getUserPHID()))
        ->executeOne();
      if ($user) {
        return array($user, $account->getAccountID(), $token);
      }
    }

    return array(null, null, null);
  }

  private function makeAsanaAPICall($token, $action, $method, array $params) {
    foreach ($params as $key => $value) {
      if ($value === null) {
        unset($params[$key]);
      } else if (is_array($value)) {
        unset($params[$key]);
        foreach ($value as $skey => $svalue) {
          $params[$key.'['.$skey.']'] = $svalue;
        }
      }
    }

    return id(new PhutilAsanaFuture())
      ->setAccessToken($token)
      ->setMethod($method)
      ->setRawAsanaQuery($action, $params)
      ->resolve();
  }

  private function newRefFromResult($type, $result) {
    $ref = id(new DoorkeeperObjectRef())
      ->setApplicationType(DoorkeeperBridgeAsana::APPTYPE_ASANA)
      ->setApplicationDomain(DoorkeeperBridgeAsana::APPDOMAIN_ASANA)
      ->setObjectType($type)
      ->setObjectID($result['id'])
      ->setIsVisible(true);

    $xobj = $ref->newExternalObject();
    $ref->attachExternalObject($xobj);

    $bridge = new DoorkeeperBridgeAsana();
    $bridge->fillObjectFromData($xobj, $result);

    $xobj->save();

    return $ref;
  }

  private function addFollowers(
    $oauth_token,
    $task_id,
    array $followers,
    $silent = false) {

    if (!$followers) {
      return;
    }

    $data = array(
      'followers' => $followers,
    );

    // NOTE: This uses a currently-undocumented API feature to suppress the
    // follow notifications.
    if ($silent) {
      $data['silent'] = true;
    }

    $this->makeAsanaAPICall(
      $oauth_token,
      "tasks/{$task_id}/addFollowers",
      'POST',
      $data);
  }

  private function getAsanaProjectIDs() {
    $project_ids = array();

    $publisher = $this->getPublisher();
    $config = PhabricatorEnv::getEnvConfig('asana.project-ids');
    if (is_array($config)) {
      $ids = idx($config, get_class($publisher));
      if (is_array($ids)) {
        foreach ($ids as $id) {
          if (is_scalar($id)) {
            $project_ids[] = $id;
          }
        }
      }
    }

    return $project_ids;
  }

  private function addProjects(
    $oauth_token,
    $task_id,
    array $project_ids) {
    foreach ($project_ids as $project_id) {
      $data = array('project' => $project_id);
      $this->makeAsanaAPICall(
        $oauth_token,
        "tasks/{$task_id}/addProject",
        'POST',
        $data);
    }
  }

}
