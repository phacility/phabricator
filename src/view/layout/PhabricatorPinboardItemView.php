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

final class PhabricatorPinboardItemView extends AphrontView {

  private $imageURI;
  private $uri;
  private $header;

  private $imageWidth;
  private $imageHeight;

  public function setHeader($header) {
    $this->header = $header;
    return $this;
  }

  public function setURI($uri) {
    $this->uri = $uri;
    return $this;
  }

  public function setImageURI($image_uri) {
    $this->imageURI = $image_uri;
    return $this;
  }

  public function setImageSize($x, $y) {
    $this->imageWidth = $x;
    $this->imageHeight = $y;
    return $this;
  }

  public function render() {
    $header = null;
    if ($this->header) {
      $header = hsprintf('<a href="%s">%s</a>', $this->uri, $this->header);
      $header = phutil_render_tag(
        'div',
        array(
          'class' => 'phabricator-pinboard-item-header',
        ),
        $header);
    }

    $image = phutil_render_tag(
      'a',
      array(
        'href' => $this->uri,
        'class' => 'phabricator-pinboard-item-image-link',
      ),
      phutil_render_tag(
        'img',
        array(
          'src'     => $this->imageURI,
          'width'   => $this->imageWidth,
          'height'  => $this->imageHeight,
        )));

    $content = $this->renderChildren();
    if ($content) {
      $content = phutil_render_tag(
        'div',
        array(
          'class' => 'phabricator-pinboard-item-content',
        ),
        $content);
    }

    return phutil_render_tag(
      'div',
      array(
        'class' => 'phabricator-pinboard-item-view',
      ),
      $header.
      $image.
      $content);
  }

}
