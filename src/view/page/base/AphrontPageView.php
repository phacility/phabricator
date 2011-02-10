<?php

/*
 * Copyright 2011 Facebook, Inc.
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

class AphrontPageView extends AphrontView {

  private $title;

  public function setTitle($title) {
    $this->title = $title;
    return $this;
  }

  public function getTitle() {
    return $this->title;
  }

  protected function getHead() {
    return '';
  }

  protected function getBody() {
    return $this->renderChildren();
  }

  protected function getTail() {
    return '';
  }

  protected function willRenderPage() {
    return;
  }

  protected function willSendResponse($response) {
    return $response;
  }

  public function render() {

    $this->willRenderPage();

    $title = phutil_escape_html($this->getTitle());
    $head  = $this->getHead();
    $body  = $this->getBody();
    $tail  = $this->getTail();

    $response = <<<EOHTML
<!DOCTYPE html>
<html>
  <head>
    <title>{$title}</title>
    {$head}
  </head>
  <body>
    {$body}
  </body>
  {$tail}
</html>

EOHTML;

    $response = $this->willSendResponse($response);
    return $response;

  }

}
