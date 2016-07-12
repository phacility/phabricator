<?php

final class DiffusionSvnBlameQuery extends DiffusionBlameQuery {

  protected function newBlameFuture(DiffusionRequest $request, $path) {
    $repository = $request->getRepository();
    $commit = $request->getCommit();

    return $repository->getRemoteCommandFuture(
      'blame --force %s',
      $repository->getSubversionPathURI($path, $commit));
  }

  protected function resolveBlameFuture(ExecFuture $future) {
    list($err, $stdout) = $future->resolve();

    if ($err) {
      return null;
    }

    $result = array();
    $matches = null;

    $lines = phutil_split_lines($stdout);
    foreach ($lines as $line) {
      if (preg_match('/^\s*(\d+)/', $line, $matches)) {
        $result[] = (int)$matches[1];
      } else {
        $result[] = null;
      }
    }

    return $result;
  }

}
