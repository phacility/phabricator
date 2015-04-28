<?php

final class DiffusionCommitParentsQueryConduitAPIMethod
  extends DiffusionQueryConduitAPIMethod {

  public function getAPIMethodName() {
    return 'diffusion.commitparentsquery';
  }

  public function getMethodDescription() {
    return pht(
      "Get the commit identifiers for a commit's parent or parents.");
  }

  protected function defineReturnType() {
    return 'list<string>';
  }

  protected function defineCustomParamTypes() {
    return array(
      'commit' => 'required string',
    );
  }

  protected function getResult(ConduitAPIRequest $request) {
    $repository = $this->getRepository($request);

    return id(new DiffusionLowLevelParentsQuery())
      ->setRepository($repository)
      ->withIdentifier($request->getValue('commit'))
      ->execute();
  }

}
