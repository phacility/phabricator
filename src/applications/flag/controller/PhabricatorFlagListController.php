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

final class PhabricatorFlagListController extends PhabricatorFlagController {

  public function processRequest() {
    $request = $this->getRequest();
    $user = $request->getUser();

    $nav = new AphrontSideNavFilterView();
    $nav->setBaseURI(new PhutilURI('/flag/view/'));
    $nav->addFilter('all', 'Flags');
    $nav->selectFilter('all', 'all');

    $query = new PhabricatorFlagQuery();
    $query->withOwnerPHIDs(array($user->getPHID()));
    $query->setViewer($user);
    $query->needHandles(true);

    $flags = $query->execute();

    $view = new PhabricatorFlagListView();
    $view->setFlags($flags);
    $view->setUser($user);

    $panel = new AphrontPanelView();
    $panel->setHeader('Flags');
    $panel->appendChild($view);

    $nav->appendChild($panel);

    return $this->buildStandardPageResponse(
      $nav,
      array(
        'title' => 'Flags',
      ));
  }

}
