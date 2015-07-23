<?php

/**
 * Trivial action which logs a message.
 *
 * This action is primarily useful for testing triggers.
 */
final class PhabricatorLogTriggerAction
   extends PhabricatorTriggerAction {

  public function validateProperties(array $properties) {
    PhutilTypeSpec::checkMap(
      $properties,
      array(
        'message' => 'string',
      ));
  }

  public function execute($last_epoch, $this_epoch) {
    $message = pht(
      '(%s -> %s @ %s) %s',
      $last_epoch ? date('Y-m-d g:i:s A', $last_epoch) : 'null',
      date('Y-m-d g:i:s A', $this_epoch),
      date('Y-m-d g:i:s A', PhabricatorTime::getNow()),
      $this->getProperty('message'));

    phlog($message);
  }

}
