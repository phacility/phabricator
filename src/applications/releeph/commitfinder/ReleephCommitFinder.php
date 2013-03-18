<?php

final class ReleephCommitFinder {

  private $releephProject;

  public function setReleephProject(ReleephProject $rp) {
    $this->releephProject = $rp;
    return $this;
  }

  public function fromPartial($partial_string) {
    // Look for diffs
    $matches = array();
    if (preg_match('/^D([1-9]\d*)$/', $partial_string, $matches)) {
      $diff_id = $matches[1];
      $diff_rev = id(new DifferentialRevision())->load($diff_id);
      if (!$diff_rev) {
        throw new ReleephCommitFinderException(
          "{$partial_string} does not refer to an existing diff.");
      }
      $commit_phids = $diff_rev->loadCommitPHIDs();

      if (!$commit_phids) {
        throw new ReleephCommitFinderException(
          "{$partial_string} has no commits associated with it yet.");
      }

      $commits = id(new PhabricatorRepositoryCommit())->loadAllWhere(
        'phid IN (%Ls) ORDER BY epoch ASC',
        $commit_phids);
      return head($commits);
    }

    // Look for a raw commit number, or r<callsign><commit-number>.
    $repository = $this->releephProject->loadPhabricatorRepository();
    $dr_data = null;
    $matches = array();
    if (preg_match('/^r(?P<callsign>[A-Z]+)(?P<commit>\w+)$/',
      $partial_string, $matches)) {
      $callsign = $matches['callsign'];
      if ($callsign != $repository->getCallsign()) {
        throw new ReleephCommitFinderException(sprintf(
          "%s is in a different repository to this Releeph project (%s).",
          $partial_string,
          $repository->getCallsign()));
      } else {
        $dr_data = $matches;
      }
    } else {
      $dr_data = array(
        'callsign' => $repository->getCallsign(),
        'commit' => $partial_string
      );
    }

    try {
      $dr = DiffusionRequest::newFromDictionary($dr_data);
    } catch (Exception $ex) {
      $message = "No commit matches {$partial_string}: ".$ex->getMessage();
      throw new ReleephCommitFinderException($message);
    }

    $phabricator_repository_commit = $dr->loadCommit();

    if (!$phabricator_repository_commit) {
      throw new ReleephCommitFinderException(
        "The commit {$partial_string} doesn't exist in this repository.");
    }

    return $phabricator_repository_commit;
  }

}
