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

class JavelinViewExample extends PhabricatorUIExample {

  public function getName() {
    return 'Javelin Views';
  }

  public function getDescription() {
    return 'Mix and match client and server views.';
  }

  public function renderExample() {

    $request = $this->getRequest();

    $init = $request->getStr('init');

    $parent_server_template = new JavelinViewExampleServerView();

    $parent_client_template = new AphrontJavelinView();
    $parent_client_template
      ->setName('JavelinViewExample')
      ->setCelerityResource('phabricator-uiexample-javelin-view');

    $child_server_template = new JavelinViewExampleServerView();

    $child_client_template = new AphrontJavelinView();
    $child_client_template
      ->setName('JavelinViewExample')
      ->setCelerityResource('phabricator-uiexample-javelin-view');

    $parent_server_template->appendChild($parent_client_template);
    $parent_client_template->appendChild($child_server_template);
    $child_server_template->appendChild($child_client_template);
    $child_client_template->appendChild('Hey, it worked.');

    $panel = new AphrontPanelView();
    $panel->appendChild($parent_server_template);

    return $panel;
  }
}
