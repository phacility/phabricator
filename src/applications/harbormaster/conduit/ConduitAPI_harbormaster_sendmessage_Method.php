<?php

final class ConduitAPI_harbormaster_sendmessage_Method
  extends ConduitAPI_harbormaster_Method {

  public function getMethodDescription() {
    return pht(
      'Send a message to a build target, notifying it of results in an '.
      'external system.');
  }

  public function defineParamTypes() {
    return array(
      'buildTargetPHID' => 'phid',
      'type'            => 'enum<pass, fail>',
    );
  }

  public function defineReturnType() {
    return 'void';
  }

  public function defineErrorTypes() {
    return array();
  }

  protected function execute(ConduitAPIRequest $request) {
    $viewer = $request->getUser();

    $build_target_phid = $request->getValue('buildTargetPHID');
    $message_type = $request->getValue('type');

    $build_target = id(new HarbormasterBuildTargetQuery())
      ->setViewer($viewer)
      ->withPHIDs(array($build_target_phid))
      ->executeOne();
    if (!$build_target) {
      throw new Exception(pht('No such build target!'));
    }

    $message = HarbormasterBuildMessage::initializeNewMessage($viewer)
      ->setBuildTargetPHID($build_target->getPHID())
      ->setType($message_type)
      ->save();

    return null;
  }

}
