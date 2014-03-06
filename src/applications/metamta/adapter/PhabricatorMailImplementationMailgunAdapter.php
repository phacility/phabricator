<?php

/**
 * Mail adapter that uses Mailgun's web API to deliver email.
 */
final class PhabricatorMailImplementationMailgunAdapter
  extends PhabricatorMailImplementationAdapter {

  private $params = array();

  public function setFrom($email, $name = '') {
    $this->params['from'] = $email;
    $this->params['from-name'] = $name;
    return $this;
  }

  public function addReplyTo($email, $name = '') {
    if (empty($this->params['reply-to'])) {
      $this->params['reply-to'] = array();
    }
    $this->params['reply-to'][] = array(
      'email' => $email,
      'name'  => $name,
    );
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
    // TODO: implement attachments. Requires changes in HTTPSFuture
    throw new Exception(
      "Mailgun adapter does not currently support attachments.");
  }

  public function addHeader($header_name, $header_value) {
    $this->params['headers'][] = array($header_name, $header_value);
    return $this;
  }

  public function setBody($body) {
    $this->params['body'] = $body;
    return $this;
  }

  public function setSubject($subject) {
    $this->params['subject'] = $subject;
    return $this;
  }

  public function setIsHTML($is_html) {
    $this->params['is-html'] = $is_html;
    return $this;
  }

  public function supportsMessageIDHeader() {
    return false;
  }

  public function send() {
    $key = PhabricatorEnv::getEnvConfig('mailgun.api-key');
    $domain = PhabricatorEnv::getEnvConfig('mailgun.domain');
    $params = array();

    $params['to'] = idx($this->params, 'tos', array());
    $params['subject'] = idx($this->params, 'subject');

    if (idx($this->params, 'is-html')) {
      $params['html'] = idx($this->params, 'body');
    } else {
      $params['text'] = idx($this->params, 'body');
    }

    $from = idx($this->params, 'from');
    if (idx($this->params, 'from-name')) {
      $params['from'] = "{$this->params['from-name']} <{$from}>";
    } else {
      $params['from'] = $from;
    }

    if (idx($this->params, 'reply-to')) {
      $replyto = $this->params['reply-to'];
      $params['h:reply-to'] = $replyto;
    }

    if (idx($this->params, 'ccs')) {
      $params['cc'] = $this->params['ccs'];
    }

    foreach (idx($this->params, 'headers', array()) as $header) {
      list($name, $value) = $header;
      $params['h:'.$name] = $value;
    }

    $future = new HTTPSFuture(
      "https://api:{$key}@api.mailgun.net/v2/{$domain}/messages",
      $params);
    $future->setMethod('POST');

    list($body) = $future->resolvex();

    $response = json_decode($body, true);
    if (!is_array($response)) {
      throw new Exception("Failed to JSON decode response: {$body}");
    }

    if (!idx($response, 'id')) {
      $message = $response['message'];
      throw new Exception("Request failed with errors: {$message}.");
    }

    return true;
  }

}
