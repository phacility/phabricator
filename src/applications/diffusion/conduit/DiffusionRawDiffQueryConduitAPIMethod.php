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
      'linesOfContext' => 'optional int',
      'againstCommit' => 'optional string',
    ) + DiffusionFileFutureQuery::getConduitParameters();
  }

  protected function getResult(ConduitAPIRequest $request) {
    $drequest = $this->getDiffusionRequest();

    $query = DiffusionRawDiffQuery::newFromDiffusionRequest($drequest);

    $lines_of_context = $request->getValue('linesOfContext');
    if ($lines_of_context !== null) {
      $query->setLinesOfContext($lines_of_context);
    }

    $against_commit = $request->getValue('againstCommit');
    if ($against_commit !== null) {
      $query->setAgainstCommit($against_commit);
    }

    return $query->respondToConduitRequest($request);
  }

}
