<?php

final class DiffusionRawDiffQueryConduitAPIMethod
  extends DiffusionQueryConduitAPIMethod {

  public function getAPIMethodName() {
    return 'diffusion.rawdiffquery';
  }

  public function getMethodDescription() {
    return pht(
      'Get raw diff information from a repository for a specific commit at an '.
      '(optional) path.');
  }

  protected function defineReturnType() {
    return 'string';
  }

  protected function defineCustomParamTypes() {
    return array(
      'commit' => 'required string',
      'path' => 'optional string',
      'timeout' => 'optional int',
      'byteLimit' => 'optional int',
      'linesOfContext' => 'optional int',
      'againstCommit' => 'optional string',
    );
  }

  protected function getResult(ConduitAPIRequest $request) {
    $drequest = $this->getDiffusionRequest();

    $raw_query = DiffusionRawDiffQuery::newFromDiffusionRequest($drequest);

    $timeout = $request->getValue('timeout');
    if ($timeout !== null) {
      $raw_query->setTimeout($timeout);
    }

    $lines_of_context = $request->getValue('linesOfContext');
    if ($lines_of_context !== null) {
      $raw_query->setLinesOfContext($lines_of_context);
    }

    $against_commit = $request->getValue('againstCommit');
    if ($against_commit !== null) {
      $raw_query->setAgainstCommit($against_commit);
    }

    $byte_limit = $request->getValue('byteLimit');
    if ($byte_limit !== null) {
      $raw_query->setByteLimit($byte_limit);
    }

    return $raw_query->loadRawDiff();
  }

}
