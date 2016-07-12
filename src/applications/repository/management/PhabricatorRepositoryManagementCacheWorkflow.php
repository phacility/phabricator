<?php

final class PhabricatorRepositoryManagementCacheWorkflow
  extends PhabricatorRepositoryManagementWorkflow {

  protected function didConstruct() {
    $this
      ->setName('cache')
      ->setExamples(
        '**cache** [__options__] --commit __commit__ --path __path__')
      ->setSynopsis(pht('Manage the repository graph cache.'))
      ->setArguments(
        array(
          array(
            'name'    => 'commit',
            'param'   => 'commit',
            'help'    => pht('Specify a commit to look up.'),
          ),
          array(
            'name'    => 'path',
            'param'   => 'path',
            'help'    => pht('Specify a path to look up.'),
          ),
        ));
  }

  public function execute(PhutilArgumentParser $args) {

    $commit_name = $args->getArg('commit');
    if ($commit_name === null) {
      throw new PhutilArgumentUsageException(
        pht(
          'Specify a commit to look up with `%s`.',
          '--commit'));
    }
    $commit = $this->loadNamedCommit($commit_name);

    $path_name = $args->getArg('path');
    if ($path_name === null) {
      throw new PhutilArgumentUsageException(
        pht(
          'Specify a path to look up with `%s`.',
          '--path'));
    }

    $path_map = id(new DiffusionPathIDQuery(array($path_name)))
      ->loadPathIDs();
    if (empty($path_map[$path_name])) {
      throw new PhutilArgumentUsageException(
        pht('Path "%s" is not known to Phabricator.', $path_name));
    }
    $path_id = $path_map[$path_name];

    $graph_cache = new PhabricatorRepositoryGraphCache();

    $t_start = microtime(true);
    $cache_result = $graph_cache->loadLastModifiedCommitID(
      $commit->getID(),
      $path_id);
    $t_end = microtime(true);

    $console = PhutilConsole::getConsole();

    $console->writeOut(
      "%s\n",
      pht('Query took %s ms.', new PhutilNumber(1000 * ($t_end - $t_start))));

    if ($cache_result === false) {
      $console->writeOut("%s\n", pht('Not found in graph cache.'));
    } else if ($cache_result === null) {
      $console->writeOut(
        "%s\n",
        pht('Path not modified in any ancestor commit.'));
    } else {
      $last = id(new DiffusionCommitQuery())
        ->setViewer($this->getViewer())
        ->withIDs(array($cache_result))
        ->executeOne();
      if (!$last) {
        throw new Exception(pht('Cache returned bogus result!'));
      }

      $console->writeOut(
        "%s\n",
        pht(
          'Path was last changed at %s.',
          $commit->getRepository()->formatCommitName(
            $last->getcommitIdentifier())));
    }

    return 0;
  }

}
