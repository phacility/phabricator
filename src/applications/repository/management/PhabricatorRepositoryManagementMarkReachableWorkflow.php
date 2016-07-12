<?php

final class PhabricatorRepositoryManagementMarkReachableWorkflow
  extends PhabricatorRepositoryManagementWorkflow {

  private $untouchedCount = 0;

  protected function didConstruct() {
    $this
      ->setName('mark-reachable')
      ->setExamples('**mark-reachable** [__options__] __repository__ ...')
      ->setSynopsis(
        pht(
          'Rebuild "unreachable" flags for commits in __repository__.'))
      ->setArguments(
        array(
          array(
            'name' => 'repos',
            'wildcard' => true,
          ),
        ));
  }

  public function execute(PhutilArgumentParser $args) {
    $repos = $this->loadRepositories($args, 'repos');
    if (!$repos) {
      throw new PhutilArgumentUsageException(
        pht(
          'Specify one or more repositories to correct reachability status '.
          'for.'));
    }

    foreach ($repos as $repo) {
      $this->markReachable($repo);
    }

    echo tsprintf(
      "%s\n",
      pht(
        'Examined %s commits already in the correct state.',
        new PhutilNumber($this->untouchedCount)));

    echo tsprintf(
      "%s\n",
      pht('Done.'));

    return 0;
  }

  private function markReachable(PhabricatorRepository $repository) {
    if (!$repository->isGit()) {
      throw new PhutilArgumentUsageException(
        pht(
          'Only Git repositories are supported, this repository ("%s") is '.
          'not a Git repository.',
          $repository->getDisplayName()));
    }

    $viewer = $this->getViewer();

    $commits = id(new DiffusionCommitQuery())
      ->setViewer($viewer)
      ->withRepository($repository)
      ->execute();

    $flag = PhabricatorRepositoryCommit::IMPORTED_UNREACHABLE;

    $graph = new PhabricatorGitGraphStream($repository);
    foreach ($commits as $commit) {
      $identifier = $commit->getCommitIdentifier();

      try {
        $graph->getCommitDate($identifier);
        $unreachable = false;
      } catch (Exception $ex) {
        $unreachable = true;
      }

      // The commit has proper reachability, so do nothing.
      if ($commit->isUnreachable() === $unreachable) {
        $this->untouchedCount++;
        continue;
      }

      if ($unreachable) {
        echo tsprintf(
          "%s: %s\n",
          $commit->getMonogram(),
          pht('Marking commit unreachable.'));

        $commit->writeImportStatusFlag($flag);
      } else {
        echo tsprintf(
          "%s: %s\n",
          $commit->getMonogram(),
          pht('Marking commit reachable.'));

        $commit->clearImportStatusFlag($flag);
      }
    }
  }

}
