<?php

final class DiffusionGitLFSResponse extends AphrontResponse {

  private $content;

  public static function newErrorResponse($code, $message) {

    // We can optionally include "request_id" and "documentation_url" in
    // this response.

    return id(new self())
      ->setHTTPResponseCode($code)
      ->setContent(
        array(
          'message' => $message,
        ));
  }

  public function setContent(array $content) {
    $this->content = phutil_json_encode($content);
    return $this;
  }

  public function buildResponseString() {
    return $this->content;
  }

  public function getHeaders() {
    $headers = array(
      array('Content-Type', 'application/vnd.git-lfs+json'),
    );

    return array_merge(parent::getHeaders(), $headers);
  }

}
