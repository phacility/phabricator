<?php

final class RepositoryQueryConduitAPIMethod
  extends RepositoryConduitAPIMethod {

  public function getAPIMethodName() {
    return 'repository.query';
  }

  public function getMethodStatus() {
    return self::METHOD_STATUS_FROZEN;
  }

  public function getMethodStatusDescription() {
    return pht(
      'This method is frozen and will eventually be deprecated. New code '.
      'should use "diffusion.repository.query" instead.');
  }

  public function getMethodDescription() {
    return pht('Query repositories.');
  }

  public function newQueryObject() {
    return new PhabricatorRepositoryQuery();
  }

  protected function defineParamTypes() {
    return array(
      'ids' => 'optional list<int>',
      'phids' => 'optional list<phid>',
      'callsigns' => 'optional list<string>',
      'vcsTypes' => 'optional list<string>',
      'remoteURIs' => 'optional list<string>',
      'uuids' => 'optional list<string>',
    );
  }

  protected function defineReturnType() {
    return 'list<dict>';
  }

  protected function execute(ConduitAPIRequest $request) {
    $query = $this->newQueryForRequest($request);

    $ids = $request->getValue('ids', array());
    if ($ids) {
      $query->withIDs($ids);
    }

    $phids = $request->getValue('phids', array());
    if ($phids) {
      $query->withPHIDs($phids);
    }

    $callsigns = $request->getValue('callsigns', array());
    if ($callsigns) {
      $query->withCallsigns($callsigns);
    }

    $vcs_types = $request->getValue('vcsTypes', array());
    if ($vcs_types) {
      $query->withTypes($vcs_types);
    }

    $remote_uris = $request->getValue('remoteURIs', array());
    if ($remote_uris) {
      $query->withURIs($remote_uris);
    }

    $uuids = $request->getValue('uuids', array());
    if ($uuids) {
      $query->withUUIDs($uuids);
    }

    $pager = $this->newPager($request);
    $repositories = $query->executeWithCursorPager($pager);

    $results = array();
    foreach ($repositories as $repository) {
      $results[] = $repository->toDictionary();
    }

    return $results;
  }

}
