<?php

final class PhabricatorMailImplementationAmazonSESAdapter
  extends PhabricatorMailImplementationPHPMailerLiteAdapter {

  private $message;
  private $isHTML;

  public function __construct() {
    parent::__construct();
    $this->mailer->Mailer = 'amazon-ses';
    $this->mailer->customMailer = $this;
  }

  public function supportsMessageIDHeader() {
    // Amazon SES will ignore any Message-ID we provide.
    return false;
  }

  /**
   * @phutil-external-symbol class SimpleEmailService
   */
  public function executeSend($body) {
    $key = PhabricatorEnv::getEnvConfig('amazon-ses.access-key');
    $secret = PhabricatorEnv::getEnvConfig('amazon-ses.secret-key');

    $root = phutil_get_library_root('phabricator');
    $root = dirname($root);
    require_once $root.'/externals/amazon-ses/ses.php';

    $service = new SimpleEmailService($key, $secret);
    $service->enableUseExceptions(true);
    return $service->sendRawEmail($body);
  }

}
