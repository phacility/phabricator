<?php

/**
 * @task discover   Discovering Repositories
 * @task svn        Discovering Subversion Repositories
 * @task git        Discovering Git Repositories
 * @task hg         Discovering Mercurial Repositories
 * @task internal   Internals
 */
final class PhabricatorRepositoryDiscoveryEngine
  extends PhabricatorRepositoryEngine {

  private $repairMode;
  private $commitCache = array();
  const MAX_COMMIT_CACHE_SIZE = 2048;


/* -(  Discovering Repositories  )------------------------------------------- */


  public function setRepairMode($repair_mode) {
    $this->repairMode = $repair_mode;
    return $this;
  }


  public function getRepairMode() {
    return $this->repairMode;
  }


  /**
   * @task discovery
   */
  public function discoverCommits() {
    $repository = $this->getRepository();

    $vcs = $repository->getVersionControlSystem();
    switch ($vcs) {
      case PhabricatorRepositoryType::REPOSITORY_TYPE_SVN:
        $refs = $this->discoverSubversionCommits();
        break;
      case PhabricatorRepositoryType::REPOSITORY_TYPE_MERCURIAL:
        $refs = $this->discoverMercurialCommits();
        break;
/*
  TODO: Implement this!

      case PhabricatorRepositoryType::REPOSITORY_TYPE_GIT:
        $refs = $this->discoverGitCommits();
        break;
*/
      default:
        throw new Exception("Unknown VCS '{$vcs}'!");
    }

    // Mark discovered commits in the cache.
    foreach ($refs as $ref) {
      $this->commitCache[$ref->getIdentifier()] = true;
    }

    return $refs;
  }


/* -(  Discovering Subversion Repositories  )-------------------------------- */


  /**
   * @task svn
   */
  private function discoverSubversionCommits() {
    $repository = $this->getRepository();

    $upper_bound = null;
    $limit = 1;
    $refs = array();
    do {
      // Find all the unknown commits on this path. Note that we permit
      // importing an SVN subdirectory rather than the entire repository, so
      // commits may be nonsequential.

      if ($upper_bound === null) {
        $at_rev = 'HEAD';
      } else {
        $at_rev = ($upper_bound - 1);
      }

      try {
        list($xml, $stderr) = $repository->execxRemoteCommand(
          'log --xml --quiet --limit %d %s',
          $limit,
          $repository->getSubversionBaseURI($at_rev));
      } catch (CommandException $ex) {
        $stderr = $ex->getStdErr();
        if (preg_match('/(path|File) not found/', $stderr)) {
          // We've gone all the way back through history and this path was not
          // affected by earlier commits.
          break;
        }
        throw $ex;
      }

      $xml = phutil_utf8ize($xml);
      $log = new SimpleXMLElement($xml);
      foreach ($log->logentry as $entry) {
        $identifier = (int)$entry['revision'];
        $epoch = (int)strtotime((string)$entry->date[0]);
        $refs[$identifier] = id(new PhabricatorRepositoryCommitRef())
          ->setIdentifier($identifier)
          ->setEpoch($epoch);

        if ($upper_bound === null) {
          $upper_bound = $identifier;
        } else {
          $upper_bound = min($upper_bound, $identifier);
        }
      }

      // Discover 2, 4, 8, ... 256 logs at a time. This allows us to initially
      // import large repositories fairly quickly, while pulling only as much
      // data as we need in the common case (when we've already imported the
      // repository and are just grabbing one commit at a time).
      $limit = min($limit * 2, 256);

    } while ($upper_bound > 1 && !$this->isKnownCommit($upper_bound));

    krsort($refs);
    while ($refs && $this->isKnownCommit(last($refs)->getIdentifier())) {
      array_pop($refs);
    }
    $refs = array_reverse($refs);

    return $refs;
  }


/* -(  Discovering Mercurial Repositories  )--------------------------------- */


  /**
   * @task hg
   */
  private function discoverMercurialCommits() {
    $repository = $this->getRepository();

    $branches = id(new DiffusionLowLevelMercurialBranchesQuery())
      ->setRepository($repository)
      ->execute();
    $branches = mpull($branches, 'getHeadCommitIdentifier', 'getName');

    $refs = array();
    foreach ($branches as $name => $commit) {
      $this->log("Examining branch '{$name}', at {$commit}'.");
      if (!$repository->shouldTrackBranch($name)) {
        $this->log("Skipping, branch is untracked.");
        continue;
      }

      if ($this->isKnownCommit($commit)) {
        $this->log("Skipping, tip is a known commit.");
        continue;
      }

      $this->log("Looking for new commits.");
      $refs[] = $this->discoverMercurialAncestry($repository, $commit);
    }

    return array_mergev($refs);
  }


  /**
   * @task hg
   */
  private function discoverMercurialAncestry(
    PhabricatorRepository $repository,
    $commit) {

    $discover = array($commit);
    $graph = array();
    $seen = array();

    $stream = new PhabricatorMercurialGraphStream($repository);

    // Find all the reachable, undiscovered commits. Build a graph of the
    // edges.
    while ($discover) {
      $target = array_pop($discover);

      if (empty($graph[$target])) {
        $graph[$target] = array();
      }

      $parents = $stream->getParents($target);
      foreach ($parents as $parent) {
        if ($this->isKnownCommit($parent)) {
          continue;
        }

        $graph[$target][$parent] = true;

        if (empty($seen[$parent])) {
          $seen[$parent] = true;
          $discover[] = $parent;
        }
      }
    }

    // Now, sort them topographically.
    $commits = $this->reduceGraph($graph);

    $refs = array();
    foreach ($commits as $commit) {
      $refs[] = id(new PhabricatorRepositoryCommitRef())
        ->setIdentifier($commit)
        ->setEpoch($stream->getCommitDate($commit));
    }

    return $refs;
  }


/* -(  Internals  )---------------------------------------------------------- */


  private function reduceGraph(array $edges) {
    foreach ($edges as $commit => $parents) {
      $edges[$commit] = array_keys($parents);
    }

    $graph = new PhutilDirectedScalarGraph();
    $graph->addNodes($edges);

    $commits = $graph->getTopographicallySortedNodes();

    // NOTE: We want the most ancestral nodes first, so we need to reverse the
    // list we get out of AbstractDirectedGraph.
    $commits = array_reverse($commits);

    return $commits;
  }


  private function isKnownCommit($identifier) {
    if (isset($this->commitCache[$identifier])) {
      return true;
    }

    if ($this->repairMode) {
      // In repair mode, rediscover the entire repository, ignoring the
      // database state. We can hit the local cache above, but if we miss it
      // stop the script from going to the database cache.
      return false;
    }

    $commit = id(new PhabricatorRepositoryCommit())->loadOneWhere(
      'repositoryID = %d AND commitIdentifier = %s',
      $this->getRepository()->getID(),
      $identifier);

    if (!$commit) {
      return false;
    }

    $this->commitCache[$identifier] = true;
    while (count($this->commitCache) > self::MAX_COMMIT_CACHE_SIZE) {
      array_shift($this->commitCache);
    }

    return true;
  }

}
