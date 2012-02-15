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

abstract class DiffusionController extends PhabricatorController {

  protected $diffusionRequest;

  public function willProcessRequest(array $data) {
    $this->diffusionRequest = DiffusionRequest::newFromAphrontRequestDictionary(
      $data);
  }

  public function setDiffusionRequest(DiffusionRequest $request) {
    $this->diffusionRequest = $request;
    return $this;
  }

  protected function getDiffusionRequest() {
    return $this->diffusionRequest;
  }

  public function buildStandardPageResponse($view, array $data) {

    $page = $this->buildStandardPageView();

    $page->setApplicationName('Diffusion');
    $page->setBaseURI('/diffusion/');
    $page->setTitle(idx($data, 'title'));
    $page->setGlyph("\xE2\x89\x88");
    $page->setTabs(
      array(
        'help' => array(
          'href' => PhabricatorEnv::getDoclink(
            'article/Diffusion_User_Guide.html'),
          'name' => 'Help',
        ),
      ),
      null);
    $page->setSearchDefaultScope(PhabricatorSearchScope::SCOPE_COMMITS);

    $page->appendChild($view);

    $response = new AphrontWebpageResponse();
    return $response->setContent($page->render());
  }

  final protected function buildSideNav($selected, $has_change_view) {
    $nav = new AphrontSideNavView();

    $navs = array(
      'history' => 'History View',
      'browse'  => 'Browse View',
      'change'  => 'Change View',
    );

    if (!$has_change_view) {
      unset($navs['change']);
    }

    $drequest = $this->getDiffusionRequest();
    $repository = $drequest->getRepository();
    $callsign = $repository->getCallsign();

    $branch_uri = $drequest->getBranchURIComponent($drequest->getBranch());
    $path_uri = $branch_uri.$drequest->getPath();

    $commit_uri = null;
    $raw_commit = $drequest->getRawCommit();
    if ($raw_commit) {
      $commit_uri = ';'.$drequest->getCommitURIComponent($raw_commit);
    }

    foreach ($navs as $uri => $name) {
      $nav->addNavItem(
        phutil_render_tag(
          'a',
          array(
            'href'  => "/diffusion/{$callsign}/{$uri}/{$path_uri}{$commit_uri}",
            'class' =>
              ($uri == $selected
                ? 'aphront-side-nav-selected'
                : null),
          ),
          $name));
    }
    $nav->addNavItem(
      phutil_render_tag(
        'a',
        array(
          'href'  => '/owners/view/search/'.
            '?repository='.phutil_escape_uri($callsign).
            '&path='.phutil_escape_uri('/'.$drequest->getPath()),
        ),
        'Search Owners'));

    return $nav;
  }

  public function buildCrumbs(array $spec = array()) {
    $crumbs = new AphrontCrumbsView();
    $crumb_list = $this->buildCrumbList($spec);
    $crumbs->setCrumbs($crumb_list);
    return $crumbs;
  }

  protected function buildOpenRevisions() {
    $drequest = $this->getDiffusionRequest();
    $repository = $drequest->getRepository();
    $path = $drequest->getPath();

    $path_map = id(new DiffusionPathIDQuery(array($path)))->loadPathIDs();
    $path_id = idx($path_map, $path);
    if (!$path_id) {
      return null;
    }

    $revisions = id(new DifferentialRevisionQuery())
      ->withPath($repository->getID(), $path_id)
      ->withStatus(DifferentialRevisionQuery::STATUS_OPEN)
      ->setOrder(DifferentialRevisionQuery::ORDER_PATH_MODIFIED)
      ->setLimit(10)
      ->needRelationships(true)
      ->execute();

    if (!$revisions) {
      return null;
    }

    $view = id(new DifferentialRevisionListView())
      ->setRevisions($revisions)
      ->setUser($this->getRequest()->getUser());

    $phids = $view->getRequiredHandlePHIDs();
    $handles = id(new PhabricatorObjectHandleData($phids))->loadHandles();
    $view->setHandles($handles);

    $panel = new AphrontPanelView();
    $panel->setHeader('Pending Differential Revisions');
    $panel->appendChild($view);

    return $panel;
  }

  private function buildCrumbList(array $spec = array()) {
    $drequest = $this->getDiffusionRequest();

    $crumb_list = array();

    $repository = $drequest->getRepository();
    if ($repository) {
      $crumb_list[] = phutil_render_tag(
        'a',
        array(
          'href' => '/diffusion/',
        ),
        'Diffusion');
    } else {
      $crumb_list[] = 'Diffusion';
      return $crumb_list;
    }

    $callsign = $repository->getCallsign();
    $repository_name = phutil_escape_html($repository->getName()).' Repository';

    if (empty($spec['commit'])) {
      $branch_name = $drequest->getBranch();
      if ($branch_name) {
        $repository_name .= ' ('.phutil_escape_html($branch_name).')';
      }
    }

    if (empty($spec['view']) && empty($spec['commit'])) {
      $crumb_list[] = $repository_name;
      return $crumb_list;
    }

    $crumb_list[] = phutil_render_tag(
      'a',
      array(
        'href' => "/diffusion/{$callsign}/",
      ),
      $repository_name);

    $raw_commit = $drequest->getRawCommit();
    if (isset($spec['commit'])) {
      $crumb_list[] = "r{$callsign}{$raw_commit}";
      return $crumb_list;
    }

    $view = $spec['view'];

    $path = null;
    if (isset($spec['path'])) {
      $path = $drequest->getPath();
    }

    if ($raw_commit) {
      $commit_link = DiffusionView::linkCommit(
        $repository,
        $raw_commit);
    } else {
      $commit_link = '';
    }

    switch ($view) {
      case 'history':
        $view_name = 'History';
        break;
      case 'browse':
        $view_name = 'Browse';
        break;
      case 'change':
        $view_name = 'Change';
        $crumb_list[] = phutil_escape_html($path).' ('.$commit_link.')';
        return $crumb_list;
    }

    $branch_uri = $drequest->getBranchURIComponent($drequest->getBranch());
    $view_root_uri = "/diffusion/{$callsign}/{$view}/{$branch_uri}";
    $jump_href = $view_root_uri;

    $view_tail_uri = null;
    if ($raw_commit) {
      $view_tail_uri = ';'.$drequest->getCommitURIComponent($raw_commit);
    }

    if (!strlen($path)) {
      $crumb_list[] = $view_name;
    } else {

      $crumb_list[] = phutil_render_tag(
        'a',
        array(
          'href' => $view_root_uri.$view_tail_uri,
        ),
        $view_name);

      $path_parts = explode('/', $path);
      do {
        $last = array_pop($path_parts);
      } while ($last == '');

      $path_sections = array();
      $thus_far = '';
      foreach ($path_parts as $path_part) {
        $thus_far .= $path_part.'/';
        $path_sections[] = phutil_render_tag(
          'a',
          array(
            'href' => $view_root_uri.$thus_far.$view_tail_uri,
          ),
          phutil_escape_html($path_part));
      }

      $path_sections[] = phutil_escape_html($last);
      $path_sections = '/'.implode('/', $path_sections);

      $jump_href = $view_root_uri.$thus_far.$last;

      $crumb_list[] = $path_sections;
    }

    $last_crumb = array_pop($crumb_list);

    if ($raw_commit) {
      $jump_link = phutil_render_tag(
        'a',
        array(
          'href' => $jump_href,
        ),
        'Jump to HEAD');
      $last_crumb .= " @ {$commit_link} ({$jump_link})";
    } else {
      $last_crumb .= " @ HEAD";
    }

    $crumb_list[] = $last_crumb;

    return $crumb_list;
  }

}
