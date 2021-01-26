<?php

/**
 * In Git, there appears to be no way to send a message which will be output
 * by `git clone http://...`, although the response code is visible. We send
 * the message in a header which is visible with "GIT_CURL_VERBOSE" if you
 * know where to look.
 *
 * In Mercurial, the HTTP status response message is printed to the console, so
 * we send human-readable text there.
 *
 * In Subversion, we can get it to print a custom message if we send an
 * invalid/unknown response code, although the output is ugly and difficult
 * to read. For known codes like 404, it prints a canned message.
 *
 * All VCS binaries ignore the response body; we include it only for
 * completeness.
 */
final class PhabricatorVCSResponse extends AphrontResponse {

  private $code;
  private $message;

  public function __construct($code, $message) {
    $this->code = $code;

    $message = head(phutil_split_lines($message));
    $this->message = $message;
  }

  public function getMessage() {
    return $this->message;
  }

  public function buildResponseString() {
    return $this->code.' '.$this->message;
  }

  public function getHeaders() {
    $headers = array();

    if ($this->getHTTPResponseCode() == 401) {
      $headers[] = array(
        'WWW-Authenticate',
        'Basic realm="Phabricator Repositories"',
      );
    }

    $message = $this->getMessage();
    if (strlen($message)) {
      foreach (phutil_split_lines($message, false) as $line) {
        $headers[] = array(
          'X-Phabricator-Message',
          $line,
        );
      }
    }

    return $headers;
  }

  public function getCacheHeaders() {
    return array();
  }

  public function getHTTPResponseCode() {
    return $this->code;
  }

  public function getHTTPResponseMessage() {
    return $this->message;
  }

}
