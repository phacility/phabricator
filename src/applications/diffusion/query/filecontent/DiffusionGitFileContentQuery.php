<?php

final class DiffusionGitFileContentQuery extends DiffusionFileContentQuery {

  public function getFileContentFuture() {
    $drequest = $this->getRequest();

    $repository = $drequest->getRepository();
    $path = $drequest->getPath();
    $commit = $drequest->getCommit();

    return $repository->getLocalCommandFuture(
      'cat-file blob %s:%s',
      $commit,
      $path);
  }

  protected function executeQueryFromFuture(Future $future) {
    list($corpus) = $future->resolvex();

    $file_content = new DiffusionFileContent();
    $file_content->setCorpus($corpus);

    return $file_content;
  }

}
