<?php

abstract class DiffusionController extends PhabricatorController {

  protected $diffusionRequest;

  public function willProcessRequest(array $data) {
    if (isset($data['callsign'])) {
      $drequest = DiffusionRequest::newFromAphrontRequestDictionary($data);
      $this->diffusionRequest = $drequest;
    }
  }

  public function setDiffusionRequest(DiffusionRequest $request) {
    $this->diffusionRequest = $request;
    return $this;
  }

  protected function getDiffusionRequest() {
    if (!$this->diffusionRequest) {
      throw new Exception("No Diffusion request object!");
    }
    return $this->diffusionRequest;
  }

  public function buildStandardPageResponse($view, array $data) {

    $page = $this->buildStandardPageView();

    $page->setApplicationName('Diffusion');
    $page->setBaseURI('/diffusion/');
    $page->setTitle(idx($data, 'title'));
    $page->setGlyph("\xE2\x89\x88");
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

    foreach ($navs as $action => $name) {
      $href = $drequest->generateURI(
        array(
          'action' => $action,
        ));

      $nav->addNavItem(
        phutil_render_tag(
          'a',
          array(
            'href'  => $href,
            'class' =>
              ($action == $selected
                ? 'aphront-side-nav-selected'
                : null),
          ),
          $name));
    }

    // TODO: URI encoding might need to be sorted out for this link.

    $nav->addNavItem(
      phutil_render_tag(
        'a',
        array(
          'href'  => '/owners/view/search/'.
            '?repository='.phutil_escape_uri($drequest->getCallsign()).
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
      ->setFields(DifferentialRevisionListView::getDefaultFields())
      ->setUser($this->getRequest()->getUser())
      ->loadAssets();

    $phids = $view->getRequiredHandlePHIDs();
    $handles = $this->loadViewerHandles($phids);
    $view->setHandles($handles);

    $panel = new AphrontPanelView();
    $panel->setId('pending-differential-revisions');
    $panel->setHeader('Pending Differential Revisions');
    $panel->appendChild($view);

    return $panel;
  }

  private function buildCrumbList(array $spec = array()) {

    $spec = $spec + array(
      'commit'  => null,
      'tags'    => null,
      'branches'    => null,
      'view'    => null,
    );

    $crumb_list = array();

    // On the home page, we don't have a DiffusionRequest.
    if ($this->diffusionRequest) {
      $drequest = $this->getDiffusionRequest();
      $repository = $drequest->getRepository();
    } else {
      $drequest = null;
      $repository = null;
    }

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

    if (!$spec['commit'] && !$spec['tags'] && !$spec['branches']) {
      $branch_name = $drequest->getBranch();
      if ($branch_name) {
        $repository_name .= ' ('.phutil_escape_html($branch_name).')';
      }
    }

    if (!$spec['view'] && !$spec['commit']
      && !$spec['tags'] && !$spec['branches']) {
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

    if ($spec['tags']) {
      if ($spec['commit']) {
        $crumb_list[] = "Tags for ".phutil_render_tag(
          'a',
          array(
            'href' => $drequest->generateURI(
              array(
                'action' => 'commit',
                'commit' => $raw_commit,
              )),
          ),
          phutil_escape_html("r{$callsign}{$raw_commit}"));
      } else {
        $crumb_list[] = 'Tags';
      }
      return $crumb_list;
    }

    if ($spec['branches']) {
      $crumb_list[] = 'Branches';
      return $crumb_list;
    }

    if ($spec['commit']) {
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

    $uri_params = array(
      'action' => $view,
    );

    if (!strlen($path)) {
      $crumb_list[] = $view_name;
    } else {

      $crumb_list[] = phutil_render_tag(
        'a',
        array(
          'href' => $drequest->generateURI(
            array(
              'path' => '',
            ) + $uri_params),
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
            'href' => $drequest->generateURI(
              array(
                'path' => $thus_far,
              ) + $uri_params),
          ),
          phutil_escape_html($path_part));
      }

      $path_sections[] = phutil_escape_html($last);
      $path_sections = '/'.implode('/', $path_sections);

      $crumb_list[] = $path_sections;
    }

    $last_crumb = array_pop($crumb_list);

    if ($raw_commit) {
      $jump_link = phutil_render_tag(
        'a',
        array(
          'href' => $drequest->generateURI(
            array(
              'commit' => '',
            ) + $uri_params),
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
