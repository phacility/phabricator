<?php

/**
 * Never triggers an event.
 *
 * This clock can be used for testing, or to cancel events.
 */
final class PhabricatorNeverTriggerClock extends PhabricatorTriggerClock {

  public function validateProperties(array $properties) {
    PhutilTypeSpec::checkMap(
      $properties,
      array());
  }

  public function getNextEventEpoch($last_epoch, $is_reschedule) {
    return null;
  }

}
