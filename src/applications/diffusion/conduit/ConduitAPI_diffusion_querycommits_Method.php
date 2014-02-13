<?php

final class ConduitAPI_diffusion_querycommits_Method
  extends ConduitAPI_diffusion_Method {

  public function getMethodDescription() {
    return pht('Retrieve information about commits.');
  }

  public function defineReturnType() {
    return 'map<string, dict>';
  }

  public function defineParamTypes() {
    return array(
      'ids'               => 'optional list<int>',
      'phids'             => 'optional list<phid>',
      'names'             => 'optional list<string>',
      'repositoryPHID'    => 'optional phid',
    ) + $this->getPagerParamTypes();
  }

  public function defineErrorTypes() {
    return array();
  }

  protected function execute(ConduitAPIRequest $request) {
    $query = id(new DiffusionCommitQuery())
      ->setViewer($request->getUser());

    $repository_phid = $request->getValue('repositoryPHID');
    if ($repository_phid) {
      $repository = id(new PhabricatorRepositoryQuery())
        ->setViewer($request->getUser())
        ->withPHIDs(array($repository_phid))
        ->executeOne();
      if ($repository) {
        $query->withRepository($repository);
      }
    }

    $names = $request->getValue('names');
    if ($names) {
      $query->withIdentifiers($names);
    }

    $ids = $request->getValue('ids');
    if ($ids) {
      $query->withIDs($ids);
    }

    $phids = $request->getValue('phids');
    if ($phids) {
      $query->withPHIDs($phids);
    }

    $pager = $this->newPager($request);
    $commits = $query->executeWithCursorPager($pager);

    $map = $query->getIdentifierMap();
    $map = mpull($map, 'getPHID');

    $data = array();
    foreach ($commits as $commit) {
      $callsign = $commit->getRepository()->getCallsign();
      $identifier = $commit->getCommitIdentifier();
      $uri = '/r'.$callsign.$identifier;
      $uri = PhabricatorEnv::getProductionURI($uri);

      $data[$commit->getPHID()] = array(
        'id' => $commit->getID(),
        'phid' => $commit->getPHID(),
        'repositoryPHID' => $commit->getRepository()->getPHID(),
        'identifier' => $identifier,
        'epoch' => $commit->getEpoch(),
        'uri' => $uri,
        'isImporting' => !$commit->isImported(),
      );
    }

    $result = array(
      'data' => $data,
      'identifierMap' => nonempty($map, (object)array()),
    );

    return $this->addPagerResults($result, $pager);
  }

}
