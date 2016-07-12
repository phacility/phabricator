<?php

final class AphrontUnhandledExceptionResponse
  extends AphrontStandaloneHTMLResponse {

  private $exception;

  public function setException(Exception $exception) {
    // Log the exception unless it's specifically a silent malformed request
    // exception.

    $should_log = true;
    if ($exception instanceof AphrontMalformedRequestException) {
      if ($exception->getIsUnlogged()) {
        $should_log = false;
      }
    }

    if ($should_log) {
      phlog($exception);
    }

    $this->exception = $exception;
    return $this;
  }

  public function getHTTPResponseCode() {
    return 500;
  }

  protected function getResources() {
    return array(
      'css/application/config/config-template.css',
      'css/application/config/unhandled-exception.css',
    );
  }

  protected function getResponseTitle() {
    $ex = $this->exception;

    if ($ex instanceof AphrontMalformedRequestException) {
      return $ex->getTitle();
    } else {
      return pht('Unhandled Exception');
    }
  }

  protected function getResponseBodyClass() {
    return 'unhandled-exception';
  }

  protected function getResponseBody() {
    $ex = $this->exception;

    if ($ex instanceof AphrontMalformedRequestException) {
      $title = $ex->getTitle();
    } else {
      $title = get_class($ex);
    }

    $body = $ex->getMessage();
    $body = phutil_escape_html_newlines($body);

    return phutil_tag(
      'div',
      array(
        'class' => 'unhandled-exception-detail',
      ),
      array(
        phutil_tag(
          'h1',
          array(
            'class' => 'unhandled-exception-title',
          ),
          $title),
        phutil_tag(
          'div',
          array(
            'class' => 'unhandled-exception-body',
          ),
          $body),
      ));
  }

  protected function buildPlainTextResponseString() {
    $ex = $this->exception;

    return pht(
      '%s: %s',
      get_class($ex),
      $ex->getMessage());
  }

}
