<?php

final class PhabricatorMailImplementationPHPMailerAdapter
  extends PhabricatorMailImplementationAdapter {

  private $mailer;

  /**
   * @phutil-external-symbol class PHPMailer
   */
  public function __construct() {
    $root = phutil_get_library_root('phabricator');
    $root = dirname($root);
    require_once $root.'/externals/phpmailer/class.phpmailer.php';
    $this->mailer = new PHPMailer($use_exceptions = true);
    $this->mailer->CharSet = 'utf-8';

    $encoding = PhabricatorEnv::getEnvConfig('phpmailer.smtp-encoding', '8bit');
    $this->mailer->Encoding = $encoding;

    // By default, PHPMailer sends one mail per recipient. We handle
    // multiplexing higher in the stack, so tell it to send mail exactly
    // like we ask.
    $this->mailer->SingleTo = false;

    $mailer = PhabricatorEnv::getEnvConfig('phpmailer.mailer');
    if ($mailer == 'smtp') {
      $this->mailer->IsSMTP();
      $this->mailer->Host = PhabricatorEnv::getEnvConfig('phpmailer.smtp-host');
      $this->mailer->Port = PhabricatorEnv::getEnvConfig('phpmailer.smtp-port');
      $user = PhabricatorEnv::getEnvConfig('phpmailer.smtp-user');
      if ($user) {
        $this->mailer->SMTPAuth = true;
        $this->mailer->Username = $user;
        $this->mailer->Password =
          PhabricatorEnv::getEnvConfig('phpmailer.smtp-password');
      }

      $protocol = PhabricatorEnv::getEnvConfig('phpmailer.smtp-protocol');
      if ($protocol) {
        $protocol = phutil_utf8_strtolower($protocol);
        $this->mailer->SMTPSecure = $protocol;
      }
    } else if ($mailer == 'sendmail') {
      $this->mailer->IsSendmail();
    } else {
      // Do nothing, by default PHPMailer send message using PHP mail()
      // function.
    }
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
    $this->mailer->IsHTML(false);
    $this->mailer->Body = $body;
    return $this;
  }

  public function setHTMLBody($html_body) {
    $this->mailer->IsHTML(true);
    $this->mailer->Body = $html_body;
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
