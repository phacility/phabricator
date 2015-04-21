<?php

final class HarbormasterSendMessageConduitAPIMethod
  extends HarbormasterConduitAPIMethod {

  public function getAPIMethodName() {
    return 'harbormaster.sendmessage';
  }

  public function getMethodDescription() {
    return pht(
      'Send a message to a build target, notifying it of results in an '.
      'external system.');
  }

  protected function defineParamTypes() {
    $type_const = $this->formatStringConstants(array('pass', 'fail'));

    return array(
      'buildTargetPHID' => 'required phid',
      'type'            => 'required '.$type_const,
    );
  }

  protected function defineReturnType() {
    return 'void';
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

    // If the build has completely paused because all steps are blocked on
    // waiting targets, this will resume it.
    PhabricatorWorker::scheduleTask(
      'HarbormasterBuildWorker',
      array(
        'buildID' => $build_target->getBuild()->getID(),
      ));

    return null;
  }

}
