<?php

/**
 * @group conduit
 */
final class ConduitAPI_repository_query_Method
  extends ConduitAPI_repository_Method {

  public function getMethodStatus() {
    return self::METHOD_STATUS_UNSTABLE;
  }

  public function getMethodStatusDescription() {
    return "Repository methods are new and subject to change.";
  }

  public function getMethodDescription() {
    return "Query repositories.";
  }

  public function defineParamTypes() {
    return array(
    );
  }

  public function defineReturnType() {
    return 'list<dict>';
  }

  public function defineErrorTypes() {
    return array(
    );
  }

  protected function execute(ConduitAPIRequest $request) {
    $repositories = id(new PhabricatorRepository())->loadAll();

    $results = array();
    foreach ($repositories as $repository) {
      $results[] = $repository->toDictionary();
    }

    return $results;
  }
}
