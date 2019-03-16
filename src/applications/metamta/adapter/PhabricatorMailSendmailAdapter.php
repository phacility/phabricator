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
    return $this->guessIfHostSupportsMessageID(
      $this->getOption('message-id'),
      null);
  }

  protected function validateOptions(array $options) {
    PhutilTypeSpec::checkMap(
      $options,
      array(
        'message-id' => 'bool|null',
      ));
  }

  public function newDefaultOptions() {
    return array(
      'message-id' => null,
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
