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

final class PhabricatorUIListFilterExample extends PhabricatorUIExample {

  public function getName() {
    return 'ListFilter';
  }

  public function getDescription() {
    return 'Use <tt>AphrontListFilterView</tt> to layout controls for '.
           'filtering and manipulating lists of objects.';
  }

  public function renderExample() {

    $filter = new AphrontListFilterView();
    $filter->addButton(
      phutil_render_tag(
        'a',
        array(
          'href' => '#',
          'class' => 'button green',
        ),
        'Create New Thing'));

    $form = new AphrontFormView();
    $form->setUser($this->getRequest()->getUser());
    $form
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setLabel('Query'))
      ->appendChild(
        id(new AphrontFormSubmitControl())
          ->setValue('Search'));

    $filter->appendChild($form);


    return $filter;
  }
}
