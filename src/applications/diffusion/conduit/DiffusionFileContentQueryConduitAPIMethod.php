<?php

final class DiffusionFileContentQueryConduitAPIMethod
  extends DiffusionQueryConduitAPIMethod {

  public function getAPIMethodName() {
    return 'diffusion.filecontentquery';
  }

  public function getMethodDescription() {
    return pht('Retrieve file content from a repository.');
  }

  protected function defineReturnType() {
    return 'array';
  }

  protected function defineCustomParamTypes() {
    return array(
      'path' => 'required string',
      'commit' => 'required string',
    ) + DiffusionFileFutureQuery::getConduitParameters();
  }

  protected function getResult(ConduitAPIRequest $request) {
    $drequest = $this->getDiffusionRequest();

    return DiffusionFileContentQuery::newFromDiffusionRequest($drequest)
      ->respondToConduitRequest($request);
  }

}
