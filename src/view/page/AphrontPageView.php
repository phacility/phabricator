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

abstract class AphrontPageView extends AphrontView {

  private $title;

  public function setTitle($title) {
    $this->title = $title;
    return $this;
  }

  public function getTitle() {
    $title = $this->title;
    if (is_array($title)) {
      $title = implode(" \xC2\xB7 ", $title);
    }
    return $title;
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

  protected function getBodyClasses() {
    return null;
  }

  public function render() {

    $this->willRenderPage();

    $title = phutil_escape_html($this->getTitle());
    $head  = $this->getHead();
    $body  = $this->getBody();
    $tail  = $this->getTail();

    $body_classes = $this->getBodyClasses();

    $body = phutil_render_tag(
      'body',
      array(
        'class' => nonempty($body_classes, null),
      ),
      $body.$tail);

    $response = <<<EOHTML
<!DOCTYPE html>
<html>
  <head>
    <meta charset="UTF-8" />
    <title>{$title}</title>
    {$head}
  </head>
  {$body}
</html>

EOHTML;

    $response = $this->willSendResponse($response);
    return $response;

  }

}
