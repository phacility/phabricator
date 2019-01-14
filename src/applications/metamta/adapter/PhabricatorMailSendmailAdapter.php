<?php

final class PhabricatorMailSendmailAdapter
  extends PhabricatorMailAdapter {

  const ADAPTERTYPE = 'sendmail';


  public function getSupportedMessageTypes() {
    return array(
      PhabricatorMailEmailMessage::MESSAGETYPE,
    );
  }

  public function supportsMessageIDHeader() {
    return true;
  }

  protected function validateOptions(array $options) {
    PhutilTypeSpec::checkMap(
      $options,
      array(
        'encoding' => 'string',
      ));
  }

  public function newDefaultOptions() {
    return array(
      'encoding' => 'base64',
    );
  }

  /**
   * @phutil-external-symbol class PHPMailerLite
   */
  public function sendMessage(PhabricatorMailExternalMessage $message) {
    $root = phutil_get_library_root('phabricator');
    $root = dirname($root);
    require_once $root.'/externals/phpmailer/class.phpmailer-lite.php';

    $mailer = PHPMailerLite::newFromMessage($message);
    $mailer->Send();
  }

}
