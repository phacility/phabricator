<?php

final class PhabricatorMailSMTPAdapter
  extends PhabricatorMailAdapter {

  const ADAPTERTYPE = 'smtp';

  public function getSupportedMessageTypes() {
    return array(
      PhabricatorMailEmailMessage::MESSAGETYPE,
    );
  }

  public function supportsMessageIDHeader() {
    return $this->guessIfHostSupportsMessageID(
      $this->getOption('message-id'),
      $this->getOption('host'));
  }

  protected function validateOptions(array $options) {
    PhutilTypeSpec::checkMap(
      $options,
      array(
        'host' => 'string|null',
        'port' => 'int',
        'user' => 'string|null',
        'password' => 'string|null',
        'protocol' => 'string|null',
        'message-id' => 'bool|null',
      ));
  }

  public function newDefaultOptions() {
    return array(
      'host' => null,
      'port' => 25,
      'user' => null,
      'password' => null,
      'protocol' => null,
      'message-id' => null,
    );
  }

  /**
   * @phutil-external-symbol class PHPMailer
   */
  public function sendMessage(PhabricatorMailExternalMessage $message) {
    $root = phutil_get_library_root('phabricator');
    $root = dirname($root);
    require_once $root.'/externals/phpmailer/class.phpmailer.php';
    $smtp = new PHPMailer($use_exceptions = true);

    $smtp->CharSet = 'utf-8';
    $smtp->Encoding = 'base64';

    // By default, PHPMailer sends one mail per recipient. We handle
    // combining or separating To and Cc higher in the stack, so tell it to
    // send mail exactly like we ask.
    $smtp->SingleTo = false;

    $smtp->IsSMTP();
    $smtp->Host = $this->getOption('host');
    $smtp->Port = $this->getOption('port');
    $user = $this->getOption('user');
    if (strlen($user)) {
      $smtp->SMTPAuth = true;
      $smtp->Username = $user;
      $smtp->Password = $this->getOption('password');
    }

    $protocol = $this->getOption('protocol');
    if ($protocol) {
      $protocol = phutil_utf8_strtolower($protocol);
      $smtp->SMTPSecure = $protocol;
    }

    $subject = $message->getSubject();
    if ($subject !== null) {
      $smtp->Subject = $subject;
    }

    $from_address = $message->getFromAddress();
    if ($from_address) {
      $smtp->SetFrom(
        $from_address->getAddress(),
        (string)$from_address->getDisplayName(),
        $crazy_side_effects = false);
    }

    $reply_address = $message->getReplyToAddress();
    if ($reply_address) {
      $smtp->AddReplyTo(
        $reply_address->getAddress(),
        (string)$reply_address->getDisplayName());
    }

    $to_addresses = $message->getToAddresses();
    if ($to_addresses) {
      foreach ($to_addresses as $address) {
        $smtp->AddAddress(
          $address->getAddress(),
          (string)$address->getDisplayName());
      }
    }

    $cc_addresses = $message->getCCAddresses();
    if ($cc_addresses) {
      foreach ($cc_addresses as $address) {
        $smtp->AddCC(
          $address->getAddress(),
          (string)$address->getDisplayName());
      }
    }

    $headers = $message->getHeaders();
    if ($headers) {
      $list = array();
      foreach ($headers as $header) {
        $name = $header->getName();
        $value = $header->getValue();

        if (phutil_utf8_strtolower($name) === 'message-id') {
          $smtp->MessageID = $value;
        } else {
          $smtp->AddCustomHeader("{$name}: {$value}");
        }
      }
    }

    $text_body = $message->getTextBody();
    if ($text_body !== null) {
      $smtp->Body = $text_body;
    }

    $html_body = $message->getHTMLBody();
    if ($html_body !== null) {
      $smtp->IsHTML(true);
      $smtp->Body = $html_body;
      if ($text_body !== null) {
        $smtp->AltBody = $text_body;
      }
    }

    $attachments = $message->getAttachments();
    if ($attachments) {
      foreach ($attachments as $attachment) {
        $smtp->AddStringAttachment(
          $attachment->getData(),
          $attachment->getFilename(),
          'base64',
          $attachment->getMimeType());
      }
    }

    $smtp->Send();
  }

}
