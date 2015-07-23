<?php

final class DiffusionSearchQueryConduitAPIMethod
  extends DiffusionQueryConduitAPIMethod {

  public function getAPIMethodName() {
    return 'diffusion.searchquery';
  }

  public function getMethodDescription() {
    return pht('Search (grep) a repository at a specific path and commit.');
  }

  protected function defineReturnType() {
    return 'array';
  }

  protected function defineCustomParamTypes() {
    return array(
      'path' => 'required string',
      'commit' => 'optional string',
      'grep' => 'required string',
      'limit' => 'optional int',
      'offset' => 'optional int',
    );
  }

  protected function defineCustomErrorTypes() {
    return array(
      'ERR-GREP-COMMAND' => pht('Grep command failed.'),
    );
  }

  protected function getResult(ConduitAPIRequest $request) {
    try {
      $results = parent::getResult($request);
    } catch (CommandException $ex) {
      throw id(new ConduitException('ERR-GREP-COMMAND'))
        ->setErrorDescription($ex->getStderr());
    }

    $offset = $request->getValue('offset');
    $results = array_slice($results, $offset);

    return $results;
  }

  protected function getGitResult(ConduitAPIRequest $request) {
    $drequest = $this->getDiffusionRequest();
    $path = $drequest->getPath();
    $grep = $request->getValue('grep');
    $repository = $drequest->getRepository();
    $limit = $request->getValue('limit');
    $offset = $request->getValue('offset');

    $results = array();
    $future = $repository->getLocalCommandFuture(
      // NOTE: --perl-regexp is available only with libpcre compiled in.
      'grep --extended-regexp --null -n --no-color -e %s %s -- %s',
      $grep,
      $drequest->getStableCommit(),
      $path);

    $binary_pattern = '/Binary file [^:]*:(.+) matches/';
    $lines = new LinesOfALargeExecFuture($future);
    foreach ($lines as $line) {
      $result = null;
      if (preg_match('/[^:]*:(.+)\0(.+)\0(.*)/', $line, $result)) {
        $results[] = array_slice($result, 1);
      } else if (preg_match($binary_pattern, $line, $result)) {
        list(, $path) = $result;
        $results[] = array($path, null, pht('Binary file'));
      } else {
        $results[] = array(null, null, $line);
      }
      if (count($results) >= $offset + $limit) {
        break;
      }
    }
    unset($lines);

    return $results;
  }

  protected function getMercurialResult(ConduitAPIRequest $request) {
    $drequest = $this->getDiffusionRequest();
    $path = $drequest->getPath();
    $grep = $request->getValue('grep');
    $repository = $drequest->getRepository();
    $limit = $request->getValue('limit');
    $offset = $request->getValue('offset');

    $results = array();
    $future = $repository->getLocalCommandFuture(
      'grep --rev %s --print0 --line-number %s %s',
      hgsprintf('ancestors(%s)', $drequest->getStableCommit()),
      $grep,
      $path);

    $lines = id(new LinesOfALargeExecFuture($future))->setDelimiter("\0");
    $parts = array();
    foreach ($lines as $line) {
      $parts[] = $line;
      if (count($parts) == 4) {
        list($path, $char_offset, $line, $string) = $parts;
        $results[] = array($path, $line, $string);
        if (count($results) >= $offset + $limit) {
          break;
        }
        $parts = array();
      }
    }
    unset($lines);

    return $results;
  }

}
