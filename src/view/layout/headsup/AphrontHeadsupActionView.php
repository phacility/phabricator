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

final class AphrontHeadsupActionView extends AphrontView {

  private $name;
  private $class;
  private $uri;
  private $workflow;
  private $instant;
  private $user;

  public function setName($name) {
    $this->name = $name;
    return $this;
  }

  public function setClass($class) {
    $this->class = $class;
    return $this;
  }

  public function setURI($uri) {
    $this->uri = $uri;
    return $this;
  }

  public function setWorkflow($workflow) {
    $this->workflow = $workflow;
    return $this;
  }

  public function setInstant($instant) {
    $this->instant = $instant;
    return $this;
  }

  public function setUser($user) {
    $this->user = $user;
    return $this;
  }

  public function render() {
    if ($this->instant) {
      $button_class = $this->class.' link';
      return phabricator_render_form(
        $this->user,
        array(
          'action' => $this->uri,
          'method' => 'post',
          'style'  => 'display: inline',
        ),
        '<button class="'.$button_class.'">'.
          phutil_escape_html($this->name).
        '</button>'
      );
    }

    if ($this->uri) {
      $tag = 'a';
    } else {
      $tag = 'span';
    }

    $attrs = array(
      'href' => $this->uri,
      'class' => $this->class,
    );

    if ($this->workflow) {
      $attrs['sigil'] = 'workflow';
    }

    return javelin_render_tag(
      $tag,
      $attrs,
      phutil_escape_html($this->name));
  }

}
