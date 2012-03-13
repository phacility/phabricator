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

/**
 * @group aphront
 */
final class Aphront403Response extends AphrontWebpageResponse {

  private $forbiddenText;
  public function setForbiddenText($text) {
    $this->forbiddenText = $text;
    return $this;
  }
  private function getForbiddenText() {
    return $this->forbiddenText;
  }

  public function getHTTPResponseCode() {
    return 403;
  }

  public function buildResponseString() {
    $forbidden_text = $this->getForbiddenText();
    if (!$forbidden_text) {
      $forbidden_text =
        'You do not have privileges to access the requested page.';
    }
    $failure = new AphrontRequestFailureView();
    $failure->setHeader('403 Forbidden');
    $failure->appendChild('<p>'.$forbidden_text.'</p>');

    $view = new PhabricatorStandardPageView();
    $view->setTitle('403 Forbidden');
    $view->setRequest($this->getRequest());
    $view->appendChild($failure);

    return $view->render();
  }

}
