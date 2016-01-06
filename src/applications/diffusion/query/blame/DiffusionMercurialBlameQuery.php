<?php

final class DiffusionMercurialBlameQuery extends DiffusionBlameQuery {

  protected function newBlameFuture(DiffusionRequest $request, $path) {
    $repository = $request->getRepository();
    $commit = $request->getCommit();

    // NOTE: We're using "--debug" to make "--changeset" give us the full
    // commit hashes.

    return $repository->getLocalCommandFuture(
      'annotate --debug --changeset --rev %s -- %s',
      $commit,
      $path);
  }

  protected function resolveBlameFuture(ExecFuture $future) {
    list($err, $stdout) = $future->resolve();

    if ($err) {
      return null;
    }

    $result = array();

    $lines = phutil_split_lines($stdout);
    foreach ($lines as $line) {
      list($commit) = explode(':', $line, 2);
      $result[] = $commit;
    }

    return $result;
  }

}
