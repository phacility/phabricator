<?php

final class PhrequentPushConduitAPIMethod extends PhrequentConduitAPIMethod {

  public function getAPIMethodName() {
    return 'phrequent.push';
  }

  public function getMethodDescription() {
    return pht(
      'Start tracking time on an object by '.
      'pushing it on the tracking stack.');
  }

  public function getMethodStatus() {
    return self::METHOD_STATUS_UNSTABLE;
  }

  public function defineParamTypes() {
    return array(
      'objectPHID' => 'required phid',
      'startTime' => 'int',
    );
  }

  public function defineReturnType() {
    return 'phid';
  }

  public function defineErrorTypes() {
    return array(
    );
  }

  protected function execute(ConduitAPIRequest $request) {
    $user = $request->getUser();
    $object_phid = $request->getValue('objectPHID');
    $timestamp = $request->getValue('startTime');
    if ($timestamp === null) {
      $timestamp = time();
    }

    $editor = new PhrequentTrackingEditor();
    return $editor->startTracking($user, $object_phid, $timestamp);
  }

}
