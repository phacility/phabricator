<?php

final class PhabricatorExtensionsSetupCheck extends PhabricatorSetupCheck {

  public function getDefaultGroup() {
    return self::GROUP_PHP;
  }

  public function getExecutionOrder() {
    return 0;
  }

  protected function executeChecks() {
    // TODO: Make 'mbstring' and 'iconv' soft requirements.
    // TODO: Make 'curl' a soft requirement.

    $required = array(
      'hash',
      'json',
      'openssl',
      'mbstring',
      'iconv',
      'ctype',

      // There is a chance we might not need this, but some configurations (like
      // OAuth or Amazon SES) will require it. Just mark it 'required' since
      // it's widely available and relatively core.
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
