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

final class PhabricatorPropertyListExample extends PhabricatorUIExample {

  public function getName() {
    return 'Property List';
  }

  public function getDescription() {
    return 'Use <tt>PhabricatorPropertyListView</tt> to render object '.
           'properties.';
  }

  public function renderExample() {
    $request = $this->getRequest();
    $user = $request->getUser();

    $view = new PhabricatorPropertyListView();

    $view->addProperty(
      pht('Color'),
      pht('Yellow'));

    $view->addProperty(
      pht('Size'),
      pht('Mouse'));

    $view->addProperty(
      pht('Element'),
      pht('Electric'));

    $view->addTextContent(
      'Lorem ipsum dolor sit amet, consectetur adipiscing elit. '.
      'Quisque rhoncus tempus massa, sit amet faucibus lectus bibendum '.
      'viverra. Nunc tempus tempor quam id iaculis. Maecenas lectus '.
      'velit, aliquam et consequat quis, tincidunt id dolor.');

    return $view;
  }
}
