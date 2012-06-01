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

final class PhabricatorUIExampleRenderController
  extends PhabricatorUIExampleController {

  private $class;

  public function willProcessRequest(array $data) {
    $this->class = idx($data, 'class');
  }

  public function processRequest() {

    $classes = id(new PhutilSymbolLoader())
      ->setAncestorClass('PhabricatorUIExample')
      ->selectAndLoadSymbols();
    $classes = ipull($classes, 'name', 'name');

    $selected = null;
    foreach ($classes as $class => $ignored) {
      $classes[$class] = newv($class, array());
      if ($this->class == $classes[$class]->getName()) {
        $selected = $class;
      }
    }

    if (!$selected) {
      $selected = head_key($classes);
    }

    $nav = new AphrontSideNavView();

    foreach ($classes as $class => $obj) {
      $name = $obj->getName();

      $nav->addNavItem(
        phutil_render_tag(
          'a',
          array(
            'href' => '/uiexample/view/'.$name.'/',
            'class' => ($selected == $class)
              ? 'aphront-side-nav-selected'
              : null,
          ),
          phutil_escape_html($obj->getName())));
    }

    require_celerity_resource('phabricator-ui-example-css');

    $example = $classes[$selected];
    $example->setRequest($this->getRequest());


    $nav->appendChild(
      '<div class="phabricator-ui-example-header">'.
        '<h1 class="phabricator-ui-example-name">'.
          phutil_escape_html($example->getName()).
          ' ('.get_class($example).')'.
        '</h1>'.
        '<p class="phabricator-ui-example-description">'
          .$example->getDescription().
        '</p>'.
      '</div>');

    $nav->appendChild($example->renderExample());

    return $this->buildStandardPageResponse(
      $nav,
      array(
        'title' => 'UI Example',
      ));
  }

}
