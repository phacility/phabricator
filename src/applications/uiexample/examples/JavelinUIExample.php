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

    $placeholder_id = celerity_generate_unique_node_id();

    Javelin::initBehavior(
      'placeholder',
      array(
        'id'    => $placeholder_id,
        'text'  => 'This is a placeholder',
      ));

    $form = id(new AphrontFormView())
      ->setUser($user)
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setLabel('Placeholder')
          ->setID($placeholder_id))
      ->appendChild(
        id(new AphrontFormSubmitControl())
          ->setValue('Submit'));

    $panel = new AphrontPanelView();
    $panel->setHeader('A Form');
    $panel->setWidth(AphrontPanelView::WIDTH_FORM);
    $panel->appendChild($form);

    return $panel;
  }
}
