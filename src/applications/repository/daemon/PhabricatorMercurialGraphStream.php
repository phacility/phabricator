<?php

/**
 * Streaming interface on top of "hg log" that gives us performant access to
 * the Mercurial commit graph with one nonblocking invocation of "hg". See
 * @{class:PhabricatorRepositoryPullLocalDaemon}.
 */
final class PhabricatorMercurialGraphStream
  extends PhabricatorRepositoryGraphStream {

  private $repository;
  private $iterator;

  private $parents        = array();
  private $dates          = array();
  private $local          = array();
  private $localParents   = array();

  public function __construct(PhabricatorRepository $repository, $commit) {
    $this->repository = $repository;

    $future = $repository->getLocalCommandFuture(
      'log --template %s --rev %s',
      '{rev}\1{node}\1{date}\1{parents}\2',
      hgsprintf('reverse(ancestors(%s))', $commit));

    $this->iterator = new LinesOfALargeExecFuture($future);
    $this->iterator->setDelimiter("\2");
    $this->iterator->rewind();
  }

  public function getParents($commit) {
    if (!isset($this->parents[$commit])) {
      $this->parseUntil('node', $commit);

      $local = $this->localParents[$commit];

      // The normal parsing pass gives us the local revision numbers of the
      // parents, but since we've decided we care about this data, we need to
      // convert them into full hashes. To do this, we parse to the deepest
      // one and then just look them up.

      $parents = array();
      if ($local) {
        $this->parseUntil('rev', min($local));
        foreach ($local as $rev) {
          $parents[] = $this->local[$rev];
        }
      }

      $this->parents[$commit] = $parents;

      // Throw away the local info for this commit, we no longer need it.
      unset($this->localParents[$commit]);
    }

    return $this->parents[$commit];
  }

  public function getCommitDate($commit) {
    if (!isset($this->dates[$commit])) {
      $this->parseUntil('node', $commit);
    }
    return $this->dates[$commit];
  }

  /**
   * Parse until we have consumed some object. There are two types of parses:
   * parse until we find a commit hash ($until_type = "node"), or parse until we
   * find a local commit number ($until_type = "rev"). We use the former when
   * looking up commits, and the latter when resolving parents.
   */
  private function parseUntil($until_type, $until_name) {
    if ($this->isParsed($until_type, $until_name)) {
      return;
    }

    $hglog = $this->iterator;

    while ($hglog->valid()) {
      $line = $hglog->current();
      $hglog->next();

      $line = trim($line);
      if (!strlen($line)) {
        break;
      }
      list($rev, $node, $date, $parents) = explode("\1", $line);

      $rev  = (int)$rev;
      $date = (int)head(explode('.', $date));

      $this->dates[$node]        = $date;
      $this->local[$rev]         = $node;
      $this->localParents[$node] = $this->parseParents($parents, $rev);

      if ($this->isParsed($until_type, $until_name)) {
        return;
      }
    }

    throw new Exception(
      pht(
        "No such %s '%s' in repository!",
        $until_type,
        $until_name));
  }


  /**
   * Parse a {parents} template, returning the local commit numbers.
   */
  private function parseParents($parents, $target_rev) {

    // The hg '{parents}' token is empty if there is one "natural" parent
    // (predecessor local commit ID). Otherwise, it may have one or two
    // parents. The string looks like this:
    //
    //  151:1f6c61a60586 154:1d5f799ebe1e

    $parents = trim($parents);
    if (strlen($parents)) {
      $local = array();

      $parents = explode(' ', $parents);
      foreach ($parents as $key => $parent) {
        $parent = (int)head(explode(':', $parent));
        if ($parent == -1) {
          // Initial commits will sometimes have "-1" as a parent.
          continue;
        }
        $local[] = $parent;
      }
    } else if ($target_rev) {
      // We have empty parents. If there's a predecessor, that's the local
      // parent number.
      $local = array($target_rev - 1);
    } else {
      // Initial commits will sometimes have no parents.
      $local = array();
    }

    return $local;
  }


  /**
   * Returns true if the object specified by $type ('rev' or 'node') and
   * $name (rev or node name) has been consumed from the hg process.
   */
  private function isParsed($type, $name) {
    switch ($type) {
      case 'rev':
        return isset($this->local[$name]);
      case 'node':
        return isset($this->dates[$name]);
    }
  }


}
