<?php

final class PhabricatorMailAmazonSESAdapter
  extends PhabricatorMailAdapter {

  const ADAPTERTYPE = 'ses';

  public function getSupportedMessageTypes() {
    return array(
      PhabricatorMailEmailMessage::MESSAGETYPE,
    );
  }

  protected function validateOptions(array $options) {
    PhutilTypeSpec::checkMap(
      $options,
      array(
        'access-key' => 'string',
        'secret-key' => 'string',
        'region' => 'string',
        'endpoint' => 'string',
      ));
  }

  public function newDefaultOptions() {
    return array(
      'access-key' => null,
      'secret-key' => null,
      'region' => null,
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

  public function executeSend($body) {
    $key = $this->getOption('access-key');

    $secret = $this->getOption('secret-key');
    $secret = new PhutilOpaqueEnvelope($secret);

    $region = $this->getOption('region');
    $endpoint = $this->getOption('endpoint');

    $data = array(
      'Action' => 'SendRawEmail',
      'RawMessage.Data' => base64_encode($body),
    );

    $data = phutil_build_http_querystring($data);

    $future = id(new PhabricatorAWSSESFuture())
      ->setAccessKey($key)
      ->setSecretKey($secret)
      ->setRegion($region)
      ->setEndpoint($endpoint)
      ->setHTTPMethod('POST')
      ->setData($data);

    $future->resolve();

    return true;
  }

}
