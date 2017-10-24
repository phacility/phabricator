<?php

abstract class HeraldStateReasons extends Phobject {

  abstract public function explainReason($reason);

  final public static function getAllReasons() {
    return id(new PhutilClassMapQuery())
      ->setAncestorClass(__CLASS__)
      ->execute();
  }

  final public static function getExplanation($reason) {
    $reasons = self::getAllReasons();

    foreach ($reasons as $reason_implementation) {
      $explanation = $reason_implementation->explainReason($reason);
      if ($explanation !== null) {
        return $explanation;
      }
    }

    return pht('Unknown reason ("%s").', $reason);
  }

}
