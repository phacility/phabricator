<?php

/**
 * Mail adapter that uses Mailgun's web API to deliver email.
 */
final class PhabricatorMailImplementationMailgunAdapter
  extends PhabricatorMailImplementationAdapter {

  const ADAPTERTYPE = 'mailgun';

  private $params = array();
  private $attachments = array();

  public function setFrom($email, $name = '') {
    $this->params['from'] = $email;
    $this->params['from-name'] = $name;
    return $this;
  }

  public function addReplyTo($email, $name = '') {
    if (empty($this->params['reply-to'])) {
      $this->params['reply-to'] = array();
    }
    $this->params['reply-to'][] = $this->renderAddress($email, $name);
    return $this;
  }

  public function addTos(array $emails) {
    foreach ($emails as $email) {
      $this->params['tos'][] = $email;
    }
    return $this;
  }

  public function addCCs(array $emails) {
    foreach ($emails as $email) {
      $this->params['ccs'][] = $email;
    }
    return $this;
  }

  public function addAttachment($data, $filename, $mimetype) {
    $this->attachments[] = array(
      'data' => $data,
      'name' => $filename,
      'type' => $mimetype,
    );

    return $this;
  }

  public function addHeader($header_name, $header_value) {
    $this->params['headers'][] = array($header_name, $header_value);
    return $this;
  }

  public function setBody($body) {
    $this->params['body'] = $body;
    return $this;
  }

  public function setHTMLBody($html_body) {
    $this->params['html-body'] = $html_body;
    return $this;
  }

  public function setSubject($subject) {
    $this->params['subject'] = $subject;
    return $this;
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
      ));
  }

  public function newDefaultOptions() {
    return array(
      'api-key' => null,
      'domain' => null,
    );
  }

  public function newLegacyOptions() {
    return array(
      'api-key' => PhabricatorEnv::getEnvConfig('mailgun.api-key'),
      'domain' => PhabricatorEnv::getEnvConfig('mailgun.domain'),
    );
  }

  public function send() {
    $key = $this->getOption('api-key');
    $domain = $this->getOption('domain');
    $params = array();

    $params['to'] = implode(', ', idx($this->params, 'tos', array()));
    $params['subject'] = idx($this->params, 'subject');
    $params['text'] = idx($this->params, 'body');

    if (idx($this->params, 'html-body')) {
      $params['html'] = idx($this->params, 'html-body');
    }

    $from = idx($this->params, 'from');
    $from_name = idx($this->params, 'from-name');
    $params['from'] = $this->renderAddress($from, $from_name);

    if (idx($this->params, 'reply-to')) {
      $replyto = $this->params['reply-to'];
      $params['h:reply-to'] = implode(', ', $replyto);
    }

    if (idx($this->params, 'ccs')) {
      $params['cc'] = implode(', ', $this->params['ccs']);
    }

    foreach (idx($this->params, 'headers', array()) as $header) {
      list($name, $value) = $header;
      $params['h:'.$name] = $value;
    }

    $future = new HTTPSFuture(
      "https://api:{$key}@api.mailgun.net/v2/{$domain}/messages",
      $params);
    $future->setMethod('POST');

    foreach ($this->attachments as $attachment) {
      $future->attachFileData(
        'attachment',
        $attachment['data'],
        $attachment['name'],
        $attachment['type']);
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

    return true;
  }

}
