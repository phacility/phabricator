<?php

abstract class DifferentialFreeformFieldSpecification
  extends DifferentialFieldSpecification {

  private function findMentionedTasks($message) {
    $maniphest = 'PhabricatorApplicationManiphest';
    if (!PhabricatorApplication::isClassInstalled($maniphest)) {
      return array();
    }

    $prefixes = ManiphestTaskStatus::getStatusPrefixMap();
    $suffixes = ManiphestTaskStatus::getStatusSuffixMap();

    $matches = id(new ManiphestCustomFieldStatusParser())
      ->parseCorpus($message);

    $task_statuses = array();
    foreach ($matches as $match) {
      $prefix = phutil_utf8_strtolower($match['prefix']);
      $suffix = phutil_utf8_strtolower($match['suffix']);

      $status = idx($suffixes, $suffix);
      if (!$status) {
        $status = idx($prefixes, $prefix);
      }

      foreach ($match['monograms'] as $task_monogram) {
        $task_id = (int)trim($task_monogram, 'tT');
        $task_statuses[$task_id] = $status;
      }
    }

    return $task_statuses;
  }

  private function findDependentRevisions($message) {
    $matches = id(new DifferentialCustomFieldDependsOnParser())
      ->parseCorpus($message);

    $dependents = array();
    foreach ($matches as $match) {
      foreach ($match['monograms'] as $monogram) {
        $id = (int)trim($monogram, 'dD');
        $dependents[$id] = $id;
      }
    }

    return $dependents;
  }

  public static function findRevertedCommits($message) {
    $matches = id(new DifferentialCustomFieldRevertsParser())
      ->parseCorpus($message);

    $result = array();
    foreach ($matches as $match) {
      foreach ($match['monograms'] as $monogram) {
        $result[$monogram] = $monogram;
      }
    }

    return $result;
  }

  public function didWriteRevision(DifferentialRevisionEditor $editor) {
    $message = $this->renderValueForCommitMessage(false);

    $tasks = $this->findMentionedTasks($message);
    if ($tasks) {
      $tasks = id(new ManiphestTaskQuery())
        ->setViewer($editor->getActor())
        ->withIDs(array_keys($tasks))
        ->execute();
      $this->saveFieldEdges(
        $editor->getRevision(),
        PhabricatorEdgeConfig::TYPE_DREV_HAS_RELATED_TASK,
        mpull($tasks, 'getPHID'));
    }

    $dependents = $this->findDependentRevisions($message);
    if ($dependents) {
      $dependents = id(new DifferentialRevisionQuery())
        ->setViewer($editor->getActor())
        ->withIDs($dependents)
        ->execute();
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

    $message = $this->renderValueForCommitMessage($is_edit = false);

    $user = id(new PhabricatorUser())->loadOneWhere(
      'phid = %s',
      $data->getCommitDetail('authorPHID'));
    if (!$user) {
      // TODO: Maybe after grey users, we should find a way to proceed even
      // if we don't know who the author is.
      return;
    }

    $commit_names = self::findRevertedCommits($message);
    if ($commit_names) {
      $reverts = id(new DiffusionCommitQuery())
        ->setViewer($user)
        ->withIdentifiers($commit_names)
        ->withDefaultRepository($repository)
        ->execute();
      foreach ($reverts as $revert) {
        // TODO: Do interesting things here.
      }
    }

    $tasks_statuses = $this->findMentionedTasks($message);
    if (!$tasks_statuses) {
      return;
    }

    $tasks = id(new ManiphestTaskQuery())
      ->setViewer($user)
      ->withIDs(array_keys($tasks_statuses))
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

      if ($task->getStatus() == $status) {
        // Task is already in the specified status, so skip updating it.
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
