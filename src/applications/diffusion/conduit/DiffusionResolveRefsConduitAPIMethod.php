<?php

final class DiffusionResolveRefsConduitAPIMethod
  extends DiffusionQueryConduitAPIMethod {

  public function getAPIMethodName() {
    return 'diffusion.resolverefs';
  }

  public function getMethodDescription() {
    return pht('Resolve references into stable, canonical identifiers.');
  }

  protected function defineReturnType() {
    return 'dict<string, list<dict<string, wild>>>';
  }

  protected function defineCustomParamTypes() {
    return array(
      'refs' => 'required list<string>',
      'types' => 'optional list<string>',
    );
  }

  protected function getResult(ConduitAPIRequest $request) {
    $refs = $request->getValue('refs');
    $types = $request->getValue('types');

    $query = id(new DiffusionLowLevelResolveRefsQuery())
      ->setRepository($this->getDiffusionRequest()->getRepository())
      ->withRefs($refs);

    if ($types) {
      $query->withTypes($types);
    }

    return $query->execute();
  }

}
