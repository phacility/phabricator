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

final class DifferentialCCWelcomeMail extends DifferentialReviewRequestMail {

  protected function renderVaryPrefix() {
    return '[Added to CC]';
  }

  protected function renderBody() {

    $actor = $this->getActorName();
    $name  = $this->getRevision()->getTitle();
    $body = array();

    $body[] = "{$actor} added you to the CC list for the revision \"{$name}\".";
    $body[] = null;

    $body[] = $this->renderReviewRequestBody();

    return implode("\n", $body);
  }
}
