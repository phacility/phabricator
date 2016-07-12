<?php

final class PhrequentPopConduitAPIMethod extends PhrequentConduitAPIMethod {

  public function getAPIMethodName() {
    return 'phrequent.pop';
  }

  public function getMethodDescription() {
    return pht('Stop tracking time on an object by popping it from the stack.');
  }

  public function getMethodStatus() {
    return self::METHOD_STATUS_UNSTABLE;
  }

  protected function defineParamTypes() {
    return array(
      'objectPHID' => 'phid',
      'stopTime' => 'int',
      'note' => 'string',
    );
  }

  protected function defineReturnType() {
    return 'phid';
  }

  protected function execute(ConduitAPIRequest $request) {
    $user = $request->getUser();
    $object_phid = $request->getValue('objectPHID');
    $timestamp = $request->getValue('stopTime');
    $note = $request->getValue('note');
    if ($timestamp === null) {
      $timestamp = time();
    }

    $editor = new PhrequentTrackingEditor();

    if (!$object_phid) {
      return $editor->stopTrackingTop($user, $timestamp, $note);
    } else {
      return $editor->stopTracking($user, $object_phid, $timestamp, $note);
    }
  }

}
