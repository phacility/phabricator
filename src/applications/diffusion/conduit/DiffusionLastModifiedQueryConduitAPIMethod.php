<?php

final class DiffusionLastModifiedQueryConduitAPIMethod
  extends DiffusionQueryConduitAPIMethod {

  public function getAPIMethodName() {
    return 'diffusion.lastmodifiedquery';
  }

  public function getMethodDescription() {
    return pht('Get the commits at which paths were last modified.');
  }

  protected function defineReturnType() {
    return 'map<string, string>';
  }

  protected function defineCustomParamTypes() {
    return array(
      'paths' => 'required map<string, string>',
    );
  }

  protected function getGitResult(ConduitAPIRequest $request) {
    $drequest = $this->getDiffusionRequest();
    $repository = $drequest->getRepository();

    $paths = $request->getValue('paths');
    $results = $this->loadCommitsFromCache($paths);

    foreach ($paths as $path => $commit) {
      if (array_key_exists($path, $results)) {
        continue;
      }
      list($hash) = $repository->execxLocalCommand(
        'log -n1 %s %s -- %s',
        '--format=%H',
        gitsprintf('%s', $commit),
        $path);
      $results[$path] = trim($hash);
    }

    return $results;
  }

  protected function getSVNResult(ConduitAPIRequest $request) {
    $drequest = $this->getDiffusionRequest();
    $repository = $drequest->getRepository();

    $results = array();
    foreach ($request->getValue('paths') as $path => $commit) {
      $history_result = DiffusionQuery::callConduitWithDiffusionRequest(
        $request->getUser(),
        $drequest,
        'diffusion.historyquery',
        array(
          'commit' => $commit,
          'path' => $path,
          'limit' => 1,
          'offset' => 0,
          'needDirectChanges' => true,
          'needChildChanges' => true,
        ));

      $history_array = DiffusionPathChange::newFromConduit(
        $history_result['pathChanges']);
      if ($history_array) {
        $results[$path] = head($history_array)
          ->getCommit()
          ->getCommitIdentifier();
      }
    }

    return $results;
  }

  protected function getMercurialResult(ConduitAPIRequest $request) {
    $drequest = $this->getDiffusionRequest();
    $repository = $drequest->getRepository();

    $paths = $request->getValue('paths');
    $results = $this->loadCommitsFromCache($paths);

    foreach ($paths as $path => $commit) {
      if (array_key_exists($path, $results)) {
        continue;
      }

      list($hash) = $repository->execxLocalCommand(
        'log --template %s --limit 1 --removed --rev %s -- %s',
        '{node}',
        hgsprintf('reverse(ancestors(%s))',  $commit),
        nonempty(ltrim($path, '/'), '.'));
      $results[$path] = trim($hash);
    }

    return $results;
  }

  private function loadCommitsFromCache(array $map) {
    $drequest = $this->getDiffusionRequest();
    $repository = $drequest->getRepository();

    $path_map = id(new DiffusionPathIDQuery(array_keys($map)))
      ->loadPathIDs();

    $commit_query = id(new DiffusionCommitQuery())
      ->setViewer($drequest->getUser())
      ->withRepository($repository)
      ->withIdentifiers(array_values($map));
    $commit_query->execute();

    $commit_map = $commit_query->getIdentifierMap();
    $commit_map = mpull($commit_map, 'getID');

    $graph_cache = new PhabricatorRepositoryGraphCache();

    $results = array();

    // Spend no more than this many total seconds trying to satisfy queries
    // via the graph cache.
    $remaining_time = 10.0;
    foreach ($map as $path => $commit) {
      $path_id = idx($path_map, $path);
      if (!$path_id) {
        continue;
      }
      $commit_id = idx($commit_map, $commit);
      if (!$commit_id) {
        continue;
      }

      $t_start = microtime(true);
      $cache_result = $graph_cache->loadLastModifiedCommitID(
        $commit_id,
        $path_id,
        $remaining_time);
      $t_end = microtime(true);

      if ($cache_result !== false) {
        $results[$path] = $cache_result;
      }

      $remaining_time -= ($t_end - $t_start);
      if ($remaining_time <= 0) {
        break;
      }
    }

    if ($results) {
      $commits = id(new DiffusionCommitQuery())
        ->setViewer($drequest->getUser())
        ->withRepository($repository)
        ->withIDs($results)
        ->execute();
      foreach ($results as $path => $id) {
        $commit = idx($commits, $id);
        if ($commit) {
          $results[$path] = $commit->getCommitIdentifier();
        } else {
          unset($results[$path]);
        }
      }
    }

    return $results;
  }

}
