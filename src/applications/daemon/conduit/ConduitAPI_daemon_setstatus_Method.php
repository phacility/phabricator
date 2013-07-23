<?php

/**
 * @group conduit
 */
final class ConduitAPI_daemon_setstatus_Method extends ConduitAPIMethod {

  public function getMethodStatus() {
    return self::METHOD_STATUS_DEPRECATED;
  }

  public function shouldRequireAuthentication() {
    return false;
  }

  public function shouldAllowUnguardedWrites() {
    return true;
  }

  public function getMethodDescription() {
    return "Used by daemons to update their status.";
  }

  public function defineParamTypes() {
    return array(
      'daemonLogID' => 'required string',
      'status'      => 'required enum<unknown, run, timeout, dead, exit>',
    );
  }

  public function defineReturnType() {
    return 'void';
  }

  public function defineErrorTypes() {
    return array(
      'ERR-INVALID-ID' => 'An invalid daemonLogID was provided.',
    );
  }

  protected function execute(ConduitAPIRequest $request) {

    $daemon_log = id(new PhabricatorDaemonLog())
      ->load($request->getValue('daemonLogID'));
    if (!$daemon_log) {
      throw new ConduitException('ERR-INVALID-ID');
    }
    $daemon_log->setStatus($request->getValue('status'));

    $daemon_log->save();
  }

}
