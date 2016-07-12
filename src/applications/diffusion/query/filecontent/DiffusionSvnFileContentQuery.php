<?php

final class DiffusionSvnFileContentQuery extends DiffusionFileContentQuery {

  protected function getFileContentFuture() {
    $drequest = $this->getRequest();

    $repository = $drequest->getRepository();
    $path = $drequest->getPath();
    $commit = $drequest->getCommit();

    return $repository->getRemoteCommandFuture(
      'cat %s',
      $repository->getSubversionPathURI($path, $commit));
  }

  protected function resolveFileContentFuture(Future $future) {
    list($corpus) = $future->resolvex();
    return $corpus;
  }

}
