<?php

final class PhabricatorMailImplementationAmazonSESAdapter
  extends PhabricatorMailImplementationPHPMailerLiteAdapter {

  const ADAPTERTYPE = 'ses';

  private $message;
  private $isHTML;

  public function prepareForSend() {
    parent::prepareForSend();
    $this->mailer->Mailer = 'amazon-ses';
    $this->mailer->customMailer = $this;
  }

  public function supportsMessageIDHeader() {
    // Amazon SES will ignore any Message-ID we provide.
    return false;
  }

  protected function validateOptions(array $options) {
    PhutilTypeSpec::checkMap(
      $options,
      array(
        'access-key' => 'string',
        'secret-key' => 'string',
        'endpoint' => 'string',
        'encoding' => 'string',
      ));
  }

  public function newDefaultOptions() {
    return array(
      'access-key' => null,
      'secret-key' => null,
      'endpoint' => null,
      'encoding' => 'base64',
    );
  }

  public function newLegacyOptions() {
    return array(
      'access-key' => PhabricatorEnv::getEnvConfig('amazon-ses.access-key'),
      'secret-key' => PhabricatorEnv::getEnvConfig('amazon-ses.secret-key'),
      'endpoint' => PhabricatorEnv::getEnvConfig('amazon-ses.endpoint'),
      'encoding' => PhabricatorEnv::getEnvConfig('phpmailer.smtp-encoding'),
    );
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
