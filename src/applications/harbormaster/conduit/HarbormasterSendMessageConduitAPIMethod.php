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
      'lint' => 'optional list<wild>',
      'unit' => 'optional list<wild>',
      'type' => 'required '.$type_const,
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

    $save = array();

    $lint_messages = $request->getValue('lint', array());
    foreach ($lint_messages as $lint) {
      $save[] = HarbormasterBuildLintMessage::newFromDictionary(
        $build_target,
        $lint);
    }

    $unit_messages = $request->getValue('unit', array());
    foreach ($unit_messages as $unit) {
      $save[] = HarbormasterBuildUnitMessage::newFromDictionary(
        $build_target,
        $unit);
    }

    $save[] = HarbormasterBuildMessage::initializeNewMessage($viewer)
      ->setBuildTargetPHID($build_target->getPHID())
      ->setType($message_type);

    $build_target->openTransaction();
    foreach ($save as $object) {
      $object->save();
    }
    $build_target->saveTransaction();

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
