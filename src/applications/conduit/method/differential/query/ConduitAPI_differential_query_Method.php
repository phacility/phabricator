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

/**
 * @group conduit
 */
final class ConduitAPI_differential_query_Method
  extends ConduitAPIMethod {

  public function getMethodDescription() {
    return "Query Differential revisions which match certain criteria.";
  }

  public function defineParamTypes() {
    $hash_types = ArcanistDifferentialRevisionHash::getTypes();
    $hash_types = implode(', ', $hash_types);

    $status_types = array(
      DifferentialRevisionQuery::STATUS_ANY,
      DifferentialRevisionQuery::STATUS_OPEN,
      DifferentialRevisionQuery::STATUS_ACCEPTED,
      DifferentialRevisionQuery::STATUS_CLOSED,
    );
    $status_types = implode(', ', $status_types);

    $order_types = array(
      DifferentialRevisionQuery::ORDER_MODIFIED,
      DifferentialRevisionQuery::ORDER_CREATED,
    );
    $order_types = implode(', ', $order_types);

    return array(
      'authors'           => 'optional list<phid>',
      'ccs'               => 'optional list<phid>',
      'reviewers'         => 'optional list<phid>',
      // TODO: Implement this, it needs to accept a repository ID in addition
      // to a path so the signature needs to be a little more complicated.
      // 'paths'          => 'optional list<pair<...>>',
      'commitHashes'      => 'optional list<pair<enum<'.
                             $hash_types.'>, string>>',
      'status'            => 'optional enum<'.$status_types.'>',
      'order'             => 'optional enum<'.$order_types.'>',
      'limit'             => 'optional uint',
      'offset'            => 'optional uint',
      'ids'               => 'optional list<uint>',
      'phids'             => 'optional list<phid>',
      'subscribers'       => 'optional list<phid>',
      'responsibleUsers'  => 'optional list<phid>',
      'branches'          => 'optional list<string>',
      'arcanistProjects'  => 'optional list<string>',
    );
  }

  public function defineReturnType() {
    return 'list<dict>';
  }

  public function defineErrorTypes() {
    return array(
      'ERR-INVALID-PARAMETER' => 'Missing or malformed parameter.',
    );
  }

  protected function execute(ConduitAPIRequest $request) {
    $authors            = $request->getValue('authors');
    $ccs                = $request->getValue('ccs');
    $reviewers          = $request->getValue('reviewers');
    $status             = $request->getValue('status');
    $order              = $request->getValue('order');
    $commit_hashes      = $request->getValue('commitHashes');
    $limit              = $request->getValue('limit');
    $offset             = $request->getValue('offset');
    $ids                = $request->getValue('ids');
    $phids              = $request->getValue('phids');
    $subscribers        = $request->getValue('subscribers');
    $responsible_users  = $request->getValue('responsibleUsers');
    $branches           = $request->getValue('branches');
    $arc_projects       = $request->getValue('arcanistProjects');

    $query = new DifferentialRevisionQuery();
    if ($authors) {
      $query->withAuthors($authors);
    }
    if ($ccs) {
      $query->withCCs($ccs);
    }
    if ($reviewers) {
      $query->withReviewers($reviewers);
    }
/* TODO: Implement.
    $paths     = $request->getValue('paths');
    if ($paths) {
      foreach ($paths as $path) {

        // (Lookup the repository IDs.)

        $query->withPath($repository_id, $path);
      }
    }
 */
    if ($commit_hashes) {
      $hash_types = ArcanistDifferentialRevisionHash::getTypes();
      foreach ($commit_hashes as $info) {
        list($type, $hash) = $info;
        if (empty($type) ||
            !in_array($type, $hash_types) ||
            empty($hash)) {
              throw new ConduitException('ERR-INVALID-PARAMETER');
        }
      }
      $query->withCommitHashes($commit_hashes);
    }

    if ($status) {
      $query->withStatus($status);
    }
    if ($order) {
      $query->setOrder($order);
    }
    if ($limit) {
      $query->setLimit($limit);
    }
    if ($offset) {
      $query->setOffset($offset);
    }
    if ($ids) {
      $query->withIDs($ids);
    }
    if ($phids) {
      $query->withPHIDs($phids);
    }
    if ($responsible_users) {
      $query->withResponsibleUsers($responsible_users);
    }
    if ($subscribers) {
      $query->withSubscribers($subscribers);
    }
    if ($branches) {
      $query->withBranches($branches);
    }
    if ($arc_projects) {
      // This is sort of special-cased, but don't make arc do an extra round
      // trip.
      $projects = id(new PhabricatorRepositoryArcanistProject())
        ->loadAllWhere(
          'name in (%Ls)',
          $arc_projects);
      if (!$projects) {
        return array();
      }

      $query->withArcanistProjectPHIDs(mpull($projects, 'getPHID'));
    }

    $query->needRelationships(true);
    $query->needCommitPHIDs(true);
    $query->needDiffIDs(true);
    $query->needActiveDiffs(true);

    $revisions = $query->execute();

    $results = array();
    foreach ($revisions as $revision) {
      $diff = $revision->getActiveDiff();
      if (!$diff) {
        continue;
      }

      $id = $revision->getID();
      $result = array(
        'id'            => $id,
        'phid'          => $revision->getPHID(),
        'title'         => $revision->getTitle(),
        'uri'           => PhabricatorEnv::getProductionURI('/D'.$id),
        'dateCreated'   => $revision->getDateCreated(),
        'dateModified'  => $revision->getDateModified(),
        'authorPHID'    => $revision->getAuthorPHID(),
        'status'        => $revision->getStatus(),
        'statusName'    =>
          ArcanistDifferentialRevisionStatus::getNameForRevisionStatus(
            $revision->getStatus()),
        'branch'        => $diff->getBranch(),
        'summary'       => $revision->getSummary(),
        'testPlan'      => $revision->getTestPlan(),
        'lineCount'     => $revision->getLineCount(),
        'diffs'         => $revision->getDiffIDs(),
        'commits'       => $revision->getCommitPHIDs(),
        'reviewers'     => array_values($revision->getReviewers()),
        'ccs'           => array_values($revision->getCCPHIDs()),
      );

      // TODO: This is a hacky way to put permissions on this field until we
      // have first-class support, see T838.
      if ($revision->getAuthorPHID() == $request->getUser()->getPHID()) {
        $result['sourcePath'] = $diff->getSourcePath();
      }

      $results[] = $result;
    }

    return $results;
  }
}
