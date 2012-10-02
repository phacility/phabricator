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

final class PhabricatorRemarkupControl extends AphrontFormTextAreaControl {

  protected function renderInput() {

    Javelin::initBehavior('phabricator-remarkup-assist', array());

    $actions = array(
      'b'     => array(
        'text' => 'B',
      ),
      'i'     => array(
        'text' => 'I',
      ),
      'tt'    => array(
        'text' => 'T',
      ),
      's' => array(
        'text' => 'S',
      ),
      array(
        'spacer' => true,
      ),
      'ul' => array(
        'text' => "\xE2\x80\xA2",
      ),
      'ol' => array(
        'text' => '1.',
      ),
      'code' => array(
        'text' => '{}',
      ),
      array(
        'spacer' => true,
      ),
      'mention' => array(
        'text' => '@',
      ),
      array(
        'spacer' => true,
      ),
      'h1' => array(
        'text' => 'H',
      ),
      array(
        'spacer' => true,
      ),
      'help'  => array(
        'align' => 'right',
        'text'  => '?',
        'href'  => PhabricatorEnv::getDoclink(
          'article/Remarkup_Reference.html'),
      ),
    );

    $buttons = array();
    foreach ($actions as $action => $spec) {
      if (idx($spec, 'spacer')) {
        $buttons[] = '<span> </span>';
        continue;
      }

      $classes = array();
      $classes[] = 'button';
      $classes[] = 'grey';
      $classes[] = 'remarkup-assist-button';
      if (idx($spec, 'align') == 'right') {
        $classes[] = 'remarkup-assist-right';
      }

      $href = idx($spec, 'href', '#');
      if ($href == '#') {
        $meta = array('action' => $action);
        $mustcapture = true;
        $target = null;
      } else {
        $meta = null;
        $mustcapture = null;
        $target = '_blank';
      }

      $buttons[] = javelin_render_tag(
        'a',
        array(
          'class'       => implode(' ', $classes),
          'href'        => $href,
          'sigil'       => 'remarkup-assist',
          'meta'        => $meta,
          'mustcapture' => $mustcapture,
          'target'      => $target,
          'tabindex'    => -1,
        ),
        phutil_render_tag(
          'div',
          array(
            'class' => 'remarkup-assist remarkup-assist-'.$action,
          ),
          idx($spec, 'text', '')));
    }

    $buttons = implode('', $buttons);

    return javelin_render_tag(
      'div',
      array(
        'sigil' => 'remarkup-assist-control',
      ),
      $buttons.
      parent::renderInput());
  }

}
