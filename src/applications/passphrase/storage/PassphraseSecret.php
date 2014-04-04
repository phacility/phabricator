<?php

final class PassphraseSecret extends PassphraseDAO {

  protected $secretData;

  public function getConfiguration() {
    return array(
      self::CONFIG_TIMESTAMPS => false,
      self::CONFIG_BINARY => array(
        'secretData' => true,
      ),
    ) + parent::getConfiguration();
  }

}
