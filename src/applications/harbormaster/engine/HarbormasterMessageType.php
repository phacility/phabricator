<?php

final class HarbormasterMessageType extends Phobject {

  const MESSAGE_PASS = 'pass';
  const MESSAGE_FAIL = 'fail';
  const MESSAGE_WORK = 'work';

  public static function getAllMessages() {
    return array_keys(self::getMessageSpecifications());
  }

  public static function getMessageDescription($message) {
    $spec = self::getMessageSpecification($message);
    if (!$spec) {
      return null;
    }
    return idx($spec, 'description');
  }

  private static function getMessageSpecification($message) {
    $specs = self::getMessageSpecifications();
    return idx($specs, $message);
  }

  private static function getMessageSpecifications() {
    return array(
      self::MESSAGE_PASS => array(
        'description' => pht(
          'Report that the target is complete, and the target has passed.'),
      ),
      self::MESSAGE_FAIL => array(
        'description' => pht(
          'Report that the target is complete, and the target has failed.'),
      ),
      self::MESSAGE_WORK => array(
        'description' => pht(
          'Report that work on the target is ongoing. This message can be '.
          'used to report partial results during a build.'),
      ),
    );
  }

}
