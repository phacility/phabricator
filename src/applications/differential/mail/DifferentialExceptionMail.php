<?php

/*
 * Copyright 2012 Facebook, Inc.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

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


