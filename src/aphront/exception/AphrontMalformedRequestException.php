<?php

/**
 * These exceptions are raised when a client submits a malformed request.
 *
 * These errors are caught by Aphront itself and occur too early or too
 * fundamentally in request handling to allow the request to route to a
 * controller or survive to normal processing.
 *
 * These exceptions can be made "unlogged", which will prevent them from being
 * logged. The intent is that errors which are purely the result of client
 * failure and of no interest to the server can be raised silently to avoid
 * cluttering the logs with client errors that are not actionable.
 */
final class AphrontMalformedRequestException extends AphrontException {

  private $title;
  private $isUnlogged;

  public function __construct($title, $message, $unlogged = false) {
    $this->title = $title;
    $this->isUnlogged = $unlogged;
    parent::__construct($message);
  }

  public function getTitle() {
    return $this->title;
  }

  public function getIsUnlogged() {
    return $this->isUnlogged;
  }

}
