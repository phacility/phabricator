<?php

final class PhabricatorMailAmazonSESAdapter
  extends PhabricatorMailAdapter {

  const ADAPTERTYPE = 'ses';

  public function getSupportedMessageTypes() {
    return array(
      PhabricatorMailEmailMessage::MESSAGETYPE,
    );
  }

  public function supportsMessageIDHeader() {
    return false;
  }

  protected function validateOptions(array $options) {
    PhutilTypeSpec::checkMap(
      $options,
      array(
        'access-key' => 'string',
        'secret-key' => 'string',
        'endpoint' => 'string',
      ));
  }

  public function newDefaultOptions() {
    return array(
      'access-key' => null,
      'secret-key' => null,
      'endpoint' => null,
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

    $mailer->Mailer = 'amazon-ses';
    $mailer->customMailer = $this;

    $mailer->Send();
  }



  /**
   * @phutil-external-symbol class SimpleEmailService
   */
  public function executeSend($body) {
    $key = $this->getOption('access-key');
    $secret = $this->getOption('secret-key');
    $endpoint = $this->getOption('endpoint');

    $root = phutil_get_library_root('phabricator');
    $root = dirname($root);
    require_once $root.'/externals/amazon-ses/ses.php';

    $service = new SimpleEmailService($key, $secret, $endpoint);
    $service->enableUseExceptions(true);
    return $service->sendRawEmail($body);
  }

}
