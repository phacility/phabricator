<?php

/*
 * Copyright 2012 Facebook, Inc.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

final class PhabricatorAuditCommentEditor {

  private $commit;
  private $user;

  public function __construct(PhabricatorRepositoryCommit $commit) {
    $this->commit = $commit;
    return $this;
  }

  public function setUser(PhabricatorUser $user) {
    $this->user = $user;
    return $this;
  }

  public function addComment(PhabricatorAuditComment $comment) {

    $commit = $this->commit;
    $user = $this->user;

    $comment
      ->setActorPHID($user->getPHID())
      ->setTargetPHID($commit->getPHID())
      ->save();

    // When a user submits an audit comment, we update all the audit requests
    // they have authority over to reflect the most recent status. The general
    // idea here is that if audit has triggered for, e.g., several packages, but
    // a user owns all of them, they can clear the audit requirement in one go
    // without auditing the commit for each trigger.

    $audit_phids = self::loadAuditPHIDsForUser($this->user);
    $audit_phids = array_fill_keys($audit_phids, true);

    $relationships = id(new PhabricatorOwnersPackageCommitRelationship())
      ->loadAllWhere(
        'commitPHID = %s',
        $commit->getPHID());

    $action = $comment->getAction();
    $status_map = PhabricatorAuditActionConstants::getStatusNameMap();
    $status = idx($status_map, $action, null);

    // Status may be empty for updates which don't affect status, like
    // "comment".
    if ($status) {
      foreach ($relationships as $relationship) {
        if (empty($audit_phids[$relationship->getPackagePHID()])) {
          continue;
        }
        $relationship->setAuditStatus($status);
        $relationship->save();
      }
    }

    // TODO: News feed.
    // TODO: Search index.
    // TODO: Email.
  }


  /**
   * Load the PHIDs for all objects the user has the authority to act as an
   * audit for. This includes themselves, and any packages they are an owner
   * of.
   */
  public static function loadAuditPHIDsForUser(PhabricatorUser $user) {
    $phids = array();

    // The user can audit on their own behalf.
    $phids[$user->getPHID()] = true;

    // The user can audit on behalf of all packages they own.
    $owned_packages = id(new PhabricatorOwnersOwner())->loadAllWhere(
      'userPHID = %s',
      $user->getPHID());
    if ($owned_packages) {
      $packages = id(new PhabricatorOwnersPackage())->loadAllWhere(
        'id IN (%Ld)',
        mpull($owned_packages, 'getPackageID'));
      foreach (mpull($packages, 'getPHID') as $phid) {
        $phids[$phid] = true;
      }
    }

    // The user can audit on behalf of all projects they are a member of.
    $query = new PhabricatorProjectQuery();
    $query->setMembers(array($user->getPHID));
    $projects = $query->execute();
    foreach ($projects as $project) {
      $phids[$project->getPHID()] = true;
    }

    return array_keys($phids);
  }

}
