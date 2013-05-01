<?php

abstract class DiffusionController extends PhabricatorController {

  protected $diffusionRequest;

  public function willProcessRequest(array $data) {
    if (isset($data['callsign'])) {
      $drequest = DiffusionRequest::newFromAphrontRequestDictionary(
        $data,
        $this->getRequest());
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
    $nav = new AphrontSideNavFilterView();
    $nav->setBaseURI(new PhutilURI(''));

    $navs = array(
      'history' => 'History View',
      'browse'  => 'Browse View',
      'change'  => 'Change View',
    );

    if (!$has_change_view) {
      unset($navs['change']);
    }

    $drequest = $this->getDiffusionRequest();
    $branch = $drequest->loadBranch();

    if ($branch && $branch->getLintCommit()) {
      $navs['lint'] = 'Lint View';
    }

    $selected_href = null;
    foreach ($navs as $action => $name) {
      $href = $drequest->generateURI(
        array(
          'action' => $action,
        ));
      if ($action == $selected) {
        $selected_href = $href;
      }

      $nav->addFilter($href, $name, $href);
    }
    $nav->selectFilter($selected_href, null);

    // TODO: URI encoding might need to be sorted out for this link.

    $nav->addFilter(
      '',
      "Search Owners \xE2\x86\x97",
      '/owners/view/search/'.
        '?repository='.phutil_escape_uri($drequest->getCallsign()).
        '&path='.phutil_escape_uri('/'.$drequest->getPath()));

    return $nav;
  }

  public function buildCrumbs(array $spec = array()) {
    $crumbs = $this->buildApplicationCrumbs();
    $crumb_list = $this->buildCrumbList($spec);
    foreach ($crumb_list as $crumb) {
      $crumbs->addCrumb($crumb);
    }
    return $crumbs;
  }

  protected function buildOpenRevisions() {
    $user = $this->getRequest()->getUser();

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
      ->setFields(DifferentialRevisionListView::getDefaultFields($user))
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

    if (!$repository) {
      return $crumb_list;
    }

    $callsign = $repository->getCallsign();
    $repository_name = 'r'.$callsign;

    if (!$spec['commit'] && !$spec['tags'] && !$spec['branches']) {
      $branch_name = $drequest->getBranch();
      if ($branch_name) {
        $repository_name .= ' ('.$branch_name.')';
      }
    }

    $crumb = id(new PhabricatorCrumbView())
      ->setName($repository_name);
    if (!$spec['view'] && !$spec['commit'] &&
        !$spec['tags'] && !$spec['branches']) {
      $crumb_list[] = $crumb;
      return $crumb_list;
    }
    $crumb->setHref("/diffusion/{$callsign}/");
    $crumb_list[] = $crumb;

    $raw_commit = $drequest->getRawCommit();

    if ($spec['tags']) {
      $crumb = new PhabricatorCrumbView();
      if ($spec['commit']) {
        $crumb->setName(
          "Tags for r{$callsign}{$raw_commit}");
        $crumb->setHref($drequest->generateURI(
          array(
            'action' => 'commit',
            'commit' => $raw_commit,
          )));
      } else {
        $crumb->setName('Tags');
      }
      $crumb_list[] = $crumb;
      return $crumb_list;
    }

    if ($spec['branches']) {
      $crumb = id(new PhabricatorCrumbView())
        ->setName('Branches');
      $crumb_list[] = $crumb;
      return $crumb_list;
    }

    if ($spec['commit']) {
      $crumb = id(new PhabricatorCrumbView())
        ->setName("r{$callsign}{$raw_commit}")
        ->setHref("r{$callsign}{$raw_commit}");
      $crumb_list[] = $crumb;
      return $crumb_list;
    }

    $crumb = new PhabricatorCrumbView();
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
      case 'lint':
        $view_name = 'Lint';
        break;
      case 'change':
        $view_name = 'Change';
        $crumb_list[] = $crumb->setName(
          hsprintf('%s (%s)', $path, $commit_link));
        return $crumb_list;
    }

    $uri_params = array(
      'action' => $view,
    );

    $crumb = id(new PhabricatorCrumbView())
      ->setName($view_name);
    if (!strlen($path)) {
      $crumb_list[] = $crumb;
    } else {

      $crumb->setHref($drequest->generateURI(
        array(
          'path' => '',
        ) + $uri_params));
      $crumb_list[] = $crumb;

      $path_parts = explode('/', $path);
      do {
        $last = array_pop($path_parts);
      } while ($last == '');

      $path_sections = array();
      $thus_far = '';
      foreach ($path_parts as $path_part) {
        $thus_far .= $path_part.'/';
        $path_sections[] = '/';
        $path_sections[] = phutil_tag(
          'a',
          array(
            'href' => $drequest->generateURI(
              array(
                'path' => $thus_far,
              ) + $uri_params),
          ),
          $path_part);
      }

      $path_sections[] = '/'.$last;

      $crumb_list[] = id(new PhabricatorCrumbView())
        ->setName($path_sections);
    }

    $last_crumb = array_pop($crumb_list);

    if ($raw_commit) {
      $jump_link = phutil_tag(
        'a',
        array(
          'href' => $drequest->generateURI(
            array(
              'commit' => '',
            ) + $uri_params),
        ),
        'Jump to HEAD');

      $name = $last_crumb->getName();
      $name = hsprintf('%s @ %s (%s)', $name, $commit_link, $jump_link);
      $last_crumb->setName($name);
    } else if ($spec['view'] != 'lint') {
      $name = $last_crumb->getName();
      $name = hsprintf('%s @ HEAD', $name);
      $last_crumb->setName($name);
    }

    $crumb_list[] = $last_crumb;

    return $crumb_list;
  }

  protected function callConduitWithDiffusionRequest(
    $method,
    array $params = array()) {

    $user = $this->getRequest()->getUser();
    $drequest = $this->getDiffusionRequest();

    return DiffusionQuery::callConduitWithDiffusionRequest(
      $user,
      $drequest,
      $method,
      $params);
  }
}
