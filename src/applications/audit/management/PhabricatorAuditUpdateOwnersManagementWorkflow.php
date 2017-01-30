<?php

final class PhabricatorAuditUpdateOwnersManagementWorkflow
  extends PhabricatorAuditManagementWorkflow {

  protected function didConstruct() {
    $this
      ->setName('update-owners')
      ->setExamples('**update-owners** ...')
      ->setSynopsis(pht('Update package relationships for commits.'))
      ->setArguments(
        array(
          array(
            'name' => 'all',
            'help' => pht('Update all commits in all repositories.'),
          ),
          array(
            'name' => 'objects',
            'wildcard' => true,
            'help' => pht('Update named commits and repositories.'),
          ),
        ));
  }

  public function execute(PhutilArgumentParser $args) {
    $viewer = $this->getViewer();

    $all = $args->getArg('all');
    $names = $args->getArg('objects');

    if (!$names && !$all) {
      throw new PhutilArgumentUsageException(
        pht(
          'Specify "--all" to update everything, or a list of specific '.
          'commits or repositories to update.'));
    } else if ($names && $all) {
      throw new PhutilArgumentUsageException(
        pht(
          'Specify either a list of objects to update or "--all", but not '.
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

    foreach ($objects as $object) {
      if ($object instanceof PhabricatorRepository) {
        $commits = id(new DiffusionCommitQuery())
          ->setViewer($viewer)
          ->withRepository($object)
          ->execute();
      } else {
        $commits = array($object);
      }

      foreach ($commits as $commit) {
        $repository = $commit->getRepository();

        $affected_paths = PhabricatorOwnerPathQuery::loadAffectedPaths(
          $repository,
          $commit,
          $viewer);

        $affected_packages = PhabricatorOwnersPackage::loadAffectedPackages(
          $repository,
          $affected_paths);

        $monograms = mpull($affected_packages, 'getMonogram');
        if ($monograms) {
          $monograms = implode(', ', $monograms);
        } else {
          $monograms = pht('none');
        }

        echo tsprintf(
          "%s\n",
          pht(
            'Updating "%s" (%s)...',
            $commit->getDisplayName(),
            $monograms));

        $commit->writeOwnersEdges(mpull($affected_packages, 'getPHID'));
      }
    }
  }

}
