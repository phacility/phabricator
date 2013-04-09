<?php

abstract class DifferentialFreeformFieldSpecification
  extends DifferentialFieldSpecification {

  private function findMentionedTasks($message) {
    $maniphest = 'PhabricatorApplicationManiphest';
    if (!PhabricatorApplication::isClassInstalled($maniphest)) {
      return array();
    }

    $prefixes = array(
      'resolve'       => ManiphestTaskStatus::STATUS_CLOSED_RESOLVED,
      'resolves'      => ManiphestTaskStatus::STATUS_CLOSED_RESOLVED,
      'resolved'      => ManiphestTaskStatus::STATUS_CLOSED_RESOLVED,
      'fix'           => ManiphestTaskStatus::STATUS_CLOSED_RESOLVED,
      'fixes'         => ManiphestTaskStatus::STATUS_CLOSED_RESOLVED,
      'fixed'         => ManiphestTaskStatus::STATUS_CLOSED_RESOLVED,
      'wontfix'       => ManiphestTaskStatus::STATUS_CLOSED_WONTFIX,
      'wontfixes'     => ManiphestTaskStatus::STATUS_CLOSED_WONTFIX,
      'wontfixed'     => ManiphestTaskStatus::STATUS_CLOSED_WONTFIX,
      'spite'         => ManiphestTaskStatus::STATUS_CLOSED_SPITE,
      'spites'        => ManiphestTaskStatus::STATUS_CLOSED_SPITE,
      'spited'        => ManiphestTaskStatus::STATUS_CLOSED_SPITE,
      'invalidate'    => ManiphestTaskStatus::STATUS_CLOSED_INVALID,
      'invaldiates'   => ManiphestTaskStatus::STATUS_CLOSED_INVALID,
      'invalidated'   => ManiphestTaskStatus::STATUS_CLOSED_INVALID,
      'close'         => ManiphestTaskStatus::STATUS_CLOSED_RESOLVED,
      'closes'        => ManiphestTaskStatus::STATUS_CLOSED_RESOLVED,
      'closed'        => ManiphestTaskStatus::STATUS_CLOSED_RESOLVED,
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
    preg_match_all(
      "/({$prefix_regex})\s+T(\d+)\s*({$suffix_regex})/i",
      $message,
      $matches,
      PREG_SET_ORDER);

    $tasks_statuses = array();
    foreach ($matches as $set) {
      $prefix = strtolower($set[1]);
      $task_id = (int)$set[2];
      $suffix = strtolower($set[3]);

      $status = idx($suffixes, $suffix);
      if (!$status) {
        $status = idx($prefixes, $prefix);
      }

      $tasks_statuses[$task_id] = $status;
    }

    return $tasks_statuses;
  }

  private function findDependentRevisions($message) {
    $dependents = array();

    $matches = null;
    preg_match_all(
      '/\b(?i:depends\s+on)\s+D(\d+(,\s+D\d++)*)\b/',
      $message,
      $matches);

    foreach ($matches[1] as $revisions) {
      foreach (preg_split('/,\s+D/', $revisions) as $id) {
        $dependents[$id] = $id;
      }
    }

    return $dependents;
  }

  public function didWriteRevision(DifferentialRevisionEditor $editor) {
    $message = $this->renderValueForCommitMessage(false);

    $tasks = $this->findMentionedTasks($message);
    if ($tasks) {
      $tasks = id(new ManiphestTask())
        ->loadAllWhere('id IN (%Ld)', array_keys($tasks));
      $this->saveFieldEdges(
        $editor->getRevision(),
        PhabricatorEdgeConfig::TYPE_DREV_HAS_RELATED_TASK,
        mpull($tasks, 'getPHID'));
    }

    $dependents = $this->findDependentRevisions($message);
    if ($dependents) {
      $dependents = id(new DifferentialRevision())
        ->loadAllWhere('id IN (%Ld)', $dependents);
      $this->saveFieldEdges(
        $editor->getRevision(),
        PhabricatorEdgeConfig::TYPE_DREV_DEPENDS_ON_DREV,
        mpull($dependents, 'getPHID'));
    }
  }

  private function saveFieldEdges(
    DifferentialRevision $revision,
    $edge_type,
    array $add_phids) {

    $revision_phid = $revision->getPHID();

    $old_phids = PhabricatorEdgeQuery::loadDestinationPHIDs(
      $revision_phid,
      $edge_type);

    $add_phids = array_diff($add_phids, $old_phids);
    if (!$add_phids) {
      return;
    }

    $edge_editor = id(new PhabricatorEdgeEditor())->setActor($this->getUser());
    foreach ($add_phids as $phid) {
      $edge_editor->addEdge($revision_phid, $edge_type, $phid);
    }
    // NOTE: Deletes only through the fields.
    $edge_editor->save();
  }

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

    $message = $this->renderValueForCommitMessage($is_edit = false);
    $tasks_statuses = $this->findMentionedTasks($message);
    if (!$tasks_statuses) {
      return;
    }

    $tasks = id(new ManiphestTaskQuery())
      ->withTaskIDs(array_keys($tasks_statuses))
      ->execute();

    foreach ($tasks as $task_id => $task) {
      id(new PhabricatorEdgeEditor())
        ->setActor($user)
        ->addEdge(
          $task->getPHID(),
          PhabricatorEdgeConfig::TYPE_TASK_HAS_COMMIT,
          $commit->getPHID())
        ->save();

      $status = $tasks_statuses[$task_id];
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
