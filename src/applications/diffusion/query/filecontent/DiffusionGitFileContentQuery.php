<?php

final class DiffusionGitFileContentQuery extends DiffusionFileContentQuery {

  protected function getFileContentFuture() {
    $drequest = $this->getRequest();

    $repository = $drequest->getRepository();
    $path = $drequest->getPath();
    $commit = $drequest->getCommit();

    return $repository->getLocalCommandFuture(
      'cat-file blob %s:%s',
      $commit,
      $path);
  }

  protected function resolveFileContentFuture(Future $future) {
    list($corpus) = $future->resolvex();
    return $corpus;
  }

}
