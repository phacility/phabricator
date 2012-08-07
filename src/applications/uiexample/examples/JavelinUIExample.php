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

final class JavelinUIExample extends PhabricatorUIExample {

  public function getName() {
    return 'Javelin UI';
  }

  public function getDescription() {
    return 'Here are some Javelin UI elements that you could use.';
  }

  public function renderExample() {
    $request = $this->getRequest();
    $user = $request->getUser();

    // toggle-class

    $container_id  = celerity_generate_unique_node_id();
    $button_red_id = celerity_generate_unique_node_id();
    $button_blue_id = celerity_generate_unique_node_id();

    $button_red = javelin_render_tag(
      'a',
      array(
        'class' => 'button',
        'sigil' => 'jx-toggle-class',
        'href'  => '#',
        'id'    => $button_red_id,
        'meta'  => array(
          'map' => array(
            $container_id => 'jxui-red-border',
            $button_red_id => 'jxui-active',
          ),
        ),
      ),
      'Toggle Red Border');

    $button_blue = javelin_render_tag(
      'a',
      array(
        'class' => 'button jxui-active',
        'sigil' => 'jx-toggle-class',
        'href'  => '#',
        'id'    => $button_blue_id,
        'meta' => array(
          'state' => true,
          'map' => array(
            $container_id => 'jxui-blue-background',
            $button_blue_id => 'jxui-active',
          ),
        ),
      ),
      'Toggle Blue Background');

    $div = phutil_render_tag(
      'div',
      array(
        'id' => $container_id,
        'class' => 'jxui-example-container jxui-blue-background',
      ),
      $button_red.$button_blue);

    return array($div);
  }
}
