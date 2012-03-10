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

final class DifferentialInlineCommentEditView extends AphrontView {

  private $user;
  private $inputs = array();
  private $uri;
  private $title;
  private $onRight;
  private $number;
  private $length;

  public function addHiddenInput($key, $value) {
    $this->inputs[] = array($key, $value);
    return $this;
  }

  public function setUser(PhabricatorUser $user) {
    $this->user = $user;
    return $this;
  }

  public function setSubmitURI($uri) {
    $this->uri = $uri;
    return $this;
  }

  public function setTitle($title) {
    $this->title = $title;
    return $this;
  }

  public function setOnRight($on_right) {
    $this->onRight = $on_right;
    $this->addHiddenInput('on_right', $on_right);
    return $this;
  }

  public function setNumber($number) {
    $this->number = $number;
    return $this;
  }

  public function setLength($length) {
    $this->length = $length;
    return $this;
  }

  public function render() {
    if (!$this->uri) {
      throw new Exception("Call setSubmitURI() before render()!");
    }
    if (!$this->user) {
      throw new Exception("Call setUser() before render()!");
    }

    $content = '<th></th><td>'.phabricator_render_form(
      $this->user,
      array(
        'action'    => $this->uri,
        'method'    => 'POST',
        'sigil'     => 'inline-edit-form',
      ),
      $this->renderInputs().
      $this->renderBody()).'</td>';
    $other = '<th></th><td></td>';

    if ($this->onRight) {
      $core = $other.$content;
    } else {
      $core = $content.$other;
    }

    return '<table><tr class="inline-comment-splint">'.$core.'</tr></table>';
  }

  private function renderInputs() {
    $out = array();
    foreach ($this->inputs as $input) {
      list($name, $value) = $input;
      $out[] = phutil_render_tag(
        'input',
        array(
          'type'  => 'hidden',
          'name'  => $name,
          'value' => $value,
        ),
        null);
    }
    return implode('', $out);
  }

  private function renderBody() {
    $buttons = array();

    $buttons[] = '<button>Ready</button>';
    $buttons[] = javelin_render_tag(
      'button',
      array(
        'sigil' => 'inline-edit-cancel',
        'class' => 'grey',
      ),
      'Cancel');

    $buttons = implode('', $buttons);
    return javelin_render_tag(
      'div',
      array(
        'class' => 'differential-inline-comment-edit',
        'sigil' => 'differential-inline-comment',
        'meta' => array(
          'on_right' => $this->onRight,
          'number' => $this->number,
          'length' => $this->length,
        ),
      ),
      '<div class="differential-inline-comment-edit-title">'.
        phutil_escape_html($this->title).
      '</div>'.
      '<div class="differential-inline-comment-edit-body">'.
        $this->renderChildren().
      '</div>'.
      '<div class="differential-inline-comment-edit-buttons">'.
        $buttons.
        '<div style="clear: both;"></div>'.
      '</div>');
  }

}
