<?php

/**
 * TODO: Should be final, but inherited by SES.
 */
class PhabricatorMailImplementationPHPMailerLiteAdapter
  extends PhabricatorMailImplementationAdapter {

  protected $mailer;

  /**
   * @phutil-external-symbol class PHPMailerLite
   */
  public function __construct() {
    $root = phutil_get_library_root('phabricator');
    $root = dirname($root);
    require_once $root.'/externals/phpmailer/class.phpmailer-lite.php';
    $this->mailer = new PHPMailerLite($use_exceptions = true);
    $this->mailer->CharSet = 'utf-8';

    $encoding = PhabricatorEnv::getEnvConfig('phpmailer.smtp-encoding', '8bit');
    $this->mailer->Encoding = $encoding;

    // By default, PHPMailerLite sends one mail per recipient. We handle
    // multiplexing higher in the stack, so tell it to send mail exactly
    // like we ask.
    $this->mailer->SingleTo = false;
  }

  public function supportsMessageIDHeader() {
    return true;
  }

  public function setFrom($email, $name = '') {
    $this->mailer->SetFrom($email, $name, $crazy_side_effects = false);
    return $this;
  }

  public function addReplyTo($email, $name = '') {
    $this->mailer->AddReplyTo($email, $name);
    return $this;
  }

  public function addTos(array $emails) {
    foreach ($emails as $email) {
      $this->mailer->AddAddress($email);
    }
    return $this;
  }

  public function addCCs(array $emails) {
    foreach ($emails as $email) {
      $this->mailer->AddCC($email);
    }
    return $this;
  }

  public function addAttachment($data, $filename, $mimetype) {
    $this->mailer->AddStringAttachment(
      $data,
      $filename,
      'base64',
      $mimetype);
    return $this;
  }

  public function addHeader($header_name, $header_value) {
    if (strtolower($header_name) == 'message-id') {
      $this->mailer->MessageID = $header_value;
    } else {
      $this->mailer->AddCustomHeader($header_name.': '.$header_value);
    }
    return $this;
  }

  public function setBody($body) {
    $this->mailer->Body = $body;
    $this->mailer->IsHTML(false);
    return $this;
  }


  /**
   * Note: phpmailer-lite does NOT support sending messages with mixed version
   * (plaintext and html). So for now lets just use HTML if it's available.
   * @param $html
   */
  public function setHTMLBody($html_body) {
    $this->mailer->Body = $html_body;
    $this->mailer->IsHTML(true);
    return $this;
  }

  public function setSubject($subject) {
    $this->mailer->Subject = $subject;
    return $this;
  }

  public function hasValidRecipients() {
    return true;
  }

  public function send() {
    return $this->mailer->Send();
  }

}
