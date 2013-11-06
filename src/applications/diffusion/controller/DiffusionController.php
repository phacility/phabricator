<?php

abstract class DiffusionController extends PhabricatorController {

  protected $diffusionRequest;

  public function willBeginExecution() {
    $request = $this->getRequest();
    $uri = $request->getRequestURI();

    $user_agent = idx($_SERVER, 'HTTP_USER_AGENT');

    // Check if this is a VCS request, e.g. from "git clone", "hg clone", or
    // "svn checkout". If it is, we jump off into repository serving code to
    // process the request.

    $regex = '@^/diffusion/(?P<callsign>[A-Z]+)(/|$)@';
    $matches = null;
    if (preg_match($regex, (string)$uri, $matches)) {
      $vcs = null;

      $content_type = $request->getHTTPHeader('Content-Type');

      if ($request->getExists('__vcs__')) {
        // This is magic to make it easier for us to debug stuff by telling
        // users to run:
        //
        //   curl http://example.phabricator.com/diffusion/X/?__vcs__=1
        //
        // ...to get a human-readable error.
        $vcs = $request->getExists('__vcs__');
      } else if (strncmp($user_agent, "git/", 4) === 0) {
        $vcs = PhabricatorRepositoryType::REPOSITORY_TYPE_GIT;
      } else if ($request->getExists('service')) {
        $service = $request->getStr('service');
        // We get this initially for `info/refs`.
        // Git also gives us a User-Agent like "git/1.8.2.3".
        $vcs = PhabricatorRepositoryType::REPOSITORY_TYPE_GIT;
      } else if ($content_type == 'application/x-git-upload-pack-request') {
        // We get this for `git-upload-pack`.
        $vcs = PhabricatorRepositoryType::REPOSITORY_TYPE_GIT;
      } else if ($content_type == 'application/x-git-receive-pack-request') {
        // We get this for `git-receive-pack`.
        $vcs = PhabricatorRepositoryType::REPOSITORY_TYPE_GIT;
      } else if ($request->getExists('cmd')) {
        // Mercurial also sends an Accept header like
        // "application/mercurial-0.1", and a User-Agent like
        // "mercurial/proto-1.0".
        $vcs = PhabricatorRepositoryType::REPOSITORY_TYPE_MERCURIAL;
      } else {
        // Subversion also sends an initial OPTIONS request (vs GET/POST), and
        // has a User-Agent like "SVN/1.8.3 (x86_64-apple-darwin11.4.2)
        // serf/1.3.2".
        $dav = $request->getHTTPHeader('DAV');
        $dav = new PhutilURI($dav);
        if ($dav->getDomain() === 'subversion.tigris.org') {
          $vcs = PhabricatorRepositoryType::REPOSITORY_TYPE_SVN;
        }
      }

      if ($vcs) {
        return $this->processVCSRequest($matches['callsign']);
      }
    }

    parent::willBeginExecution();
  }

  private function processVCSRequest($callsign) {

    // If authentication credentials have been provided, try to find a user
    // that actually matches those credentials.
    if (isset($_SERVER['PHP_AUTH_USER']) && isset($_SERVER['PHP_AUTH_PW'])) {
      $username = $_SERVER['PHP_AUTH_USER'];
      $password = new PhutilOpaqueEnvelope($_SERVER['PHP_AUTH_PW']);

      $viewer = $this->authenticateHTTPRepositoryUser($username, $password);
      if (!$viewer) {
        return new PhabricatorVCSResponse(
          403,
          pht('Invalid credentials.'));
      }
    } else {
      // User hasn't provided credentials, which means we count them as
      // being "not logged in".
      $viewer = new PhabricatorUser();
    }

    $allow_public = PhabricatorEnv::getEnvConfig('policy.allow-public');
    $allow_auth = PhabricatorEnv::getEnvConfig('diffusion.allow-http-auth');
    if (!$allow_public) {
      if (!$viewer->isLoggedIn()) {
        if ($allow_auth) {
          return new PhabricatorVCSResponse(
            401,
            pht('You must log in to access repositories.'));
        } else {
          return new PhabricatorVCSResponse(
            403,
            pht('Public and authenticated HTTP access are both forbidden.'));
        }
      }
    }

    try {
      $repository = id(new PhabricatorRepositoryQuery())
        ->setViewer($viewer)
        ->withCallsigns(array($callsign))
        ->executeOne();
      if (!$repository) {
        return new PhabricatorVCSResponse(
          404,
          pht('No such repository exists.'));
      }
    } catch (PhabricatorPolicyException $ex) {
      if ($viewer->isLoggedIn()) {
        return new PhabricatorVCSResponse(
          403,
          pht('You do not have permission to access this repository.'));
      } else {
        if ($allow_auth) {
          return new PhabricatorVCSResponse(
            401,
            pht('You must log in to access this repository.'));
        } else {
          return new PhabricatorVCSResponse(
            403,
            pht(
              'This repository requires authentication, which is forbidden '.
              'over HTTP.'));
        }
      }
    }

    if (!$repository->isTracked()) {
      return new PhabricatorVCSResponse(
        403,
        pht('This repository is inactive.'));
    }

    $is_push = !$this->isReadOnlyRequest($repository);

    switch ($repository->getServeOverHTTP()) {
      case PhabricatorRepository::SERVE_READONLY:
        if ($is_push) {
          return new PhabricatorVCSResponse(
            403,
            pht('This repository is read-only over HTTP.'));
        }
        break;
      case PhabricatorRepository::SERVE_READWRITE:
        if ($is_push) {
          $can_push = PhabricatorPolicyFilter::hasCapability(
            $viewer,
            $repository,
            DiffusionCapabilityPush::CAPABILITY);
          if (!$can_push) {
            if ($viewer->isLoggedIn()) {
              return new PhabricatorVCSResponse(
                403,
                pht('You do not have permission to push to this repository.'));
            } else {
              if ($allow_auth) {
                return new PhabricatorVCSResponse(
                  401,
                  pht('You must log in to push to this repository.'));
              } else {
                return new PhabricatorVCSResponse(
                  403,
                  pht(
                    'Pushing to this repository requires authentication, '.
                    'which is forbidden over HTTP.'));
              }
            }
          }
        }
        break;
      case PhabricatorRepository::SERVE_OFF:
      default:
        return new PhabricatorVCSResponse(
          403,
          pht('This repository is not available over HTTP.'));
    }

    switch ($repository->getVersionControlSystem()) {
      case PhabricatorRepositoryType::REPOSITORY_TYPE_GIT:
        $result = $this->serveGitRequest($repository, $viewer);
        break;
      default:
        $result = new PhabricatorVCSResponse(
          999,
          pht('TODO: Implement meaningful responses.'));
        break;
    }

    $code = $result->getHTTPResponseCode();

    if ($is_push && ($code == 200)) {
      $unguarded = AphrontWriteGuard::beginScopedUnguardedWrites();
        $repository->writeStatusMessage(
          PhabricatorRepositoryStatusMessage::TYPE_NEEDS_UPDATE,
          PhabricatorRepositoryStatusMessage::CODE_OKAY);
      unset($unguarded);
    }

    return $result;
  }

  private function isReadOnlyRequest(
    PhabricatorRepository $repository) {
    $request = $this->getRequest();
    $method = $_SERVER['REQUEST_METHOD'];

    // TODO: This implementation is safe by default, but very incomplete.

    switch ($repository->getVersionControlSystem()) {
      case PhabricatorRepositoryType::REPOSITORY_TYPE_GIT:
        $service = $request->getStr('service');
        $path = $this->getRequestDirectoryPath();
        // NOTE: Service names are the reverse of what you might expect, as they
        // are from the point of view of the server. The main read service is
        // "git-upload-pack", and the main write service is "git-receive-pack".

        if ($method == 'GET' &&
            $path == '/info/refs' &&
            $service == 'git-upload-pack') {
          return true;
        }

        if ($path == '/git-upload-pack') {
          return true;
        }

        break;
      case PhabricatorRepositoryType::REPOSITORY_TYPE_MERCURIAL:
        $cmd = $request->getStr('cmd');
        switch ($cmd) {
          case 'capabilities':
            return true;
          default:
            return false;
        }
        break;
      case PhabricatorRepositoryType::REPOSITORY_TYPE_SUBVERSION:
        break;
    }

    return false;
  }

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
    $crumb->setHref(
      $drequest->generateURI(
        array(
          'action' => 'branch',
          'path' => '/',
        )));
    $crumb_list[] = $crumb;

    $raw_commit = $drequest->getRawCommit();

    if ($spec['tags']) {
      $crumb = new PhabricatorCrumbView();
      if ($spec['commit']) {
        $crumb->setName(
          pht("Tags for %s", 'r'.$callsign.$raw_commit));
        $crumb->setHref($drequest->generateURI(
          array(
            'action' => 'commit',
            'commit' => $raw_commit,
          )));
      } else {
        $crumb->setName(pht('Tags'));
      }
      $crumb_list[] = $crumb;
      return $crumb_list;
    }

    if ($spec['branches']) {
      $crumb = id(new PhabricatorCrumbView())
        ->setName(pht('Branches'));
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

    $crumb = id(new PhabricatorCrumbView())
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
        'class' => 'phui-header-divider'),
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

  /**
   * @phutil-external-symbol class PhabricatorStartup
   */
  private function serveGitRequest(
    PhabricatorRepository $repository,
    PhabricatorUser $viewer) {
    $request = $this->getRequest();

    $request_path = $this->getRequestDirectoryPath();
    $repository_root = $repository->getLocalPath();

    // Rebuild the query string to strip `__magic__` parameters and prevent
    // issues where we might interpret inputs like "service=read&service=write"
    // differently than the server does and pass it an unsafe command.

    // NOTE: This does not use getPassthroughRequestParameters() because
    // that code is HTTP-method agnostic and will encode POST data.

    $query_data = $_GET;
    foreach ($query_data as $key => $value) {
      if (!strncmp($key, '__', 2)) {
        unset($query_data[$key]);
      }
    }
    $query_string = http_build_query($query_data, '', '&');

    // We're about to wipe out PATH with the rest of the environment, so
    // resolve the binary first.
    $bin = Filesystem::resolveBinary('git-http-backend');
    if (!$bin) {
      throw new Exception("Unable to find `git-http-backend` in PATH!");
    }

    $env = array(
      'REQUEST_METHOD' => $_SERVER['REQUEST_METHOD'],
      'QUERY_STRING' => $query_string,
      'CONTENT_TYPE' => $request->getHTTPHeader('Content-Type'),
      'HTTP_CONTENT_ENCODING' => $request->getHTTPHeader('Content-Encoding'),
      'REMOTE_ADDR' => $_SERVER['REMOTE_ADDR'],
      'GIT_PROJECT_ROOT' => $repository_root,
      'GIT_HTTP_EXPORT_ALL' => '1',
      'PATH_INFO' => $request_path,

      'REMOTE_USER' => $viewer->getUsername(),

      // TODO: Set these correctly.
      // GIT_COMMITTER_NAME
      // GIT_COMMITTER_EMAIL
    );

    $input = PhabricatorStartup::getRawInput();

    list($err, $stdout, $stderr) = id(new ExecFuture('%s', $bin))
      ->setEnv($env, true)
      ->write($input)
      ->resolve();

    if ($err) {
      return new PhabricatorVCSResponse(
        500,
        pht('Error %d: %s', $err, $stderr));
    }

    return id(new DiffusionGitResponse())->setGitData($stdout);
  }

  private function getRequestDirectoryPath() {
    $request = $this->getRequest();
    $request_path = $request->getRequestURI()->getPath();
    return preg_replace('@^/diffusion/[A-Z]+@', '', $request_path);
  }

  protected function renderStatusMessage($title, $body) {
    return id(new AphrontErrorView())
      ->setSeverity(AphrontErrorView::SEVERITY_WARNING)
      ->setTitle($title)
      ->appendChild($body);
  }

  private function authenticateHTTPRepositoryUser(
    $username,
    PhutilOpaqueEnvelope $password) {

    if (!PhabricatorEnv::getEnvConfig('diffusion.allow-http-auth')) {
      // No HTTP auth permitted.
      return null;
    }

    if (!strlen($username)) {
      // No username.
      return null;
    }

    if (!strlen($password->openEnvelope())) {
      // No password.
      return null;
    }

    $user = id(new PhabricatorPeopleQuery())
      ->setViewer(PhabricatorUser::getOmnipotentUser())
      ->withUsernames(array($username))
      ->executeOne();
    if (!$user) {
      // Username doesn't match anything.
      return null;
    }

    $password_entry = id(new PhabricatorRepositoryVCSPassword())
      ->loadOneWhere('userPHID = %s', $user->getPHID());
    if (!$password_entry) {
      // User doesn't have a password set.
      return null;
    }

    if (!$password_entry->comparePassword($password, $user)) {
      // Password doesn't match.
      return null;
    }

    if ($user->getIsDisabled()) {
      // User is disabled.
      return null;
    }

    return $user;
  }
}

