<?php

final class DiffusionQueryPathsConduitAPIMethod
  extends DiffusionQueryConduitAPIMethod {

  public function getAPIMethodName() {
    return 'diffusion.querypaths';
  }

  public function getMethodDescription() {
    return pht('Filename search on a repository.');
  }

  protected function defineReturnType() {
    return 'list<string>';
  }

  protected function defineCustomParamTypes() {
    return array(
      'path' => 'required string',
      'commit' => 'required string',
      'pattern' => 'optional string',
      'limit' => 'optional int',
      'offset' => 'optional int',
    );
  }

  protected function getResult(ConduitAPIRequest $request) {
    $results = parent::getResult($request);
    $offset = $request->getValue('offset');
    return array_slice($results, $offset);
  }

  protected function getGitResult(ConduitAPIRequest $request) {
    $drequest = $this->getDiffusionRequest();
    $path = $drequest->getPath();
    $commit = $request->getValue('commit');
    $repository = $drequest->getRepository();

    // Recent versions of Git don't work if you pass the empty string, and
    // require "." to list everything.
    if (!strlen($path)) {
      $path = '.';
    }

    $future = $repository->getLocalCommandFuture(
      'ls-tree --name-only -r -z %s -- %s',
      gitsprintf('%s', $commit),
      $path);

    $lines = id(new LinesOfALargeExecFuture($future))->setDelimiter("\0");
    return $this->filterResults($lines, $request);
  }

  protected function getMercurialResult(ConduitAPIRequest $request) {
    $drequest = $this->getDiffusionRequest();
    $repository = $drequest->getRepository();
    $path = $request->getValue('path');
    $commit = $request->getValue('commit');

    $entire_manifest = id(new DiffusionLowLevelMercurialPathsQuery())
      ->setRepository($repository)
      ->withCommit($commit)
      ->withPath($path)
      ->execute();

    $match_against = trim($path, '/');
    $match_len = strlen($match_against);

    $lines = array();
    foreach ($entire_manifest as $path) {
      if (strlen($path) && !strncmp($path, $match_against, $match_len)) {
        $lines[] = $path;
      }
    }

    return $this->filterResults($lines, $request);
  }

  protected function filterResults($lines, ConduitAPIRequest $request) {
    $pattern = $request->getValue('pattern');
    $limit = (int)$request->getValue('limit');
    $offset = (int)$request->getValue('offset');

    if (strlen($pattern)) {
      // Add delimiters to the regex pattern.
      $pattern = '('.$pattern.')';
    }

    $results = array();
    $count = 0;
    foreach ($lines as $line) {
      if (strlen($pattern) && !preg_match($pattern, $line)) {
        continue;
      }

      $results[] = $line;
      $count++;

      if ($limit && ($count >= ($offset + $limit))) {
        break;
      }
    }

    return $results;
  }

}
