<?php

/**
 * @group conduit
 */
final class ConduitAPI_daemon_log_Method extends ConduitAPIMethod {

  public function shouldRequireAuthentication() {
    // TODO: Lock this down once we build phantoms.
    return false;
  }

  public function shouldAllowUnguardedWrites() {
    return true;
  }

  public function getMethodDescription() {
    return "Used by daemons to log events.";
  }

  public function defineParamTypes() {
    return array(
      'daemonLogID'   => 'required int',
      'type'          => 'required string',
      'message'       => 'optional string',
    );
  }

  public function defineReturnType() {
    return 'void';
  }

  public function defineErrorTypes() {
    return array(
    );
  }

  protected function execute(ConduitAPIRequest $request) {

    $daemon_event = new PhabricatorDaemonLogEvent();
    $daemon_event->setLogID($request->getValue('daemonLogID'));
    $daemon_event->setLogType($request->getValue('type'));
    $daemon_event->setMessage((string)$request->getValue('message'));
    $daemon_event->setEpoch(time());

    $daemon_event->save();

    return;
  }

}
