<?php

/*
 * Copyright 2011 Facebook, Inc.
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
class ConduitAPI_differential_query_Method extends ConduitAPIMethod {

  public function getMethodDescription() {
    return "Query Differential revisions which match certain criteria.";
  }

  public function defineParamTypes() {
    $status_types = array(
      DifferentialRevisionQuery::STATUS_ANY,
      DifferentialRevisionQuery::STATUS_OPEN,
    );
    $status_types = implode(', ', $status_types);

    $order_types = array(
      DifferentialRevisionQuery::ORDER_MODIFIED,
      DifferentialRevisionQuery::ORDER_CREATED,
    );
    $order_types = implode(', ', $order_types);

    return array(
      'authors'   => 'optional list<phid>',
      'ccs'       => 'optional list<phid>',
      'reviewers' => 'optional list<phid>',
      'paths'     => 'optional list<string>',
      'status'    => 'optional enum<'.$status_types.'>',
      'order'     => 'optional enum<'.$order_types.'>',
      'limit'     => 'optional uint',
      'offset'    => 'optional uint',
      'ids'       => 'optional list<uint>',
      'phids'     => 'optional list<phid>',
    );
  }

  public function defineReturnType() {
    return 'list<dict>';
  }

  public function defineErrorTypes() {
    return array(
    );
  }

  protected function execute(ConduitAPIRequest $request) {
    $authors   = $request->getValue('authors');
    $ccs       = $request->getValue('ccs');
    $reviewers = $request->getValue('reviewers');
    $paths     = $request->getValue('paths');
    $status    = $request->getValue('status');
    $order     = $request->getValue('order');
    $limit     = $request->getValue('limit');
    $offset    = $request->getValue('offset');
    $ids       = $request->getValue('ids');
    $phids     = $request->getValue('phids');

    $query = new DifferentialRevisionQuery();
    $query->withAuthors($authors);
    if ($ccs) {
      $query->withCCs($ccs);
    }
    if ($reviewers) {
      $query->withReviewers($reviewers);
    }
    if ($paths) {
      foreach ($paths as $path) {
        $query->withPath($path);
      }
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

    $revisions = $query->execute();

    $results = array();
    foreach ($revisions as $revision) {
      $diff = $revision->loadActiveDiff();
      if (!$diff) {
        continue;
      }

      $revision->loadRelationships();

      $id = $revision->getID();
      $results[] = array(
        'id'          => $id,
        'phid'        => $revision->getPHID(),
        'title'       => $revision->getTitle(),
        'uri'         => PhabricatorEnv::getProductionURI('/D'.$id),
        'dateCreated' => $revision->getDateCreated(),
        'authorPHID'  => $revision->getAuthorPHID(),
        'status'      => $revision->getStatus(),
        'statusName'  => DifferentialRevisionStatus::getNameForRevisionStatus(
          $revision->getStatus()),
        'sourcePath'  => $diff->getSourcePath(),
        'summary'     => $revision->getSummary(),
        'testPlan'    => $revision->getTestPlan(),
        'lineCount'   => $revision->getLineCount(),
        'diffs'       => array_keys($revision->loadDiffs()),
        'commits'     => $revision->loadCommitPHIDs(),
        'reviewers'   => array_values($revision->getReviewers()),
        'ccs'         => array_values($revision->getCCPHIDs()),
      );
    }

    return $results;
  }

}
