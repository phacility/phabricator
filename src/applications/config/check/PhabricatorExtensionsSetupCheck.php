<?php

final class PhabricatorExtensionsSetupCheck extends PhabricatorSetupCheck {

  public function getDefaultGroup() {
    return self::GROUP_PHP;
  }

  public function isPreflightCheck() {
    return true;
  }

  protected function executeChecks() {
    // TODO: Make 'mbstring' and 'iconv' soft requirements.

    $required = array(
      'hash',
      'json',
      'openssl',
      'mbstring',
      'iconv',
      'ctype',

      // There is a tiny chance we might not need this, but a significant
      // number of applications require it and it's widely available.
      'curl',
    );

    $need = array();
    foreach ($required as $extension) {
      if (!extension_loaded($extension)) {
        $need[] = $extension;
      }
    }

    if (!extension_loaded('mysqli') && !extension_loaded('mysql')) {
      $need[] = 'mysqli or mysql';
    }

    if (!$need) {
      return;
    }

    $message = pht('Required PHP extensions are not installed.');

    $issue = $this->newIssue('php.extensions')
      ->setIsFatal(true)
      ->setName(pht('Missing Required Extensions'))
      ->setMessage($message);

    foreach ($need as $extension) {
      $issue->addPHPExtension($extension);
    }
  }
}
