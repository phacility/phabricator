<?php

/**
 * Mail adapter that uses SendGrid's web API to deliver email.
 */
final class PhabricatorMailSendGridAdapter
  extends PhabricatorMailAdapter {

  const ADAPTERTYPE = 'sendgrid';

  public function getSupportedMessageTypes() {
    return array(
      PhabricatorMailEmailMessage::MESSAGETYPE,
    );
  }

  protected function validateOptions(array $options) {
    PhutilTypeSpec::checkMap(
      $options,
      array(
        'api-key' => 'string',
      ));
  }

  public function newDefaultOptions() {
    return array(
      'api-key' => null,
    );
  }

  public function sendMessage(PhabricatorMailExternalMessage $message) {
    $key = $this->getOption('api-key');

    $parameters = array();

    $subject = $message->getSubject();
    if ($subject !== null) {
      $parameters['subject'] = $subject;
    }

    $personalizations = array();

    $to_addresses = $message->getToAddresses();
    if ($to_addresses) {
      $personalizations['to'] = array();
      foreach ($to_addresses as $address) {
        $personalizations['to'][] = $this->newPersonalization($address);
      }
    }

    $cc_addresses = $message->getCCAddresses();
    if ($cc_addresses) {
      $personalizations['cc'] = array();
      foreach ($cc_addresses as $address) {
        $personalizations['cc'][] = $this->newPersonalization($address);
      }
    }

    // This is a list of different sets of recipients who should receive copies
    // of the mail. We handle "one message to each recipient" ourselves.
    $parameters['personalizations'] = array(
      $personalizations,
    );

    $from_address = $message->getFromAddress();
    if ($from_address) {
      $parameters['from'] = $this->newPersonalization($from_address);
    }

    $reply_address = $message->getReplyToAddress();
    if ($reply_address) {
      $parameters['reply_to'] = $this->newPersonalization($reply_address);
    }

    $headers = $message->getHeaders();
    if ($headers) {
      $map = array();
      foreach ($headers as $header) {
        $map[$header->getName()] = $header->getValue();
      }
      $parameters['headers'] = $map;
    }

    $content = array();
    $text_body = $message->getTextBody();
    if ($text_body !== null) {
      $content[] = array(
        'type' => 'text/plain',
        'value' => $text_body,
      );
    }

    $html_body = $message->getHTMLBody();
    if ($html_body !== null) {
      $content[] = array(
        'type' => 'text/html',
        'value' => $html_body,
      );
    }
    $parameters['content'] = $content;

    $attachments = $message->getAttachments();
    if ($attachments) {
      $files = array();
      foreach ($attachments as $attachment) {
        $files[] = array(
          'content' => base64_encode($attachment->getData()),
          'type' => $attachment->getMimeType(),
          'filename' => $attachment->getFilename(),
          'disposition' => 'attachment',
        );
      }
      $parameters['attachments'] = $files;
    }

    $sendgrid_uri = 'https://api.sendgrid.com/v3/mail/send';
    $json_parameters = phutil_json_encode($parameters);

    id(new HTTPSFuture($sendgrid_uri))
      ->setMethod('POST')
      ->addHeader('Authorization', "Bearer {$key}")
      ->addHeader('Content-Type', 'application/json')
      ->setData($json_parameters)
      ->setTimeout(60)
      ->resolvex();

    // The SendGrid v3 API does not return a JSON response body. We get a
    // non-2XX HTTP response in the case of an error, which throws above.
  }

  private function newPersonalization(PhutilEmailAddress $address) {
    $result = array(
      'email' => $address->getAddress(),
    );

    $display_name = $address->getDisplayName();
    if ($display_name) {
      $result['name'] = $display_name;
    }

    return $result;
  }

}
