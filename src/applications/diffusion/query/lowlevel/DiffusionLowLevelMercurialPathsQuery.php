<?php

/**
 * Execute and parse a low-level Mercurial paths query using `hg locate`.
 */
final class DiffusionLowLevelMercurialPathsQuery
  extends DiffusionLowLevelQuery {

  private $commit;
  private $path;

  public function withCommit($commit) {
    $this->commit = $commit;
    return $this;
  }

  public function withPath($path) {
    $this->path = $path;
    return $this;
  }

  protected function executeQuery() {
    $repository = $this->getRepository();
    $path = $this->path;
    $commit = $this->commit;

    $has_files = PhutilBinaryAnalyzer::getForBinary('hg')
      ->isMercurialFilesCommandAvailable();
    if ($has_files) {
      $hg_paths_command = 'files --print0 --rev %s -I %s';
    } else {
      $hg_paths_command = 'locate --print0 --rev %s -I %s';
    }

    $match_against = trim($path, '/');
    $prefix = trim('./'.$match_against, '/');
    list($entire_manifest) = $repository->execxLocalCommand(
      $hg_paths_command,
      hgsprintf('%s', $commit),
      $prefix);
    return explode("\0", $entire_manifest);
  }

}
