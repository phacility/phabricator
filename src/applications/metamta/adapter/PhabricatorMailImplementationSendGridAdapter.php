<?php

/**
 * Mail adapter that uses SendGrid's web API to deliver email.
 */
final class PhabricatorMailImplementationSendGridAdapter
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
    if (empty($this->params['files'])) {
      $this->params['files'] = array();
    }
    $this->params['files'][$filename] = $data;
  }

  public function addHeader($header_name, $header_value) {
    $this->params['headers'][] = array($header_name, $header_value);
    return $this;
  }

  public function setBody($body) {
    $this->params['body'] = $body;
    return $this;
  }

  public function setHTMLBody($body) {
    $this->params['html-body'] = $body;
    return $this;
  }


  public function setSubject($subject) {
    $this->params['subject'] = $subject;
    return $this;
  }

  public function supportsMessageIDHeader() {
    return false;
  }

  public function send() {

    $user = PhabricatorEnv::getEnvConfig('sendgrid.api-user');
    $key  = PhabricatorEnv::getEnvConfig('sendgrid.api-key');

    if (!$user || !$key) {
      throw new Exception(
        pht(
          "Configure '%s' and '%s' to use SendGrid for mail delivery.",
          'sendgrid.api-user',
          'sendgrid.api-key'));
    }

    $params = array();

    $ii = 0;
    foreach (idx($this->params, 'tos', array()) as $to) {
      $params['to['.($ii++).']'] = $to;
    }

    $params['subject'] = idx($this->params, 'subject');
    $params['text'] = idx($this->params, 'body');

    if (idx($this->params, 'html-body')) {
      $params['html'] = idx($this->params, 'html-body');
    }

    $params['from'] = idx($this->params, 'from');
    if (idx($this->params, 'from-name')) {
      $params['fromname'] = $this->params['from-name'];
    }

    if (idx($this->params, 'reply-to')) {
      $replyto = $this->params['reply-to'];

      // Pick off the email part, no support for the name part in this API.
      $params['replyto'] = $replyto[0]['email'];
    }

    foreach (idx($this->params, 'files', array()) as $name => $data) {
      $params['files['.$name.']'] = $data;
    }

    $headers = idx($this->params, 'headers', array());

    // See SendGrid Support Ticket #29390; there's no explicit REST API support
    // for CC right now but it works if you add a generic "Cc" header.
    //
    // SendGrid said this is supported:
    //   "You can use CC as you are trying to do there [by adding a generic
    //    header]. It is supported despite our limited documentation to this
    //    effect, I am glad you were able to figure it out regardless. ..."
    if (idx($this->params, 'ccs')) {
      $headers[] = array('Cc', implode(', ', $this->params['ccs']));
    }

    if ($headers) {
      // Convert to dictionary.
      $headers = ipull($headers, 1, 0);
      $headers = json_encode($headers);
      $params['headers'] = $headers;
    }

    $params['api_user'] = $user;
    $params['api_key'] = $key;

    $future = new HTTPSFuture(
      'https://sendgrid.com/api/mail.send.json',
      $params);
    $future->setMethod('POST');

    list($body) = $future->resolvex();

    $response = null;
    try {
      $response = phutil_json_decode($body);
    } catch (PhutilJSONParserException $ex) {
      throw new PhutilProxyException(
        pht('Failed to JSON decode response.'),
        $ex);
    }

    if ($response['message'] !== 'success') {
      $errors = implode(';', $response['errors']);
      throw new Exception(pht('Request failed with errors: %s.', $errors));
    }

    return true;
  }

}
