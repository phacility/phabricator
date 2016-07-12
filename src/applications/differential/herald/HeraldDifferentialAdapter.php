<?php

abstract class HeraldDifferentialAdapter extends HeraldAdapter {

  private $repository = false;
  private $diff;

  abstract protected function loadChangesets();
  abstract protected function loadChangesetsWithHunks();

  public function getDiff() {
    return $this->diff;
  }

  public function setDiff(DifferentialDiff $diff) {
    $this->diff = $diff;
    return $this;
  }

  public function loadRepository() {
    if ($this->repository === false) {
      $repository_phid = $this->getObject()->getRepositoryPHID();

      if ($repository_phid) {
        $repository = id(new PhabricatorRepositoryQuery())
          ->setViewer(PhabricatorUser::getOmnipotentUser())
          ->withPHIDs(array($repository_phid))
          ->executeOne();
      } else {
        $repository = null;
      }

      $this->repository = $repository;
    }

    return $this->repository;
  }


  public function loadAffectedPaths() {
    $changesets = $this->loadChangesets();

    $paths = array();
    foreach ($changesets as $changeset) {
      $paths[] = $this->getAbsoluteRepositoryPathForChangeset($changeset);
    }

    return $paths;
  }

  protected function getAbsoluteRepositoryPathForChangeset(
    DifferentialChangeset $changeset) {

    $repository = $this->loadRepository();
    if (!$repository) {
      return '/'.ltrim($changeset->getFilename(), '/');
    }

    $diff = $this->getDiff();

    return $changeset->getAbsoluteRepositoryPath($repository, $diff);
  }

  public function loadContentDictionary() {
    $add_lines = DifferentialHunk::FLAG_LINES_ADDED;
    $rem_lines = DifferentialHunk::FLAG_LINES_REMOVED;
    $mask = ($add_lines | $rem_lines);
    return $this->loadContentWithMask($mask);
  }

  public function loadAddedContentDictionary() {
    return $this->loadContentWithMask(DifferentialHunk::FLAG_LINES_ADDED);
  }

  public function loadRemovedContentDictionary() {
    return $this->loadContentWithMask(DifferentialHunk::FLAG_LINES_REMOVED);
  }

  protected function loadContentWithMask($mask) {
    $changesets = $this->loadChangesetsWithHunks();

    $dict = array();
    foreach ($changesets as $changeset) {
      $content = array();
      foreach ($changeset->getHunks() as $hunk) {
        $content[] = $hunk->getContentWithMask($mask);
      }

      $path = $this->getAbsoluteRepositoryPathForChangeset($changeset);
      $dict[$path] = implode("\n", $content);
    }

    return $dict;
  }

}
