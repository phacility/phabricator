<?php

final class DiffusionSvnFileContentQuery extends DiffusionFileContentQuery {

  protected function newQueryFuture() {
    $drequest = $this->getRequest();

    $repository = $drequest->getRepository();
    $path = $drequest->getPath();
    $commit = $drequest->getCommit();

    return $repository->getRemoteCommandFuture(
      'cat %s',
      $repository->getSubversionPathURI($path, $commit));
  }

}
