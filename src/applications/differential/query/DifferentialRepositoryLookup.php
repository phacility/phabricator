<?php

/**
 * Guess which tracked repository a diff comes from.
 */
final class DifferentialRepositoryLookup extends Phobject {

  private $viewer;
  private $diff;

  public function setDiff(DifferentialDiff $diff) {
    $this->diff = $diff;
    return $this;
  }

  public function setViewer(PhabricatorUser $viewer) {
    $this->viewer = $viewer;
    return $this;
  }

  public function lookupRepository() {
    $viewer = $this->viewer;
    $diff = $this->diff;

    // Look for a repository UUID.
    if ($diff->getRepositoryUUID()) {
      $repositories = id(new PhabricatorRepositoryQuery())
        ->setViewer($viewer)
        ->withUUIDs(array($diff->getRepositoryUUID()))
        ->execute();
      if ($repositories) {
        return head($repositories);
      }
    }

    // Look for the base commit in Git and Mercurial.
    $vcs = $diff->getSourceControlSystem();
    $vcs_git = PhabricatorRepositoryType::REPOSITORY_TYPE_GIT;
    $vcs_hg = PhabricatorRepositoryType::REPOSITORY_TYPE_MERCURIAL;
    if ($vcs == $vcs_git || $vcs == $vcs_hg) {
      $base = $diff->getSourceControlBaseRevision();
      if ($base) {
        $commits = id(new DiffusionCommitQuery())
          ->setViewer($viewer)
          ->withIdentifiers(array($base))
          ->execute();
        $commits = mgroup($commits, 'getRepositoryID');
        if (count($commits) == 1) {
          $repository_id = key($commits);
          $repositories = id(new PhabricatorRepositoryQuery())
            ->setViewer($viewer)
            ->withIDs(array($repository_id))
            ->execute();
          if ($repositories) {
            return head($repositories);
          }
        }
      }
    }

    // TODO: Compare SVN remote URIs? Compare Git/Hg remote URIs? Add
    // an explicit option to `.arcconfig`?

    return null;
  }

}
