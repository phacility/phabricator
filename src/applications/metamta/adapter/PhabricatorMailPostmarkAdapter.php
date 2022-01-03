<?php

final class PhabricatorMailPostmarkAdapter
  extends PhabricatorMailAdapter {

  const ADAPTERTYPE = 'postmark';

  public function getSupportedMessageTypes() {
    return array(
      PhabricatorMailEmailMessage::MESSAGETYPE,
    );
  }

  protected function validateOptions(array $options) {
    PhutilTypeSpec::checkMap(
      $options,
      array(
        'access-token' => 'string',
        'inbound-addresses' => 'list<string>',
      ));

    // Make sure this is properly formatted.
    PhutilCIDRList::newList($options['inbound-addresses']);
  }

  public function newDefaultOptions() {
    return array(
      'access-token' => null,
      'inbound-addresses' => array(
        // Via Postmark support circa February 2018, see:
        //
        // https://postmarkapp.com/support/article/800-ips-for-firewalls
        //
        // "Configuring Outbound Email" should be updated if this changes.
        //
        // These addresses were last updated in December 2021.
        '50.31.156.6/32',
        '50.31.156.77/32',
        '18.217.206.57/32',
        '3.134.147.250/32',
      ),
    );
  }

  public function sendMessage(PhabricatorMailExternalMessage $message) {
    $access_token = $this->getOption('access-token');

    $parameters = array();

    $subject = $message->getSubject();
    if ($subject !== null) {
      $parameters['Subject'] = $subject;
    }

    $from_address = $message->getFromAddress();
    if ($from_address) {
      $parameters['From'] = (string)$from_address;
    }

    $to_addresses = $message->getToAddresses();
    if ($to_addresses) {
      $to = array();
      foreach ($to_addresses as $address) {
        $to[] = (string)$address;
      }
      $parameters['To'] = implode(', ', $to);
    }

    $cc_addresses = $message->getCCAddresses();
    if ($cc_addresses) {
      $cc = array();
      foreach ($cc_addresses as $address) {
        $cc[] = (string)$address;
      }
      $parameters['Cc'] = implode(', ', $cc);
    }

    $reply_address = $message->getReplyToAddress();
    if ($reply_address) {
      $parameters['ReplyTo'] = (string)$reply_address;
    }

    $headers = $message->getHeaders();
    if ($headers) {
      $list = array();
      foreach ($headers as $header) {
        $list[] = array(
          'Name' => $header->getName(),
          'Value' => $header->getValue(),
        );
      }
      $parameters['Headers'] = $list;
    }

    $text_body = $message->getTextBody();
    if ($text_body !== null) {
      $parameters['TextBody'] = $text_body;
    }

    $html_body = $message->getHTMLBody();
    if ($html_body !== null) {
      $parameters['HtmlBody'] = $html_body;
    }

    $attachments = $message->getAttachments();
    if ($attachments) {
      $files = array();
      foreach ($attachments as $attachment) {
        $files[] = array(
          'Name' => $attachment->getFilename(),
          'ContentType' => $attachment->getMimeType(),
          'Content' => base64_encode($attachment->getData()),
        );
      }
      $parameters['Attachments'] = $files;
    }

    id(new PhutilPostmarkFuture())
      ->setAccessToken($access_token)
      ->setMethod('email', $parameters)
      ->setTimeout(60)
      ->resolve();
  }

}
