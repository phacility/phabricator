<?php

final class PhabricatorRepositoryManagementParentsWorkflow
  extends PhabricatorRepositoryManagementWorkflow {

  protected function didConstruct() {
    $this
      ->setName('parents')
      ->setExamples('**parents** [options] [__repository__] ...')
      ->setSynopsis(
        pht(
          'Build parent caches in repositories that are missing the data, '.
          'or rebuild them in a specific __repository__.'))
      ->setArguments(
        array(
          array(
            'name'        => 'repos',
            'wildcard'    => true,
          ),
        ));
  }

  public function execute(PhutilArgumentParser $args) {
    $repos = $this->loadRepositories($args, 'repos');
    if (!$repos) {
      $repos = id(new PhabricatorRepositoryQuery())
        ->setViewer($this->getViewer())
        ->execute();
    }

    $console = PhutilConsole::getConsole();
    foreach ($repos as $repo) {
      $monogram = $repo->getMonogram();
      if ($repo->isSVN()) {
        $console->writeOut(
          "%s\n",
          pht(
            'Skipping "%s": Subversion repositories do not require this '.
            'cache to be built.',
            $monogram));
        continue;
      }
      $this->rebuildRepository($repo);
    }

    return 0;
  }

  private function rebuildRepository(PhabricatorRepository $repo) {
    $console = PhutilConsole::getConsole();
    $console->writeOut("%s\n", pht('Rebuilding "%s"...', $repo->getMonogram()));

    $refs = id(new PhabricatorRepositoryRefCursorQuery())
      ->setViewer($this->getViewer())
      ->withRefTypes(array(PhabricatorRepositoryRefCursor::TYPE_BRANCH))
      ->withRepositoryPHIDs(array($repo->getPHID()))
      ->execute();

    $graph = array();
    foreach ($refs as $ref) {
      if (!$repo->shouldTrackBranch($ref->getRefName())) {
        continue;
      }

      $console->writeOut(
        "%s\n",
        pht('Rebuilding branch "%s"...', $ref->getRefName()));

      $commit = $ref->getCommitIdentifier();

      if ($repo->isGit()) {
        $stream = new PhabricatorGitGraphStream($repo, $commit);
      } else {
        $stream = new PhabricatorMercurialGraphStream($repo, $commit);
      }

      $discover = array($commit);
      while ($discover) {
        $target = array_pop($discover);
        if (isset($graph[$target])) {
          continue;
        }
        $graph[$target] = $stream->getParents($target);
        foreach ($graph[$target] as $parent) {
          $discover[] = $parent;
        }
      }
    }

    $console->writeOut(
      "%s\n",
      pht(
        'Found %s total commit(s); updating...',
        phutil_count($graph)));

    $commit_table = id(new PhabricatorRepositoryCommit());
    $commit_table_name = $commit_table->getTableName();
    $conn_w = $commit_table->establishConnection('w');

    $bar = id(new PhutilConsoleProgressBar())
      ->setTotal(count($graph));

    $need = array();
    foreach ($graph as $child => $parents) {
      foreach ($parents as $parent) {
        $need[$parent] = $parent;
      }
      $need[$child] = $child;
    }

    $map = array();
    foreach (array_chunk($need, 2048) as $chunk) {
      $rows = queryfx_all(
        $conn_w,
        'SELECT id, commitIdentifier FROM %T
          WHERE commitIdentifier IN (%Ls) AND repositoryID = %d',
        $commit_table_name,
        $chunk,
        $repo->getID());
      foreach ($rows as $row) {
        $map[$row['commitIdentifier']] = $row['id'];
      }
    }

    $insert_sql = array();
    $delete_sql = array();

    foreach ($graph as $child => $parents) {
      $names = $parents;
      $names[] = $child;

      foreach ($names as $name) {
        if (empty($map[$name])) {
          throw new Exception(pht('Unknown commit "%s"!', $name));
        }
      }

      if (!$parents) {
        // Write an explicit 0 to indicate "no parents" instead of "no data".
        $insert_sql[] = qsprintf(
          $conn_w,
          '(%d, 0)',
          $map[$child]);
      } else {
        foreach ($parents as $parent) {
          $insert_sql[] = qsprintf(
            $conn_w,
            '(%d, %d)',
            $map[$child],
            $map[$parent]);
        }
      }

      $delete_sql[] = $map[$child];

      $bar->update(1);
    }

    $commit_table->openTransaction();
    foreach (PhabricatorLiskDAO::chunkSQL($delete_sql) as $chunk) {
      queryfx(
        $conn_w,
        'DELETE FROM %T WHERE childCommitID IN (%Q)',
        PhabricatorRepository::TABLE_PARENTS,
        $chunk);
    }

    foreach (PhabricatorLiskDAO::chunkSQL($insert_sql) as $chunk) {
      queryfx(
        $conn_w,
        'INSERT INTO %T (childCommitID, parentCommitID) VALUES %Q',
        PhabricatorRepository::TABLE_PARENTS,
        $chunk);
    }
    $commit_table->saveTransaction();

    $bar->done();
  }

}
