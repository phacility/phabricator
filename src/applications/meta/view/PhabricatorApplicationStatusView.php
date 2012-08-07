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

final class PhabricatorApplicationStatusView extends AphrontView {

  private $count;
  private $text;
  private $type;

  const TYPE_NEEDS_ATTENTION  = 'needs';
  const TYPE_INFO             = 'info';
  const TYPE_OKAY             = 'okay';
  const TYPE_WARNING          = 'warning';
  const TYPE_EMPTY            = 'empty';

  public function setType($type) {
    $this->type = $type;
    return $this;
  }

  public function setText($text) {
    $this->text = $text;
    return $this;
  }

  public function setCount($count) {
    $this->count = $count;
    return $this;
  }

  public function getCount() {
    return $this->count;
  }

  public function render() {
    $classes = array(
      'phabricator-application-status',
      'phabricator-application-status-type-'.$this->type,
    );

    return phutil_render_tag(
      'span',
      array(
        'class' => implode(' ', $classes),
      ),
      phutil_escape_html($this->text));
  }

}
