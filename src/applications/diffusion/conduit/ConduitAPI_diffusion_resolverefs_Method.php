<?php

final class ConduitAPI_diffusion_resolverefs_Method
  extends ConduitAPI_diffusion_abstractquery_Method {

  public function getMethodDescription() {
    return pht('Resolve references into stable, canonical identifiers.');
  }

  public function defineReturnType() {
    return 'dict<string, list<dict<string, wild>>>';
  }

  protected function defineCustomParamTypes() {
    return array(
      'refs' => 'required list<string>',
    );
  }

  protected function getResult(ConduitAPIRequest $request) {
    $refs = $request->getValue('refs');

    return id(new DiffusionLowLevelResolveRefsQuery())
      ->setRepository($this->getDiffusionRequest()->getRepository())
      ->withRefs($refs)
      ->execute();
  }

}
