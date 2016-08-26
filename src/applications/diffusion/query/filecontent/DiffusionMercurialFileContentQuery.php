<?php

final class DiffusionMercurialFileContentQuery
  extends DiffusionFileContentQuery {

  protected function newQueryFuture() {
    $drequest = $this->getRequest();

    $repository = $drequest->getRepository();
    $path = $drequest->getPath();
    $commit = $drequest->getCommit();

    return $repository->getLocalCommandFuture(
      'cat --rev %s -- %s',
      $commit,
      $path);
  }

}
