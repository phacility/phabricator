<?php

/**
 * @group conduit
 */
final class ConduitAPI_daemon_launched_Method extends ConduitAPIMethod {

  public function shouldRequireAuthentication() {
    // TODO: Lock this down once we build phantoms.
    return false;
  }

  public function shouldAllowUnguardedWrites() {
    return true;
  }

  public function getMethodDescription() {
    return "Used by daemons to log run status.";
  }

  public function defineParamTypes() {
    return array(
      'daemon'    => 'required string',
      'host'      => 'required string',
      'pid'       => 'required int',
      'argv'      => 'required string',
    );
  }

  public function defineReturnType() {
    return 'string';
  }

  public function defineErrorTypes() {
    return array(
    );
  }

  protected function execute(ConduitAPIRequest $request) {

    $daemon_log = new PhabricatorDaemonLog();
    $daemon_log->setDaemon($request->getValue('daemon'));
    $daemon_log->setHost($request->getValue('host'));
    $daemon_log->setPID($request->getValue('pid'));
    $daemon_log->setStatus(PhabricatorDaemonLog::STATUS_RUNNING);
    $daemon_log->setArgv(json_decode($request->getValue('argv')));

    $daemon_log->save();

    return $daemon_log->getID();
  }

}
