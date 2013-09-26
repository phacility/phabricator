<?php

abstract class PhabricatorRepositoryManagementWorkflow
  extends PhutilArgumentWorkflow {

  public function isExecutable() {
    return true;
  }

  protected function loadRepositories(PhutilArgumentParser $args, $param) {
    $callsigns = $args->getArg($param);

    if (!$callsigns) {
      return null;
    }

    $repos = id(new PhabricatorRepositoryQuery())
      ->setViewer(PhabricatorUser::getOmnipotentUser())
      ->withCallsigns($callsigns)
      ->execute();

    $repos = mpull($repos, null, 'getCallsign');
    foreach ($callsigns as $callsign) {
      if (empty($repos[$callsign])) {
        throw new PhutilArgumentUsageException(
          "No repository with callsign '{$callsign}' exists!");
      }
    }

    return $repos;
  }


}
