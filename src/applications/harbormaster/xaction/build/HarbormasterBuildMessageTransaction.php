<?php

abstract class HarbormasterBuildMessageTransaction
  extends HarbormasterBuildTransactionType {

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

  final public static function getTransactionTypeForMessageType($message_type) {
    $message_xactions = id(new PhutilClassMapQuery())
      ->setAncestorClass(__CLASS__)
      ->execute();

    foreach ($message_xactions as $message_xaction) {
      if ($message_xaction->getMessageType() === $message_type) {
        return $message_xaction->getTransactionTypeConstant();
      }
    }

    return null;
  }

  abstract public function getMessageType();

  public function validateTransactions($object, array $xactions) {
    $errors = array();

    // TODO: Restore logic that tests if the command can issue without causing
    // anything to lapse into an invalid state. This should not be the same
    // as the logic which powers the web UI: for example, if an "abort" is
    // queued we want to disable "Abort" in the web UI, but should obviously
    // process it here.

    return $errors;
  }

}
