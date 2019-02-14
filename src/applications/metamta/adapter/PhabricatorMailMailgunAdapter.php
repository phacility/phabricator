<?php

/**
 * Mail adapter that uses Mailgun's web API to deliver email.
 */
final class PhabricatorMailMailgunAdapter
  extends PhabricatorMailAdapter {

  const ADAPTERTYPE = 'mailgun';

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
        'api-key' => 'string',
        'domain' => 'string',
        'api-hostname' => 'string',
      ));
  }

  public function newDefaultOptions() {
    return array(
      'api-key' => null,
      'domain' => null,
      'api-hostname' => 'api.mailgun.net',
    );
  }

  public function sendMessage(PhabricatorMailExternalMessage $message) {
    $api_key = $this->getOption('api-key');
    $domain = $this->getOption('domain');
    $api_hostname = $this->getOption('api-hostname');
    $params = array();

    $subject = $message->getSubject();
    if ($subject !== null) {
      $params['subject'] = $subject;
    }

    $from_address = $message->getFromAddress();
    if ($from_address) {
      $params['from'] = (string)$from_address;
    }

    $to_addresses = $message->getToAddresses();
    if ($to_addresses) {
      $to = array();
      foreach ($to_addresses as $address) {
        $to[] = (string)$address;
      }
      $params['to'] = implode(', ', $to);
    }

    $cc_addresses = $message->getCCAddresses();
    if ($cc_addresses) {
      $cc = array();
      foreach ($cc_addresses as $address) {
        $cc[] = (string)$address;
      }
      $params['cc'] = implode(', ', $cc);
    }

    $reply_address = $message->getReplyToAddress();
    if ($reply_address) {
      $params['h:reply-to'] = (string)$reply_address;
    }

    $headers = $message->getHeaders();
    if ($headers) {
      foreach ($headers as $header) {
        $name = $header->getName();
        $value = $header->getValue();
        $params['h:'.$name] = $value;
      }
    }

    $text_body = $message->getTextBody();
    if ($text_body !== null) {
      $params['text'] = $text_body;
    }

    $html_body = $message->getHTMLBody();
    if ($html_body !== null) {
      $params['html'] = $html_body;
    }

    $mailgun_uri = urisprintf(
      'https://%s/v2/%s/messages',
      $api_hostname,
      $domain);

    $future = id(new HTTPSFuture($mailgun_uri, $params))
      ->setMethod('POST')
      ->setHTTPBasicAuthCredentials('api', new PhutilOpaqueEnvelope($api_key))
      ->setTimeout(60);

    $attachments = $message->getAttachments();
    foreach ($attachments as $attachment) {
      $future->attachFileData(
        'attachment',
        $attachment->getData(),
        $attachment->getFilename(),
        $attachment->getMimeType());
    }

    list($body) = $future->resolvex();

    $response = null;
    try {
      $response = phutil_json_decode($body);
    } catch (PhutilJSONParserException $ex) {
      throw new PhutilProxyException(
        pht('Failed to JSON decode response.'),
        $ex);
    }

    if (!idx($response, 'id')) {
      $message = $response['message'];
      throw new Exception(
        pht(
          'Request failed with errors: %s.',
          $message));
    }
  }

}
