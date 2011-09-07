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

class JavelinReactorExample extends PhabricatorUIExample {
  public function getName() {
    return 'Javelin Reactor Examples';
  }

  public function getDescription() {
    return 'Lots of code';
  }

  public function renderExample() {
    $rows = array();

    $examples = array(
      array(
        'Reactive button only generates a stream of events',
        'ReactorButtonExample',
        'phabricator-uiexample-reactor-button',
        array(),
      ),
      array(
        'Reactive checkbox generates a boolean dynamic value',
        'ReactorCheckboxExample',
        'phabricator-uiexample-reactor-checkbox',
        array('checked' => true)
      ),
      array(
        'Reactive focus detector generates a boolean dynamic value',
        'ReactorFocusExample',
        'phabricator-uiexample-reactor-focus',
        array(),
      ),
      array(
        'Reactive input box, with normal and calmed output',
        'ReactorInputExample',
        'phabricator-uiexample-reactor-input',
        array('init' => 'Initial value'),
      ),
      array(
        'Reactive mouseover detector generates a boolean dynamic value',
        'ReactorMouseoverExample',
        'phabricator-uiexample-reactor-mouseover',
        array(),
      ),
      array(
        'Reactive radio buttons generate a string dynamic value',
        'ReactorRadioExample',
        'phabricator-uiexample-reactor-radio',
        array(),
      ),
      array(
        'Reactive select box generates a string dynamic value',
        'ReactorSelectExample',
        'phabricator-uiexample-reactor-select',
        array(),
      ),
      array(
        'sendclass makes the class of an element a string dynamic value',
        'ReactorSendClassExample',
        'phabricator-uiexample-reactor-sendclass',
        array()
      ),
      array(
        'sendproperties makes some properties of an object into dynamic values',
        'ReactorSendPropertiesExample',
        'phabricator-uiexample-reactor-sendproperties',
        array(),
      ),
    );

    foreach ($examples as $example) {
      list($desc, $name, $resource, $params) = $example;
      $template = new AphrontJavelinView();
      $template
        ->setName($name)
        ->setParameters($params)
        ->setCelerityResource($resource);
      $rows[] = array($desc, $template->render());
    }

    $table = new AphrontTableView($rows);

    $panel = new AphrontPanelView();
    $panel->appendChild($table);

    return $panel;
  }
}
