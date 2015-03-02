<?php

abstract class DiffusionController extends PhabricatorController {

  protected $diffusionRequest;

  public function setDiffusionRequest(DiffusionRequest $request) {
    $this->diffusionRequest = $request;
    return $this;
  }

  protected function getDiffusionRequest() {
    if (!$this->diffusionRequest) {
      throw new Exception('No Diffusion request object!');
    }
    return $this->diffusionRequest;
  }

  public function willBeginExecution() {
    $request = $this->getRequest();

    // Check if this is a VCS request, e.g. from "git clone", "hg clone", or
    // "svn checkout". If it is, we jump off into repository serving code to
    // process the request.
    if (DiffusionServeController::isVCSRequest($request)) {
      $serve_controller = id(new DiffusionServeController())
        ->setCurrentApplication($this->getCurrentApplication());
      return $this->delegateToController($serve_controller);
    }

    return parent::willBeginExecution();
  }

  protected function shouldLoadDiffusionRequest() {
    return true;
  }

  final public function handleRequest(AphrontRequest $request) {
    if ($request->getURIData('callsign') &&
        $this->shouldLoadDiffusionRequest()) {
      try {
      $drequest = DiffusionRequest::newFromAphrontRequestDictionary(
        $request->getURIMap(),
        $request);
      } catch (Exception $ex) {
        return id(new Aphront404Response())
          ->setRequest($request);
      }
      $this->setDiffusionRequest($drequest);
    }
    return $this->processDiffusionRequest($request);
  }

  abstract protected function processDiffusionRequest(AphrontRequest $request);

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
    $repository_name = $repository->getName();

    if (!$spec['commit'] && !$spec['tags'] && !$spec['branches']) {
      $branch_name = $drequest->getBranch();
      if ($branch_name) {
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

    if ($spec['tags']) {
      $crumb = new PHUICrumbView();
      if ($spec['commit']) {
        $crumb->setName(
          pht('Tags for %s', 'r'.$callsign.$stable_commit));
        $crumb->setHref($drequest->generateURI(
          array(
            'action' => 'commit',
            'commit' => $drequest->getStableCommit(),
          )));
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
        ->setName("r{$callsign}{$stable_commit}")
        ->setHref("r{$callsign}{$stable_commit}");
      $crumb_list[] = $crumb;
      return $crumb_list;
    }

    $crumb = new PHUICrumbView();
    $view = $spec['view'];

    switch ($view) {
      case 'history':
        $view_name = pht('History');
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

  protected function getRepositoryControllerURI(
    PhabricatorRepository $repository,
    $path) {
    return $this->getApplicationURI($repository->getCallsign().'/'.$path);
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
        'r'.$drequest->getRepository()->getCallsign());
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
      $links[] = 'r'.$drequest->getRepository()->getCallsign();
      $links[] = $divider;
    }

    return $links;
  }

  protected function renderStatusMessage($title, $body) {
    return id(new PHUIInfoView())
      ->setSeverity(PHUIInfoView::SEVERITY_WARNING)
      ->setTitle($title)
      ->appendChild($body);
  }

}
