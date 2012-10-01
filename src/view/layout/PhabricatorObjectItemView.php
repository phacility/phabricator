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

final class PhabricatorObjectItemView extends AphrontView {

  private $header;
  private $href;
  private $attributes = array();
  private $details = array();
  private $dates = array();

  public function setHref($href) {
    $this->href = $href;
    return $this;
  }

  public function getHref() {
    return $this->href;
  }

  public function setHeader($header) {
    $this->header = $header;
    return $this;
  }

  public function getHeader() {
    return $this->header;
  }

  public function addDetail($name, $value, $class = null) {
    $this->details[] = array(
      'name'  => $name,
      'value' => $value,
    );
    return $this;
  }

  public function addAttribute($attribute) {
    $this->attributes[] = $attribute;
    return $this;
  }

  public function render() {
    $header = phutil_render_tag(
      'a',
      array(
        'href' => $this->href,
        'class' => 'phabricator-object-item-name',
      ),
      phutil_escape_html($this->header));

    $details = null;
    if ($this->details) {
      $details = array();
      foreach ($this->details as $detail) {
        $details[] =
          '<dt class="phabricator-object-detail-key">'.
            phutil_escape_html($detail['name']).
          '</dt>';
        $details[] =
          '<dd class="phabricator-object-detail-value">'.
            $detail['value'].
          '</dt>';
      }
      $details = phutil_render_tag(
        'dl',
        array(
          'class' => 'phabricator-object-detail-list',
        ),
        implode('', $details));
    }

    $attrs = null;
    if ($this->attributes) {
      $attrs = array();
      foreach ($this->attributes as $attribute) {
        $attrs[] = '<li>'.$attribute.'</li>';
      }
      $attrs = phutil_render_tag(
        'ul',
        array(
          'class' => 'phabricator-object-item-attributes',
        ),
        implode('', $attrs));
    }

    return phutil_render_tag(
      'div',
      array(
        'class' => 'phabricator-object-item',
      ),
      $header.$details.$attrs);
  }

}
