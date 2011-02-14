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

class PhabricatorSearchController extends PhabricatorSearchBaseController {

  private $id;

  public function willProcessRequest(array $data) {
    $this->id = idx($data, 'id');
  }

  public function processRequest() {
    $request = $this->getRequest();
    $user = $request->getUser();

    if ($this->id) {
      $query = id(new PhabricatorSearchQuery())->load($this->id);
      if (!$query) {
        return new Aphront404Response();
      }
    } else {
      $query = new PhabricatorSearchQuery();

      if ($request->isFormPost()) {
        $query->setQuery($request->getStr('query'));
        $query->save();
        return id(new AphrontRedirectResponse())
          ->setURI('/search/'.$query->getID().'/');
      }
    }


    $search_form = new AphrontFormView();
    $search_form
      ->setUser($user)
      ->setAction('/search/')
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setLabel('Search')
          ->setName('query')
          ->setValue($query->getQuery()))
      ->appendChild(
        id(new AphrontFormSubmitControl())
          ->setValue('Search'));

    $search_panel = new AphrontPanelView();
    $search_panel->setHeader('Search Phabricator');
    $search_panel->appendChild($search_form);

    if ($query->getID()) {
      $executor = new PhabricatorSearchMySQLExecutor();
      $results = $executor->executeSearch($query);
      $results = ipull($results, 'phid');
      $handles = id(new PhabricatorObjectHandleData($results))
        ->loadHandles();
      $results = array();
      foreach ($handles as $handle) {
        $results[] = '<h1>'.$handle->renderLink().'</h1>';
      }
      $results =
        '<div style="padding: 1em 2em 2em;">'.
          implode("\n", $results).
        '</div>';
    } else {
      $results = null;
    }

    $results = print_r($results, true);

    return $this->buildStandardPageResponse(
      array(
        $search_panel,
        $results,
      ),
      array(
        'title' => 'Results: what',
      ));
  }

}
