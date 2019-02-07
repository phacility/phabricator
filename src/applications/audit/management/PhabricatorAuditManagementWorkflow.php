<?php

abstract class PhabricatorAuditManagementWorkflow
  extends PhabricatorManagementWorkflow {


  protected function getCommitConstraintArguments() {
    return array(
      array(
        'name' => 'all',
        'help' => pht('Update all commits in all repositories.'),
      ),
      array(
        'name' => 'objects',
        'wildcard' => true,
        'help' => pht('Update named commits and repositories.'),
      ),
    );
  }

  protected function loadCommitsWithConstraints(PhutilArgumentParser $args) {
    $viewer = $this->getViewer();

    $all = $args->getArg('all');
    $names = $args->getArg('objects');

    if (!$names && !$all) {
      throw new PhutilArgumentUsageException(
        pht(
          'Specify "--all" to affect everything, or a list of specific '.
          'commits or repositories to affect.'));
    } else if ($names && $all) {
      throw new PhutilArgumentUsageException(
        pht(
          'Specify either a list of objects to affect or "--all", but not '.
          'both.'));
    }

    if ($all) {
      $objects = new LiskMigrationIterator(new PhabricatorRepository());
    } else {
      $query = id(new PhabricatorObjectQuery())
        ->setViewer($viewer)
        ->withNames($names);

      $query->execute();

      $objects = array();

      $results = $query->getNamedResults();
      foreach ($names as $name) {
        if (!isset($results[$name])) {
          throw new PhutilArgumentUsageException(
            pht(
              'Object "%s" is not a valid object.',
              $name));
        }

        $object = $results[$name];
        if (!($object instanceof PhabricatorRepository) &&
            !($object instanceof PhabricatorRepositoryCommit)) {
          throw new PhutilArgumentUsageException(
            pht(
              'Object "%s" is not a valid repository or commit.',
              $name));
        }

        $objects[] = $object;
      }
    }

    return $objects;
  }

  protected function loadCommitsForConstraintObject($object) {
    $viewer = $this->getViewer();

    if ($object instanceof PhabricatorRepository) {
      $commits = id(new DiffusionCommitQuery())
        ->setViewer($viewer)
        ->withRepository($object)
        ->execute();
    } else {
      $commits = array($object);
    }

    return $commits;
  }

  protected function synchronizeCommitAuditState($commit_phid) {
    $viewer = $this->getViewer();

    $commit = id(new DiffusionCommitQuery())
      ->setViewer($viewer)
      ->withPHIDs(array($commit_phid))
      ->needAuditRequests(true)
      ->executeOne();
    if (!$commit) {
      return;
    }

    $old_status = $commit->getAuditStatusObject();
    $commit->updateAuditStatus($commit->getAudits());
    $new_status = $commit->getAuditStatusObject();

    if ($old_status->getKey() == $new_status->getKey()) {
      echo tsprintf(
        "%s\n",
        pht(
          'No synchronization changes for "%s".',
          $commit->getDisplayName()));
    } else {
      echo tsprintf(
        "%s\n",
        pht(
          'Synchronizing "%s": "%s" -> "%s".',
          $commit->getDisplayName(),
          $old_status->getName(),
          $new_status->getName()));

      $commit->save();
    }
  }

}
