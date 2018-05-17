<?php

final class PhabricatorMailImplementationPostmarkAdapter
  extends PhabricatorMailImplementationAdapter {

  const ADAPTERTYPE = 'postmark';

  private $parameters = array();

  public function setFrom($email, $name = '') {
    $this->parameters['From'] = $this->renderAddress($email, $name);
    return $this;
  }

  public function addReplyTo($email, $name = '') {
    $this->parameters['ReplyTo'] = $this->renderAddress($email, $name);
    return $this;
  }

  public function addTos(array $emails) {
    foreach ($emails as $email) {
      $this->parameters['To'][] = $email;
    }
    return $this;
  }

  public function addCCs(array $emails) {
    foreach ($emails as $email) {
      $this->parameters['Cc'][] = $email;
    }
    return $this;
  }

  public function addAttachment($data, $filename, $mimetype) {
    $this->parameters['Attachments'][] = array(
      'Name' => $filename,
      'ContentType' => $mimetype,
      'Content' => base64_encode($data),
    );

    return $this;
  }

  public function addHeader($header_name, $header_value) {
    $this->parameters['Headers'][] = array(
      'Name' => $header_name,
      'Value' => $header_value,
    );
    return $this;
  }

  public function setBody($body) {
    $this->parameters['TextBody'] = $body;
    return $this;
  }

  public function setHTMLBody($html_body) {
    $this->parameters['HtmlBody'] = $html_body;
    return $this;
  }

  public function setSubject($subject) {
    $this->parameters['Subject'] = $subject;
    return $this;
  }

  public function supportsMessageIDHeader() {
    return true;
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
        '50.31.156.6/32',
      ),
    );
  }

  public function newLegacyOptions() {
    return array();
  }

  public function send() {
    $access_token = $this->getOption('access-token');

    $parameters = $this->parameters;
    $flatten = array(
      'To',
      'Cc',
    );

    foreach ($flatten as $key) {
      if (isset($parameters[$key])) {
        $parameters[$key] = implode(', ', $parameters[$key]);
      }
    }

    id(new PhutilPostmarkFuture())
      ->setAccessToken($access_token)
      ->setMethod('email', $parameters)
      ->resolve();

    return true;
  }

}
