<?php

final class DiffusionServeController extends DiffusionController {

  protected function shouldLoadDiffusionRequest() {
    return false;
  }

  public static function isVCSRequest(AphrontRequest $request) {
    if (!self::getCallsign($request)) {
      return null;
    }

    $content_type = $request->getHTTPHeader('Content-Type');
    $user_agent = idx($_SERVER, 'HTTP_USER_AGENT');

    $vcs = null;
    if ($request->getExists('service')) {
      $service = $request->getStr('service');
      // We get this initially for `info/refs`.
      // Git also gives us a User-Agent like "git/1.8.2.3".
      $vcs = PhabricatorRepositoryType::REPOSITORY_TYPE_GIT;
    } else if (strncmp($user_agent, 'git/', 4) === 0) {
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

    return $vcs;
  }

  private static function getCallsign(AphrontRequest $request) {
    $uri = $request->getRequestURI();

    $regex = '@^/diffusion/(?P<callsign>[A-Z]+)(/|$)@';
    $matches = null;
    if (!preg_match($regex, (string)$uri, $matches)) {
      return null;
    }

    return $matches['callsign'];
  }

  protected function processDiffusionRequest(AphrontRequest $request) {
    $callsign = self::getCallsign($request);

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
            DiffusionPushCapability::CAPABILITY);
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

    $vcs_type = $repository->getVersionControlSystem();
    $req_type = $this->isVCSRequest($request);

    if ($vcs_type != $req_type) {
      switch ($req_type) {
        case PhabricatorRepositoryType::REPOSITORY_TYPE_GIT:
          $result = new PhabricatorVCSResponse(
            500,
            pht('This is not a Git repository.'));
          break;
        case PhabricatorRepositoryType::REPOSITORY_TYPE_MERCURIAL:
          $result = new PhabricatorVCSResponse(
            500,
            pht('This is not a Mercurial repository.'));
          break;
        case PhabricatorRepositoryType::REPOSITORY_TYPE_SVN:
          $result = new PhabricatorVCSResponse(
            500,
            pht('This is not a Subversion repository.'));
          break;
        default:
          $result = new PhabricatorVCSResponse(
            500,
            pht('Unknown request type.'));
          break;
      }
    } else {
      switch ($vcs_type) {
        case PhabricatorRepositoryType::REPOSITORY_TYPE_GIT:
        case PhabricatorRepositoryType::REPOSITORY_TYPE_MERCURIAL:
          $result = $this->serveVCSRequest($repository, $viewer);
          break;
        case PhabricatorRepositoryType::REPOSITORY_TYPE_SVN:
          $result = new PhabricatorVCSResponse(
            500,
            pht(
              'Phabricator does not support HTTP access to Subversion '.
              'repositories.'));
          break;
        default:
          $result = new PhabricatorVCSResponse(
            500,
            pht('Unknown version control system.'));
          break;
      }
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

  private function serveVCSRequest(
    PhabricatorRepository $repository,
    PhabricatorUser $viewer) {

    // If this repository is hosted on a service, we need to proxy the request
    // to a host which can serve it.
    $is_cluster_request = $this->getRequest()->isProxiedClusterRequest();

    $uri = $repository->getAlmanacServiceURI(
      $viewer,
      $is_cluster_request,
      array(
        'http',
        'https',
      ));
    if ($uri) {
      $future = $this->getRequest()->newClusterProxyFuture($uri);
      return id(new AphrontHTTPProxyResponse())
        ->setHTTPFuture($future);
    }

    // Otherwise, we're going to handle the request locally.

    $vcs_type = $repository->getVersionControlSystem();
    switch ($vcs_type) {
      case PhabricatorRepositoryType::REPOSITORY_TYPE_GIT:
        $result = $this->serveGitRequest($repository, $viewer);
        break;
      case PhabricatorRepositoryType::REPOSITORY_TYPE_MERCURIAL:
        $result = $this->serveMercurialRequest($repository, $viewer);
        break;
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
        $path = $this->getRequestDirectoryPath($repository);
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
        if ($cmd == 'batch') {
          $cmds = idx($this->getMercurialArguments(), 'cmds');
          return DiffusionMercurialWireProtocol::isReadOnlyBatchCommand($cmds);
        }
        return DiffusionMercurialWireProtocol::isReadOnlyCommand($cmd);
      case PhabricatorRepositoryType::REPOSITORY_TYPE_SVN:
        break;
    }

    return false;
  }

  /**
   * @phutil-external-symbol class PhabricatorStartup
   */
  private function serveGitRequest(
    PhabricatorRepository $repository,
    PhabricatorUser $viewer) {
    $request = $this->getRequest();

    $request_path = $this->getRequestDirectoryPath($repository);
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
      throw new Exception(
        pht(
          'Unable to find `%s` in %s!',
          'git-http-backend',
          '$PATH'));
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
    ) + $this->getCommonEnvironment($viewer);

    $input = PhabricatorStartup::getRawInput();

    $command = csprintf('%s', $bin);
    $command = PhabricatorDaemon::sudoCommandAsDaemonUser($command);

    list($err, $stdout, $stderr) = id(new ExecFuture('%C', $command))
      ->setEnv($env, true)
      ->write($input)
      ->resolve();

    if ($err) {
      if ($this->isValidGitShallowCloneResponse($stdout, $stderr)) {
        // Ignore the error if the response passes this special check for
        // validity.
        $err = 0;
      }
    }

    if ($err) {
      return new PhabricatorVCSResponse(
        500,
        pht('Error %d: %s', $err, $stderr));
    }

    return id(new DiffusionGitResponse())->setGitData($stdout);
  }

  private function getRequestDirectoryPath(PhabricatorRepository $repository) {
    $request = $this->getRequest();
    $request_path = $request->getRequestURI()->getPath();
    $base_path = preg_replace('@^/diffusion/[A-Z]+@', '', $request_path);

    // For Git repositories, strip an optional directory component if it
    // isn't the name of a known Git resource. This allows users to clone
    // repositories as "/diffusion/X/anything.git", for example.
    if ($repository->isGit()) {
      $known = array(
        'info',
        'git-upload-pack',
        'git-receive-pack',
      );

      foreach ($known as $key => $path) {
        $known[$key] = preg_quote($path, '@');
      }

      $known = implode('|', $known);

      if (preg_match('@^/([^/]+)/('.$known.')(/|$)@', $base_path)) {
        $base_path = preg_replace('@^/([^/]+)@', '', $base_path);
      }
    }

    return $base_path;
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

    if (!$user->isUserActivated()) {
      // User is not activated.
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

    // If the user's password is stored using a less-than-optimal hash, upgrade
    // them to the strongest available hash.

    $hash_envelope = new PhutilOpaqueEnvelope(
      $password_entry->getPasswordHash());
    if (PhabricatorPasswordHasher::canUpgradeHash($hash_envelope)) {
      $password_entry->setPassword($password, $user);
      $unguarded = AphrontWriteGuard::beginScopedUnguardedWrites();
        $password_entry->save();
      unset($unguarded);
    }

    return $user;
  }

  private function serveMercurialRequest(
    PhabricatorRepository $repository,
    PhabricatorUser $viewer) {
    $request = $this->getRequest();

    $bin = Filesystem::resolveBinary('hg');
    if (!$bin) {
      throw new Exception(
        pht(
          'Unable to find `%s` in %s!',
          'hg',
          '$PATH'));
    }

    $env = $this->getCommonEnvironment($viewer);
    $input = PhabricatorStartup::getRawInput();

    $cmd = $request->getStr('cmd');

    $args = $this->getMercurialArguments();
    $args = $this->formatMercurialArguments($cmd, $args);

    if (strlen($input)) {
      $input = strlen($input)."\n".$input."0\n";
    }

    $command = csprintf('%s serve --stdio', $bin);
    $command = PhabricatorDaemon::sudoCommandAsDaemonUser($command);

    list($err, $stdout, $stderr) = id(new ExecFuture('%C', $command))
      ->setEnv($env, true)
      ->setCWD($repository->getLocalPath())
      ->write("{$cmd}\n{$args}{$input}")
      ->resolve();

    if ($err) {
      return new PhabricatorVCSResponse(
        500,
        pht('Error %d: %s', $err, $stderr));
    }

    if ($cmd == 'getbundle' ||
        $cmd == 'changegroup' ||
        $cmd == 'changegroupsubset') {
      // We're not completely sure that "changegroup" and "changegroupsubset"
      // actually work, they're for very old Mercurial.
      $body = gzcompress($stdout);
    } else if ($cmd == 'unbundle') {
      // This includes diagnostic information and anything echoed by commit
      // hooks. We ignore `stdout` since it just has protocol garbage, and
      // substitute `stderr`.
      $body = strlen($stderr)."\n".$stderr;
    } else {
      list($length, $body) = explode("\n", $stdout, 2);
    }

    return id(new DiffusionMercurialResponse())->setContent($body);
  }


  private function getMercurialArguments() {
    // Mercurial sends arguments in HTTP headers. "Why?", you might wonder,
    // "Why would you do this?".

    $args_raw = array();
    for ($ii = 1;; $ii++) {
      $header = 'HTTP_X_HGARG_'.$ii;
      if (!array_key_exists($header, $_SERVER)) {
        break;
      }
      $args_raw[] = $_SERVER[$header];
    }
    $args_raw = implode('', $args_raw);

    return id(new PhutilQueryStringParser())
      ->parseQueryString($args_raw);
  }

  private function formatMercurialArguments($command, array $arguments) {
    $spec = DiffusionMercurialWireProtocol::getCommandArgs($command);

    $out = array();

    // Mercurial takes normal arguments like this:
    //
    //   name <length(value)>
    //   value

    $has_star = false;
    foreach ($spec as $arg_key) {
      if ($arg_key == '*') {
        $has_star = true;
        continue;
      }
      if (isset($arguments[$arg_key])) {
        $value = $arguments[$arg_key];
        $size = strlen($value);
        $out[] = "{$arg_key} {$size}\n{$value}";
        unset($arguments[$arg_key]);
      }
    }

    if ($has_star) {

      // Mercurial takes arguments for variable argument lists roughly like
      // this:
      //
      //   * <count(args)>
      //   argname1 <length(argvalue1)>
      //   argvalue1
      //   argname2 <length(argvalue2)>
      //   argvalue2

      $count = count($arguments);

      $out[] = "* {$count}\n";

      foreach ($arguments as $key => $value) {
        if (in_array($key, $spec)) {
          // We already added this argument above, so skip it.
          continue;
        }
        $size = strlen($value);
        $out[] = "{$key} {$size}\n{$value}";
      }
    }

    return implode('', $out);
  }

  private function isValidGitShallowCloneResponse($stdout, $stderr) {
    // If you execute `git clone --depth N ...`, git sends a request which
    // `git-http-backend` responds to by emitting valid output and then exiting
    // with a failure code and an error message. If we ignore this error,
    // everything works.

    // This is a pretty funky fix: it would be nice to more precisely detect
    // that a request is a `--depth N` clone request, but we don't have any code
    // to decode protocol frames yet. Instead, look for reasonable evidence
    // in the error and output that we're looking at a `--depth` clone.

    // For evidence this isn't completely crazy, see:
    // https://github.com/schacon/grack/pull/7

    $stdout_regexp = '(^Content-Type: application/x-git-upload-pack-result)m';
    $stderr_regexp = '(The remote end hung up unexpectedly)';

    $has_pack = preg_match($stdout_regexp, $stdout);
    $is_hangup = preg_match($stderr_regexp, $stderr);

    return $has_pack && $is_hangup;
  }

  private function getCommonEnvironment(PhabricatorUser $viewer) {
    $remote_addr = $this->getRequest()->getRemoteAddr();

    return array(
      DiffusionCommitHookEngine::ENV_USER => $viewer->getUsername(),
      DiffusionCommitHookEngine::ENV_REMOTE_ADDRESS => $remote_addr,
      DiffusionCommitHookEngine::ENV_REMOTE_PROTOCOL => 'http',
    );
  }

}
