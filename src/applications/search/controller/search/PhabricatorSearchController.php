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

/**
 * @group search
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

        if (strlen($request->getStr('type'))) {
          $query->setParameter('type', $request->getStr('type'));
        }

        if ($request->getArr('author')) {
          $query->setParameter('author', $request->getArr('author'));
        }

        if ($request->getArr('owner')) {
          $query->setParameter('owner', $request->getArr('owner'));
        }

        if ($request->getInt('open')) {
          $query->setParameter('open', $request->getInt('open'));
        }

        if ($request->getArr('project')) {
          $query->setParameter('project', $request->getArr('project'));
        }

        $query->save();
        return id(new AphrontRedirectResponse())
          ->setURI('/search/'.$query->getID().'/');
      }
    }

    $more = PhabricatorEnv::getEnvConfig('search.more-document-types', array());

    $options = array(
      '' => 'All Documents',
      PhabricatorPHIDConstants::PHID_TYPE_DREV => 'Differential Revisions',
      PhabricatorPHIDConstants::PHID_TYPE_CMIT => 'Repository Commits',
      PhabricatorPHIDConstants::PHID_TYPE_TASK => 'Maniphest Tasks',
      PhabricatorPHIDConstants::PHID_TYPE_WIKI => 'Phriction Documents',
      PhabricatorPHIDConstants::PHID_TYPE_USER => 'Phabricator Users',
    ) + $more;

    $status_options = array(
      0 => 'Open and Closed Documents',
      1 => 'Open Documents',
    );

    $phids = array_merge(
      $query->getParameter('author', array()),
      $query->getParameter('owner', array()),
      $query->getParameter('project', array())
    );

    $handles = id(new PhabricatorObjectHandleData($phids))
      ->loadHandles();

    $author_value = array_select_keys(
      $handles,
      $query->getParameter('author', array()));
    $author_value = mpull($author_value, 'getFullName', 'getPHID');

    $owner_value = array_select_keys(
      $handles,
      $query->getParameter('owner', array()));
    $owner_value = mpull($owner_value, 'getFullName', 'getPHID');

    $project_value = array_select_keys(
      $handles,
      $query->getParameter('project', array()));
    $project_value = mpull($project_value, 'getFullName', 'getPHID');

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
        id(new AphrontFormSelectControl())
          ->setLabel('Document Type')
          ->setName('type')
          ->setOptions($options)
          ->setValue($query->getParameter('type')))
      ->appendChild(
        id(new AphrontFormSelectControl())
          ->setLabel('Document Status')
          ->setName('open')
          ->setOptions($status_options)
          ->setValue($query->getParameter('open')))
      ->appendChild(
        id(new AphrontFormTokenizerControl())
          ->setName('author')
          ->setLabel('Author')
          ->setDatasource('/typeahead/common/users/')
          ->setValue($author_value))
      ->appendChild(
        id(new AphrontFormTokenizerControl())
          ->setName('owner')
          ->setLabel('Owner')
          ->setDatasource('/typeahead/common/searchowner/')
          ->setValue($owner_value)
          ->setCaption(
            'Tip: search for "Up For Grabs" to find unowned documents.'))
      ->appendChild(
        id(new AphrontFormTokenizerControl())
          ->setName('project')
          ->setLabel('Project')
          ->setDatasource('/typeahead/common/projects/')
          ->setValue($project_value))
      ->appendChild(
        id(new AphrontFormSubmitControl())
          ->setValue('Search'));

    $search_panel = new AphrontPanelView();
    $search_panel->setHeader('Search Phabricator');
    $search_panel->appendChild($search_form);

    require_celerity_resource('phabricator-search-results-css');

    if ($query->getID()) {

      $limit = 20;

      $pager = new AphrontPagerView();
      $pager->setURI($request->getRequestURI(), 'page');
      $pager->setPageSize($limit);
      $pager->setOffset($request->getInt('page'));

      $query->setParameter('limit', $limit + 1);
      $query->setParameter('offset', $pager->getOffset());

      $engine = PhabricatorSearchEngineSelector::newSelector()->newEngine();
      $results = $engine->executeSearch($query);
      $results = ipull($results, 'phid');

      $results = $pager->sliceResults($results);

      if ($results) {

        $loader = new PhabricatorObjectHandleData($results);
        $handles = $loader->loadHandles();
        $objects = $loader->loadObjects();
        $results = array();
        foreach ($handles as $phid => $handle) {
          $view = new PhabricatorSearchResultView();
          $view->setHandle($handle);
          $view->setQuery($query);
          $view->setObject($objects[$phid]);
          $results[] = $view->render();
        }
        $results =
          '<div class="phabricator-search-result-list">'.
            implode("\n", $results).
            '<div class="search-results-pager">'.
              $pager->render().
            '</div>'.
          '</div>';
      } else {
        $results =
          '<div class="phabricator-search-result-list">'.
            '<p class="phabricator-search-no-results">No search results.</p>'.
          '</div>';
      }
    } else {
      $results = null;
    }

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
