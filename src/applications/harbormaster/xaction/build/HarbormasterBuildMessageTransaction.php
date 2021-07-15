<?php

abstract class HarbormasterBuildMessageTransaction
  extends HarbormasterBuildTransactionType {

  final public function getHarbormasterBuildMessageType() {
    return $this->getPhobjectClassConstant('MESSAGETYPE');
  }

  abstract public function getHarbormasterBuildMessageName();
  abstract public function getHarbormasterBuildableMessageName();
  abstract public function getHarbormasterBuildableMessageEffect();

  abstract public function newConfirmPromptTitle();
  abstract public function newConfirmPromptBody();

  abstract public function newBuildableConfirmPromptTitle(
    array $builds,
    array $sendable);

  abstract public function newBuildableConfirmPromptBody(
    array $builds,
    array $sendable);

  public function newBuildableConfirmPromptWarnings(
    array $builds,
    array $sendable) {
    return array();
  }

  final public function generateOldValue($object) {
    return null;
  }

  final public function getTransactionTypeForConduit($xaction) {
    return 'message';
  }

  final public function getFieldValuesForConduit($xaction, $data) {
    return array(
      'type' => $xaction->getNewValue(),
    );
  }

  final public static function getTransactionObjectForMessageType(
    $message_type) {
    $message_xactions = id(new PhutilClassMapQuery())
      ->setAncestorClass(__CLASS__)
      ->execute();

    foreach ($message_xactions as $message_xaction) {
      $xaction_type = $message_xaction->getHarbormasterBuildMessageType();
      if ($xaction_type === $message_type) {
        return $message_xaction;
      }
    }

    return null;
  }

  final public static function getTransactionTypeForMessageType($message_type) {
    $message_xaction = self::getTransactionObjectForMessageType($message_type);

    if ($message_xaction) {
      return $message_xaction->getTransactionTypeConstant();
    }

    return null;
  }

  final public function getTransactionHasEffect($object, $old, $new) {
    return $this->canApplyMessage($this->getActor(), $object);
  }

  final public function canApplyMessage(
    PhabricatorUser $viewer,
    HarbormasterBuild $build) {

    try {
      $this->assertCanApplyMessage($viewer, $build);
      return true;
    } catch (HarbormasterRestartException $ex) {
      return false;
    }
  }

  final public function canSendMessage(
    PhabricatorUser $viewer,
    HarbormasterBuild $build) {

    try {
      $this->assertCanSendMessage($viewer, $build);
      return true;
    } catch (HarbormasterRestartException $ex) {
      return false;
    }
  }

  final public function assertCanApplyMessage(
    PhabricatorUser $viewer,
    HarbormasterBuild $build) {
    $this->newCanApplyMessageAssertion($viewer, $build);
  }

  final public function assertCanSendMessage(
    PhabricatorUser $viewer,
    HarbormasterBuild $build) {
    $plan = $build->getBuildPlan();

    // See T13526. Users without permission to access the build plan can
    // currently end up here with no "BuildPlan" object.
    if (!$plan) {
      throw new HarbormasterRestartException(
        pht('No Build Plan Permission'),
        pht(
          'You can not issue this command because you do not have '.
          'permission to access the build plan for this build.'));
    }

    // Issuing these commands requires that you be able to edit the build, to
    // prevent enemy engineers from sabotaging your builds. See T9614.
    if (!$plan->canRunWithoutEditCapability()) {
      try {
        PhabricatorPolicyFilter::requireCapability(
          $viewer,
          $plan,
          PhabricatorPolicyCapability::CAN_EDIT);
      } catch (PhabricatorPolicyException $ex) {
        throw new HarbormasterRestartException(
          pht('Insufficent Build Plan Permission'),
          pht(
            'The build plan for this build is configured to prevent '.
            'users who can not edit it from issuing commands to the '.
            'build, and you do not have permission to edit the build '.
            'plan.'));
      }
    }

    $this->newCanSendMessageAssertion($viewer, $build);
    $this->assertCanApplyMessage($viewer, $build);
  }

  abstract protected function newCanSendMessageAssertion(
    PhabricatorUser $viewer,
    HarbormasterBuild $build);

  abstract protected function newCanApplyMessageAssertion(
    PhabricatorUser $viewer,
    HarbormasterBuild $build);

}
