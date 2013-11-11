<?php

final class NuanceSourceType extends NuanceConstants {

  /* internal source types */
  const PHABRICATOR_FORM = 1;

  /* social media source types */
  const TWITTER          = 101;

  /* engineering media source types */
  const GITHUB           = 201;


  public static function getSelectOptions() {

    return array(
      self::PHABRICATOR_FORM => pht('Phabricator Form'),
    );
  }

}
