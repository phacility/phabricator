<?php

/**
 * @group conduit
 */
final class ConduitAPI_diffusion_findsymbols_Method
  extends ConduitAPIMethod {

  public function getMethodDescription() {
    return "Retrieve Diffusion symbol information.";
  }

  public function defineParamTypes() {
    return array(
      'name'        => 'optional string',
      'namePrefix'  => 'optional string',
      'context'     => 'optional string',
      'language'    => 'optional string',
      'type'        => 'optional string',
    );
  }

  public function defineReturnType() {
    return 'nonempty list<dict>';
  }

  public function defineErrorTypes() {
    return array(
    );
  }

  protected function execute(ConduitAPIRequest $request) {
    $name = $request->getValue('name');
    $name_prefix = $request->getValue('namePrefix');
    $context = $request->getValue('context');
    $language = $request->getValue('language');
    $type = $request->getValue('type');

    $query = new DiffusionSymbolQuery();
    if ($name !== null) {
      $query->setName($name);
    }
    if ($name_prefix !== null) {
      $query->setNamePrefix($name_prefix);
    }
    if ($context !== null) {
      $query->setContext($context);
    }
    if ($language !== null) {
      $query->setLanguage($language);
    }
    if ($type !== null) {
      $query->setType($type);
    }

    $query->needPaths(true);
    $query->needArcanistProjects(true);
    $query->needRepositories(true);

    $results = $query->execute();


    $response = array();
    foreach ($results as $result) {
      $uri = $result->getURI();
      if ($uri) {
        $uri = PhabricatorEnv::getProductionURI($uri);
      }

      $response[] = array(
        'name'        => $result->getSymbolName(),
        'context'     => $result->getSymbolContext(),
        'type'        => $result->getSymbolType(),
        'language'    => $result->getSymbolLanguage(),
        'path'        => $result->getPath(),
        'line'        => $result->getLineNumber(),
        'uri'         => $uri,
      );
    }

    return $response;
  }

}
