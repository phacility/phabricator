<?php

final class DiffusionFindSymbolsConduitAPIMethod
  extends DiffusionConduitAPIMethod {

  public function getAPIMethodName() {
    return 'diffusion.findsymbols';
  }

  public function getMethodDescription() {
    return pht('Retrieve Diffusion symbol information.');
  }

  protected function defineParamTypes() {
    return array(
      'name'           => 'optional string',
      'namePrefix'     => 'optional string',
      'context'        => 'optional string',
      'language'       => 'optional string',
      'type'           => 'optional string',
      'repositoryPHID' => 'optional string',
    );
  }

  protected function defineReturnType() {
    return 'nonempty list<dict>';
  }

  protected function execute(ConduitAPIRequest $request) {
    $name = $request->getValue('name');
    $name_prefix = $request->getValue('namePrefix');
    $context = $request->getValue('context');
    $language = $request->getValue('language');
    $type = $request->getValue('type');
    $repository = $request->getValue('repositoryPHID');

    $query = id(new DiffusionSymbolQuery())
      ->setViewer($request->getUser());
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
    if ($repository !== null) {
      $query->withRepositoryPHIDs(array($repository));
    }

    $query->needPaths(true);
    $query->needRepositories(true);

    $results = $query->execute();


    $response = array();
    foreach ($results as $result) {
      $uri = $result->getURI();
      if ($uri) {
        $uri = PhabricatorEnv::getProductionURI($uri);
      }

      $response[] = array(
        'name'           => $result->getSymbolName(),
        'context'        => $result->getSymbolContext(),
        'type'           => $result->getSymbolType(),
        'language'       => $result->getSymbolLanguage(),
        'path'           => $result->getPath(),
        'line'           => $result->getLineNumber(),
        'uri'            => $uri,
        'repositoryPHID' => $result->getRepository()->getPHID(),
      );
    }

    return $response;
  }

}
