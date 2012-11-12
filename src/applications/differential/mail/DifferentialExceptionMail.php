<?php

final class DifferentialExceptionMail extends DifferentialMail {

  public function __construct(
    DifferentialRevision $revision,
    Exception $exception,
    $original_body) {

    $this->revision = $revision;
    $this->exception = $exception;
    $this->originalBody = $original_body;
  }

  protected function renderBody() {
    // Never called since buildBody() is overridden.
  }

  protected function renderSubject() {
    return "Exception: unable to process your mail request";
  }

  protected function renderVaryPrefix() {
    return '';
  }

  protected function buildBody() {
    $exception = $this->exception;
    $original_body = $this->originalBody;

    $message = $exception->getMessage();

    return <<<EOBODY
Your request failed because an exception was encoutered while processing it:

EXCEPTION: {$message}

-- Original Body -------------------------------------------------------------

{$original_body}

EOBODY;
  }

}


