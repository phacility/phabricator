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

abstract class DifferentialFreeformFieldSpecification
  extends DifferentialFieldSpecification {

  public function didParseCommit(
    PhabricatorRepository $repository,
    PhabricatorRepositoryCommit $commit,
    PhabricatorRepositoryCommitData $data) {

    $user = id(new PhabricatorUser())->loadOneWhere(
      'phid = %s',
      $data->getCommitDetail('authorPHID'));
    if (!$user) {
      return;
    }

    $prefixes = array(
      'resolves'      => ManiphestTaskStatus::STATUS_CLOSED_RESOLVED,
      'fixes'         => ManiphestTaskStatus::STATUS_CLOSED_RESOLVED,
      'wontfix'       => ManiphestTaskStatus::STATUS_CLOSED_WONTFIX,
      'wontfixes'     => ManiphestTaskStatus::STATUS_CLOSED_WONTFIX,
      'spite'         => ManiphestTaskStatus::STATUS_CLOSED_SPITE,
      'spites'        => ManiphestTaskStatus::STATUS_CLOSED_SPITE,
      'invalidate'    => ManiphestTaskStatus::STATUS_CLOSED_INVALID,
      'invaldiates'   => ManiphestTaskStatus::STATUS_CLOSED_INVALID,
      'close'         => ManiphestTaskStatus::STATUS_CLOSED_RESOLVED,
      'closes'        => ManiphestTaskStatus::STATUS_CLOSED_RESOLVED,
      'ref'           => null,
      'refs'          => null,
      'references'    => null,
      'cf.'           => null,
    );

    $suffixes = array(
      'as resolved'   => ManiphestTaskStatus::STATUS_CLOSED_RESOLVED,
      'as fixed'      => ManiphestTaskStatus::STATUS_CLOSED_RESOLVED,
      'as wontfix'    => ManiphestTaskStatus::STATUS_CLOSED_WONTFIX,
      'as spite'      => ManiphestTaskStatus::STATUS_CLOSED_SPITE,
      'out of spite'  => ManiphestTaskStatus::STATUS_CLOSED_SPITE,
      'as invalid'    => ManiphestTaskStatus::STATUS_CLOSED_INVALID,
      ''              => null,
    );

    $prefix_regex = array();
    foreach ($prefixes as $prefix => $resolution) {
      $prefix_regex[] = preg_quote($prefix, '/');
    }
    $prefix_regex = implode('|', $prefix_regex);

    $suffix_regex = array();
    foreach ($suffixes as $suffix => $resolution) {
      $suffix_regex[] = preg_quote($suffix, '/');
    }
    $suffix_regex = implode('|', $suffix_regex);

    $matches = null;
    $ok = preg_match_all(
      "/({$prefix_regex})\s+T(\d+)\s*({$suffix_regex})/i",
      $this->renderValueForCommitMessage($is_edit = false),
      $matches,
      PREG_SET_ORDER);

    if (!$ok) {
      return;
    }

    foreach ($matches as $set) {
      $prefix = strtolower($set[1]);
      $task_id = (int)$set[2];
      $suffix = strtolower($set[3]);

      $status = idx($suffixes, $suffix);
      if (!$status) {
        $status = idx($prefixes, $prefix);
      }

      $tasks = id(new ManiphestTaskQuery())
        ->withTaskIDs(array($task_id))
        ->execute();
      $task = idx($tasks, $task_id);

      if (!$task) {
        // Task doesn't exist, or the user can't see it.
        continue;
      }

      id(new PhabricatorEdgeEditor())
        ->setActor($user)
        ->addEdge(
          $task->getPHID(),
          PhabricatorEdgeConfig::TYPE_TASK_HAS_COMMIT,
          $commit->getPHID())
        ->save();

      if (!$status) {
        // Text like "Ref T123", don't change the task status.
        continue;
      }

      if ($task->getStatus() != ManiphestTaskStatus::STATUS_OPEN) {
        // Task is already closed.
        continue;
      }

      $commit_name = $repository->formatCommitName(
        $commit->getCommitIdentifier());

      $call = new ConduitCall(
        'maniphest.update',
        array(
          'id'        => $task->getID(),
          'status'    => $status,
          'comments'  => "Closed by commit {$commit_name}.",
        ));

      $call->setUser($user);
      $call->execute();
    }
  }

}
