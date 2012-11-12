<?php

final class DiffusionMercurialBrowseQuery extends DiffusionBrowseQuery {

  protected function executeQuery() {
    $drequest = $this->getRequest();
    $repository = $drequest->getRepository();

    $path = $drequest->getPath();
    $commit = $drequest->getStableCommitName();

    // TODO: This is a really really awful mess but Mercurial doesn't offer
    // an equivalent of "git ls-files -- directory". If it's any comfort, this
    // is what "hgweb" does too, see:
    //
    //   http://selenic.com/repo/hg/file/91dc8878f888/mercurial/hgweb/webcommands.py#l320
    //
    // derp derp derp derp
    //
    // Anyway, figure out what's in this path by applying massive amounts
    // of brute force.

    list($entire_manifest) = $repository->execxLocalCommand(
      'manifest --rev %s',
      $commit);
    $entire_manifest = explode("\n", $entire_manifest);

    $results = array();

    $match_against = trim($path, '/');
    $match_len = strlen($match_against);

    // For the root, don't trim. For other paths, trim the "/" after we match.
    // We need this because Mercurial's canonical paths have no leading "/",
    // but ours do.
    $trim_len = $match_len ? $match_len + 1 : 0;

    foreach ($entire_manifest as $path) {
      if (strncmp($path, $match_against, $match_len)) {
        continue;
      }
      if (!strlen($path)) {
        continue;
      }
      $remainder = substr($path, $trim_len);
      if (!strlen($remainder)) {
        // There is a file with this exact name in the manifest, so clearly
        // it's a file.
        $this->reason = self::REASON_IS_FILE;
        return array();
      }
      $parts = explode('/', $remainder);
      if (count($parts) == 1) {
        $type = DifferentialChangeType::FILE_NORMAL;
      } else {
        $type = DifferentialChangeType::FILE_DIRECTORY;
      }
      $results[reset($parts)] = $type;
    }

    foreach ($results as $key => $type) {
      $result = new DiffusionRepositoryPath();
      $result->setPath($key);
      $result->setFileType($type);
      $result->setFullPath(ltrim($match_against.'/', '/').$key);

      $results[$key] = $result;
    }

    if (empty($results)) {
      // TODO: Detect "deleted" by issuing "hg log"?

      $this->reason = self::REASON_IS_NONEXISTENT;
    }


    return $results;
  }

}
