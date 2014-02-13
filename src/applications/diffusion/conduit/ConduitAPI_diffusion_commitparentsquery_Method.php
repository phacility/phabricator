<?php

/**
 * @group conduit
 */
final class ConduitAPI_diffusion_commitparentsquery_Method
  extends ConduitAPI_diffusion_abstractquery_Method {

  public function getMethodDescription() {
    return pht(
      "Get the commit identifiers for a commit's parent or parents.");
  }

  public function defineReturnType() {
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
