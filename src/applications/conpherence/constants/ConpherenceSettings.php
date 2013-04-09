<?php

final class ConpherenceSettings extends ConpherenceConstants {

  const EMAIL_ALWAYS = 0;
  const NOTIFICATIONS_ONLY = 1;

  public static function getHumanString($constant) {
    $string = pht('Unknown setting.');

    switch ($constant) {
      case self::EMAIL_ALWAYS:
        $string = pht('Email me every update.');
        break;
      case self::NOTIFICATIONS_ONLY:
        $string = pht('Notifications only.');
        break;
    }

    return $string;
  }
}
