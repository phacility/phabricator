<?php

/**
 * @group conduit
 */
abstract class ConduitAPI_repository_Method extends ConduitAPIMethod {

  protected function buildDictForRepository(PhabricatorRepository $repository) {
    return array(
      'name'        => $repository->getName(),
      'phid'        => $repository->getPHID(),
      'callsign'    => $repository->getCallsign(),
      'vcs'         => $repository->getVersionControlSystem(),
      'uri'         => PhabricatorEnv::getProductionURI($repository->getURI()),
      'remoteURI'   => (string)$repository->getPublicRemoteURI(),
      'tracking'    => $repository->getDetail('tracking-enabled'),
      'description' => $repository->getDetail('description'),
    );
  }

}
