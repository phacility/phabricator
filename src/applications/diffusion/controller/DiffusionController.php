<?php

abstract class DiffusionController extends PhabricatorController {

  private $diffusionRequest;

  protected function getDiffusionRequest() {
    if (!$this->diffusionRequest) {
      throw new PhutilInvalidStateException('loadDiffusionContext');
    }
    return $this->diffusionRequest;
  }

  protected function hasDiffusionRequest() {
    return (bool)$this->diffusionRequest;
  }

  public function willBeginExecution() {
    $request = $this->getRequest();

    // Check if this is a VCS request, e.g. from "git clone", "hg clone", or
    // "svn checkout". If it is, we jump off into repository serving code to
    // process the request.

    $serve_controller = new DiffusionServeController();
    if ($serve_controller->isVCSRequest($request)) {
      return $this->delegateToController($serve_controller);
    }

    return parent::willBeginExecution();
  }

  protected function loadDiffusionContextForEdit() {
    return $this->loadContext(
      array(
        'edit' => true,
      ));
  }

  protected function loadDiffusionContext() {
    return $this->loadContext(array());
  }

  private function loadContext(array $options) {
    $request = $this->getRequest();
    $viewer = $this->getViewer();
    require_celerity_resource('diffusion-repository-css');

    $identifier = $this->getRepositoryIdentifierFromRequest($request);

    $params = $options + array(
      'repository' => $identifier,
      'user' => $viewer,
      'blob' => $this->getDiffusionBlobFromRequest($request),
      'commit' => $request->getURIData('commit'),
      'path' => $request->getURIData('path'),
      'line' => $request->getURIData('line'),
      'branch' => $request->getURIData('branch'),
      'lint' => $request->getStr('lint'),
    );

    $drequest = DiffusionRequest::newFromDictionary($params);

    if (!$drequest) {
      return new Aphront404Response();
    }

    // If the client is making a request like "/diffusion/1/...", but the
    // repository has a different canonical path like "/diffusion/XYZ/...",
    // redirect them to the canonical path.

    $request_path = $request->getPath();
    $repository = $drequest->getRepository();

    $canonical_path = $repository->getCanonicalPath($request_path);
    if ($canonical_path !== null) {
      if ($canonical_path != $request_path) {
        return id(new AphrontRedirectResponse())->setURI($canonical_path);
      }
    }

    $this->diffusionRequest = $drequest;

    return null;
  }

  protected function getDiffusionBlobFromRequest(AphrontRequest $request) {
    return $request->getURIData('dblob');
  }

  protected function getRepositoryIdentifierFromRequest(
    AphrontRequest $request) {

    $short_name = $request->getURIData('repositoryShortName');
    if (strlen($short_name)) {
      // If the short name ends in ".git", ignore it.
      $short_name = preg_replace('/\\.git\z/', '', $short_name);
      return $short_name;
    }

    $identifier = $request->getURIData('repositoryCallsign');
    if (strlen($identifier)) {
      return $identifier;
    }

    $id = $request->getURIData('repositoryID');
    if (strlen($id)) {
      return (int)$id;
    }

    return null;
  }

  public function buildCrumbs(array $spec = array()) {
    $crumbs = $this->buildApplicationCrumbs();
    $crumb_list = $this->buildCrumbList($spec);
    foreach ($crumb_list as $crumb) {
      $crumbs->addCrumb($crumb);
    }
    return $crumbs;
  }

  private function buildCrumbList(array $spec = array()) {

    $spec = $spec + array(
      'commit' => null,
      'tags' => null,
      'branches' => null,
      'view' => null,
    );

    $crumb_list = array();

    // On the home page, we don't have a DiffusionRequest.
    if ($this->hasDiffusionRequest()) {
      $drequest = $this->getDiffusionRequest();
      $repository = $drequest->getRepository();
    } else {
      $drequest = null;
      $repository = null;
    }

    if (!$repository) {
      return $crumb_list;
    }

    $repository_name = $repository->getName();

    if (!$spec['commit'] && !$spec['tags'] && !$spec['branches']) {
      $branch_name = $drequest->getBranch();
      if (strlen($branch_name)) {
        $repository_name .= ' ('.$branch_name.')';
      }
    }

    $crumb = id(new PHUICrumbView())
      ->setName($repository_name);
    if (!$spec['view'] && !$spec['commit'] &&
        !$spec['tags'] && !$spec['branches']) {
      $crumb_list[] = $crumb;
      return $crumb_list;
    }
    $crumb->setHref(
      $drequest->generateURI(
        array(
          'action' => 'branch',
          'path' => '/',
        )));
    $crumb_list[] = $crumb;

    $stable_commit = $drequest->getStableCommit();
    $commit_name = $repository->formatCommitName($stable_commit, $local = true);
    $commit_uri = $repository->getCommitURI($stable_commit);

    if ($spec['tags']) {
      $crumb = new PHUICrumbView();
      if ($spec['commit']) {
        $crumb->setName(pht('Tags for %s', $commit_name));
        $crumb->setHref($commit_uri);
      } else {
        $crumb->setName(pht('Tags'));
      }
      $crumb_list[] = $crumb;
      return $crumb_list;
    }

    if ($spec['branches']) {
      $crumb = id(new PHUICrumbView())
        ->setName(pht('Branches'));
      $crumb_list[] = $crumb;
      return $crumb_list;
    }

    if ($spec['commit']) {
      $crumb = id(new PHUICrumbView())
        ->setName($commit_name);
      $crumb_list[] = $crumb;
      return $crumb_list;
    }

    $crumb = new PHUICrumbView();
    $view = $spec['view'];

    switch ($view) {
      case 'history':
        $view_name = pht('History');
        break;
      case 'graph':
        $view_name = pht('Graph');
        break;
      case 'browse':
        $view_name = pht('Browse');
        break;
      case 'lint':
        $view_name = pht('Lint');
        break;
      case 'change':
        $view_name = pht('Change');
        break;
      case 'compare':
        $view_name = pht('Compare');
        break;
    }

    $crumb = id(new PHUICrumbView())
      ->setName($view_name);

    $crumb_list[] = $crumb;
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

  protected function callConduitMethod($method, array $params = array()) {
    $user = $this->getViewer();
    $drequest = $this->getDiffusionRequest();

    return DiffusionQuery::callConduitWithDiffusionRequest(
      $user,
      $drequest,
      $method,
      $params,
      true);
  }

  protected function getRepositoryControllerURI(
    PhabricatorRepository $repository,
    $path) {
    return $repository->getPathURI($path);
  }

  protected function renderPathLinks(DiffusionRequest $drequest, $action) {
    $path = $drequest->getPath();
    $path_parts = array_filter(explode('/', trim($path, '/')));

    $divider = phutil_tag(
      'span',
      array(
        'class' => 'phui-header-divider',
      ),
      '/');

    $links = array();
    if ($path_parts) {
      $links[] = phutil_tag(
        'a',
        array(
          'href' => $drequest->generateURI(
            array(
              'action' => $action,
              'path' => '',
            )),
        ),
        $drequest->getRepository()->getDisplayName());
      $links[] = $divider;
      $accum = '';
      $last_key = last_key($path_parts);
      foreach ($path_parts as $key => $part) {
        $accum .= '/'.$part;
        if ($key === $last_key) {
          $links[] = $part;
        } else {
          $links[] = phutil_tag(
            'a',
            array(
              'href' => $drequest->generateURI(
                array(
                  'action' => $action,
                  'path' => $accum.'/',
                )),
            ),
            $part);
          $links[] = $divider;
        }
      }
    } else {
      $links[] = $drequest->getRepository()->getDisplayName();
      $links[] = $divider;
    }

    return $links;
  }

  protected function renderStatusMessage($title, $body) {
    return id(new PHUIInfoView())
      ->setSeverity(PHUIInfoView::SEVERITY_NOTICE)
      ->setTitle($title)
      ->setFlush(true)
      ->appendChild($body);
  }

  protected function renderCommitHashTag(DiffusionRequest $drequest) {
    $stable_commit = $drequest->getStableCommit();
    $commit = phutil_tag(
        'a',
        array(
          'href' => $drequest->generateURI(
            array(
              'action' => 'commit',
              'commit' => $stable_commit,
            )),
        ),
      $drequest->getRepository()->formatCommitName($stable_commit, true));

    $tag = id(new PHUITagView())
      ->setName($commit)
      ->setColor(PHUITagView::COLOR_INDIGO)
      ->setBorder(PHUITagView::BORDER_NONE)
      ->setType(PHUITagView::TYPE_SHADE);

    return $tag;
  }

  protected function renderBranchTag(DiffusionRequest $drequest) {
    $branch = $drequest->getBranch();
    $branch = id(new PhutilUTF8StringTruncator())
      ->setMaximumGlyphs(24)
      ->truncateString($branch);

    $tag = id(new PHUITagView())
      ->setName($branch)
      ->setColor(PHUITagView::COLOR_INDIGO)
      ->setBorder(PHUITagView::BORDER_NONE)
      ->setType(PHUITagView::TYPE_OUTLINE)
      ->addClass('diffusion-header-branch-tag');

    return $tag;
  }

  protected function renderSymbolicCommit(DiffusionRequest $drequest) {
    $symbolic_tag = $drequest->getSymbolicCommit();
    $symbolic_tag = id(new PhutilUTF8StringTruncator())
      ->setMaximumGlyphs(24)
      ->truncateString($symbolic_tag);

    $tag = id(new PHUITagView())
      ->setName($symbolic_tag)
      ->setIcon('fa-tag')
      ->setColor(PHUITagView::COLOR_INDIGO)
      ->setBorder(PHUITagView::BORDER_NONE)
      ->setType(PHUITagView::TYPE_SHADE);

    return $tag;
  }

  protected function renderDirectoryReadme(DiffusionBrowseResultSet $browse) {
    $readme_path = $browse->getReadmePath();
    if ($readme_path === null) {
      return null;
    }

    $drequest = $this->getDiffusionRequest();
    $viewer = $this->getViewer();
    $repository = $drequest->getRepository();
    $repository_phid = $repository->getPHID();
    $stable_commit = $drequest->getStableCommit();

    $stable_commit_hash = PhabricatorHash::digestForIndex($stable_commit);
    $readme_path_hash = PhabricatorHash::digestForIndex($readme_path);

    $cache = PhabricatorCaches::getMutableStructureCache();
    $cache_key = "diffusion".
      ".repository({$repository_phid})".
      ".commit({$stable_commit_hash})".
      ".readme({$readme_path_hash})";

    $readme_cache = $cache->getKey($cache_key);
    if (!$readme_cache) {
      try {
        $result = $this->callConduitWithDiffusionRequest(
          'diffusion.filecontentquery',
          array(
            'path' => $readme_path,
            'commit' => $drequest->getStableCommit(),
          ));
      } catch (Exception $ex) {
        return null;
      }

      $file_phid = $result['filePHID'];
      if (!$file_phid) {
        return null;
      }

      $file = id(new PhabricatorFileQuery())
        ->setViewer($viewer)
        ->withPHIDs(array($file_phid))
        ->executeOne();
      if (!$file) {
        return null;
      }

      $corpus = $file->loadFileData();

      $readme_cache = array(
        'corpus' => $corpus,
      );

      $cache->setKey($cache_key, $readme_cache);
    }

    $readme_corpus = $readme_cache['corpus'];
    if (!strlen($readme_corpus)) {
      return null;
    }

    return id(new DiffusionReadmeView())
      ->setUser($this->getViewer())
      ->setPath($readme_path)
      ->setContent($readme_corpus);
  }

  protected function renderSearchForm($path = '/') {
    $drequest = $this->getDiffusionRequest();
    $viewer = $this->getViewer();
    switch ($drequest->getRepository()->getVersionControlSystem()) {
      case PhabricatorRepositoryType::REPOSITORY_TYPE_SVN:
        return null;
    }

    $search_term = $this->getRequest()->getStr('grep');
    require_celerity_resource('diffusion-icons-css');
    require_celerity_resource('diffusion-css');

    $href = $drequest->generateURI(array(
      'action' => 'browse',
      'path' => $path,
    ));

    $bar = javelin_tag(
      'input',
      array(
        'type' => 'text',
        'id' => 'diffusion-search-input',
        'name' => 'grep',
        'class' => 'diffusion-search-input',
        'sigil' => 'diffusion-search-input',
        'placeholder' => pht('Pattern Search'),
        'value' => $search_term,
      ));

    $form = phabricator_form(
      $viewer,
      array(
        'method' => 'GET',
        'action' => $href,
        'sigil' => 'diffusion-search-form',
        'class' => 'diffusion-search-form',
        'id' => 'diffusion-search-form',
      ),
      array(
        $bar,
      ));

    $form_view = phutil_tag(
      'div',
      array(
        'class' => 'diffusion-search-form-view',
      ),
      $form);

    return $form_view;
  }

  protected function buildTabsView($key) {
    $drequest = $this->getDiffusionRequest();
    $repository = $drequest->getRepository();

    $view = new PHUIListView();

    $view->addMenuItem(
      id(new PHUIListItemView())
        ->setKey('code')
        ->setName(pht('Code'))
        ->setIcon('fa-code')
        ->setHref($drequest->generateURI(
          array(
            'action' => 'branch',
            'path' => '/',
          )))
        ->setSelected($key == 'code'));

    if (!$repository->isSVN()) {
      $view->addMenuItem(
        id(new PHUIListItemView())
          ->setKey('branch')
          ->setName(pht('Branches'))
          ->setIcon('fa-code-fork')
          ->setHref($drequest->generateURI(
          array(
            'action' => 'branches',
          )))
          ->setSelected($key == 'branch'));
    }

    if (!$repository->isSVN()) {
      $view->addMenuItem(
        id(new PHUIListItemView())
          ->setKey('tags')
          ->setName(pht('Tags'))
          ->setIcon('fa-tags')
          ->setHref($drequest->generateURI(
          array(
            'action' => 'tags',
          )))
          ->setSelected($key == 'tags'));
    }

    $view->addMenuItem(
      id(new PHUIListItemView())
        ->setKey('history')
        ->setName(pht('History'))
        ->setIcon('fa-history')
        ->setHref($drequest->generateURI(
        array(
          'action' => 'history',
        )))
        ->setSelected($key == 'history'));

    $view->addMenuItem(
      id(new PHUIListItemView())
        ->setKey('graph')
        ->setName(pht('Graph'))
        ->setIcon('fa-code-fork')
        ->setHref($drequest->generateURI(
        array(
          'action' => 'graph',
        )))
        ->setSelected($key == 'graph'));

    return $view;

  }

}
