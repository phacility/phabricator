<?php

final class DiffusionServeController extends DiffusionController {

  private $serviceViewer;
  private $serviceRepository;

  private $isGitLFSRequest;
  private $gitLFSToken;
  private $gitLFSInput;

  public function setServiceViewer(PhabricatorUser $viewer) {
    $this->getRequest()->setUser($viewer);

    $this->serviceViewer = $viewer;
    return $this;
  }

  public function getServiceViewer() {
    return $this->serviceViewer;
  }

  public function setServiceRepository(PhabricatorRepository $repository) {
    $this->serviceRepository = $repository;
    return $this;
  }

  public function getServiceRepository() {
    return $this->serviceRepository;
  }

  public function getIsGitLFSRequest() {
    return $this->isGitLFSRequest;
  }

  public function getGitLFSToken() {
    return $this->gitLFSToken;
  }

  public function isVCSRequest(AphrontRequest $request) {
    $identifier = $this->getRepositoryIdentifierFromRequest($request);
    if ($identifier === null) {
      return null;
    }

    $content_type = $request->getHTTPHeader('Content-Type');
    $user_agent = idx($_SERVER, 'HTTP_USER_AGENT');
    $request_type = $request->getHTTPHeader('X-Phabricator-Request-Type');

    // This may have a "charset" suffix, so only match the prefix.
    $lfs_pattern = '(^application/vnd\\.git-lfs\\+json(;|\z))';

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
    } else if (preg_match($lfs_pattern, $content_type)) {
      // This is a Git LFS HTTP API request.
      $vcs = PhabricatorRepositoryType::REPOSITORY_TYPE_GIT;
      $this->isGitLFSRequest = true;
    } else if ($request_type == 'git-lfs') {
      // This is a Git LFS object content request.
      $vcs = PhabricatorRepositoryType::REPOSITORY_TYPE_GIT;
      $this->isGitLFSRequest = true;
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

  public function handleRequest(AphrontRequest $request) {
    $service_exception = null;
    $response = null;

    try {
      $response = $this->serveRequest($request);
    } catch (Exception $ex) {
      $service_exception = $ex;
    }

    try {
      $remote_addr = $request->getRemoteAddress();

      $pull_event = id(new PhabricatorRepositoryPullEvent())
        ->setEpoch(PhabricatorTime::getNow())
        ->setRemoteAddress($remote_addr)
        ->setRemoteProtocol('http');

      if ($response) {
        $pull_event
          ->setResultType('wild')
          ->setResultCode($response->getHTTPResponseCode());

        if ($response instanceof PhabricatorVCSResponse) {
          $pull_event->setProperties(
            array(
              'response.message' => $response->getMessage(),
            ));
        }
      } else {
        $pull_event
          ->setResultType('exception')
          ->setResultCode(500)
          ->setProperties(
            array(
              'exception.class' => get_class($ex),
              'exception.message' => $ex->getMessage(),
            ));
      }

      $viewer = $this->getServiceViewer();
      if ($viewer) {
        $pull_event->setPullerPHID($viewer->getPHID());
      }

      $repository = $this->getServiceRepository();
      if ($repository) {
        $pull_event->setRepositoryPHID($repository->getPHID());
      }

      $unguarded = AphrontWriteGuard::beginScopedUnguardedWrites();
        $pull_event->save();
      unset($unguarded);

    } catch (Exception $ex) {
      if ($service_exception) {
        throw $service_exception;
      }
      throw $ex;
    }

    if ($service_exception) {
      throw $service_exception;
    }

    return $response;
  }

  private function serveRequest(AphrontRequest $request) {
    $identifier = $this->getRepositoryIdentifierFromRequest($request);

    // If authentication credentials have been provided, try to find a user
    // that actually matches those credentials.

    // We require both the username and password to be nonempty, because Git
    // won't prompt users who provide a username but no password otherwise.
    // See T10797 for discussion.

    $have_user = strlen(idx($_SERVER, 'PHP_AUTH_USER'));
    $have_pass = strlen(idx($_SERVER, 'PHP_AUTH_PW'));
    if ($have_user && $have_pass) {
      $username = $_SERVER['PHP_AUTH_USER'];
      $password = new PhutilOpaqueEnvelope($_SERVER['PHP_AUTH_PW']);

      // Try Git LFS auth first since we can usually reject it without doing
      // any queries, since the username won't match the one we expect or the
      // request won't be LFS.
      $viewer = $this->authenticateGitLFSUser($username, $password);

      // If that failed, try normal auth. Note that we can use normal auth on
      // LFS requests, so this isn't strictly an alternative to LFS auth.
      if (!$viewer) {
        $viewer = $this->authenticateHTTPRepositoryUser($username, $password);
      }

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

    $this->setServiceViewer($viewer);

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
        ->withIdentifiers(array($identifier))
        ->needURIs(true)
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

    $response = $this->validateGitLFSRequest($repository, $viewer);
    if ($response) {
      return $response;
    }

    $this->setServiceRepository($repository);

    if (!$repository->isTracked()) {
      return new PhabricatorVCSResponse(
        403,
        pht('This repository is inactive.'));
    }

    $is_push = !$this->isReadOnlyRequest($repository);

    if ($this->getIsGitLFSRequest() && $this->getGitLFSToken()) {
      // We allow git LFS requests over HTTP even if the repository does not
      // otherwise support HTTP reads or writes, as long as the user is using a
      // token from SSH. If they're using HTTP username + password auth, they
      // have to obey the normal HTTP rules.
    } else {
      // For now, we don't distinguish between HTTP and HTTPS-originated
      // requests that are proxied within the cluster, so the user can connect
      // with HTTPS but we may be on HTTP by the time we reach this part of
      // the code. Allow things to move forward as long as either protocol
      // can be served.
      $proto_https = PhabricatorRepositoryURI::BUILTIN_PROTOCOL_HTTPS;
      $proto_http = PhabricatorRepositoryURI::BUILTIN_PROTOCOL_HTTP;

      $can_read =
        $repository->canServeProtocol($proto_https, false) ||
        $repository->canServeProtocol($proto_http, false);
      if (!$can_read) {
        return new PhabricatorVCSResponse(
          403,
          pht('This repository is not available over HTTP.'));
      }

      if ($is_push) {
        $can_write =
          $repository->canServeProtocol($proto_https, true) ||
          $repository->canServeProtocol($proto_http, true);
        if (!$can_write) {
          return new PhabricatorVCSResponse(
            403,
            pht('This repository is read-only over HTTP.'));
        }
      }
    }

    if ($is_push) {
      $can_push = PhabricatorPolicyFilter::hasCapability(
        $viewer,
        $repository,
        DiffusionPushCapability::CAPABILITY);
      if (!$can_push) {
        if ($viewer->isLoggedIn()) {
          $error_code = 403;
          $error_message = pht(
            'You do not have permission to push to this repository ("%s").',
            $repository->getDisplayName());

          if ($this->getIsGitLFSRequest()) {
            return DiffusionGitLFSResponse::newErrorResponse(
              $error_code,
              $error_message);
          } else {
            return new PhabricatorVCSResponse(
              $error_code,
              $error_message);
          }
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

    $vcs_type = $repository->getVersionControlSystem();
    $req_type = $this->isVCSRequest($request);

    if ($vcs_type != $req_type) {
      switch ($req_type) {
        case PhabricatorRepositoryType::REPOSITORY_TYPE_GIT:
          $result = new PhabricatorVCSResponse(
            500,
            pht(
              'This repository ("%s") is not a Git repository.',
              $repository->getDisplayName()));
          break;
        case PhabricatorRepositoryType::REPOSITORY_TYPE_MERCURIAL:
          $result = new PhabricatorVCSResponse(
            500,
            pht(
              'This repository ("%s") is not a Mercurial repository.',
              $repository->getDisplayName()));
          break;
        case PhabricatorRepositoryType::REPOSITORY_TYPE_SVN:
          $result = new PhabricatorVCSResponse(
            500,
            pht(
              'This repository ("%s") is not a Subversion repository.',
              $repository->getDisplayName()));
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

    // We can serve Git LFS requests first, since we don't need to proxy them.
    // It's also important that LFS requests never fall through to standard
    // service pathways, because that would let you use LFS tokens to read
    // normal repository data.
    if ($this->getIsGitLFSRequest()) {
      return $this->serveGitLFSRequest($repository, $viewer);
    }

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

    if ($this->getIsGitLFSRequest()) {
      return $this->isGitLFSReadOnlyRequest($repository);
    }

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

    // NOTE: We do not set HTTP_CONTENT_ENCODING here, because we already
    // decompressed the request when we read the request body, so the body is
    // just plain data with no encoding.

    $env = array(
      'REQUEST_METHOD' => $_SERVER['REQUEST_METHOD'],
      'QUERY_STRING' => $query_string,
      'CONTENT_TYPE' => $request->getHTTPHeader('Content-Type'),
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

    $unguarded = AphrontWriteGuard::beginScopedUnguardedWrites();

    $cluster_engine = id(new DiffusionRepositoryClusterEngine())
      ->setViewer($viewer)
      ->setRepository($repository);

    $did_write_lock = false;
    if ($this->isReadOnlyRequest($repository)) {
      $cluster_engine->synchronizeWorkingCopyBeforeRead();
    } else {
      $did_write_lock = true;
      $cluster_engine->synchronizeWorkingCopyBeforeWrite();
    }

    $caught = null;
    try {
      list($err, $stdout, $stderr) = id(new ExecFuture('%C', $command))
        ->setEnv($env, true)
        ->write($input)
        ->resolve();
    } catch (Exception $ex) {
      $caught = $ex;
    }

    if ($did_write_lock) {
      $cluster_engine->synchronizeWorkingCopyAfterWrite();
    }

    unset($unguarded);

    if ($caught) {
      throw $caught;
    }

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
        pht(
          'Error %d: %s',
          $err,
          phutil_utf8ize($stderr)));
    }

    return id(new DiffusionGitResponse())->setGitData($stdout);
  }

  private function getRequestDirectoryPath(PhabricatorRepository $repository) {
    $request = $this->getRequest();
    $request_path = $request->getRequestURI()->getPath();

    $info = PhabricatorRepository::parseRepositoryServicePath(
      $request_path,
      $repository->getVersionControlSystem());
    $base_path = $info['path'];

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

  private function authenticateGitLFSUser(
    $username,
    PhutilOpaqueEnvelope $password) {

    // Never accept these credentials for requests which aren't LFS requests.
    if (!$this->getIsGitLFSRequest()) {
      return null;
    }

    // If we have the wrong username, don't bother checking if the token
    // is right.
    if ($username !== DiffusionGitLFSTemporaryTokenType::HTTP_USERNAME) {
      return null;
    }

    $lfs_pass = $password->openEnvelope();
    $lfs_hash = PhabricatorHash::weakDigest($lfs_pass);

    $token = id(new PhabricatorAuthTemporaryTokenQuery())
      ->setViewer(PhabricatorUser::getOmnipotentUser())
      ->withTokenTypes(array(DiffusionGitLFSTemporaryTokenType::TOKENTYPE))
      ->withTokenCodes(array($lfs_hash))
      ->withExpired(false)
      ->executeOne();
    if (!$token) {
      return null;
    }

    $user = id(new PhabricatorPeopleQuery())
      ->setViewer(PhabricatorUser::getOmnipotentUser())
      ->withPHIDs(array($token->getUserPHID()))
      ->executeOne();

    if (!$user) {
      return null;
    }

    if (!$user->isUserActivated()) {
      return null;
    }

    $this->gitLFSToken = $token;

    return $user;
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

    $command = csprintf(
      '%s -R %s serve --stdio',
      $bin,
      $repository->getLocalPath());
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
      if ($cmd == 'capabilities') {
        $body = DiffusionMercurialWireProtocol::filterBundle2Capability($body);
      }
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
    $remote_address = $this->getRequest()->getRemoteAddress();

    return array(
      DiffusionCommitHookEngine::ENV_USER => $viewer->getUsername(),
      DiffusionCommitHookEngine::ENV_REMOTE_ADDRESS => $remote_address,
      DiffusionCommitHookEngine::ENV_REMOTE_PROTOCOL => 'http',
    );
  }

  private function validateGitLFSRequest(
    PhabricatorRepository $repository,
    PhabricatorUser $viewer) {
    if (!$this->getIsGitLFSRequest()) {
      return null;
    }

    if (!$repository->canUseGitLFS()) {
      return new PhabricatorVCSResponse(
        403,
        pht(
          'The requested repository ("%s") does not support Git LFS.',
          $repository->getDisplayName()));
    }

    // If this is using an LFS token, sanity check that we're using it on the
    // correct repository. This shouldn't really matter since the user could
    // just request a proper token anyway, but it suspicious and should not
    // be permitted.

    $token = $this->getGitLFSToken();
    if ($token) {
      $resource = $token->getTokenResource();
      if ($resource !== $repository->getPHID()) {
        return new PhabricatorVCSResponse(
          403,
          pht(
            'The authentication token provided in the request is bound to '.
            'a different repository than the requested repository ("%s").',
            $repository->getDisplayName()));
      }
    }

    return null;
  }

  private function serveGitLFSRequest(
    PhabricatorRepository $repository,
    PhabricatorUser $viewer) {

    if (!$this->getIsGitLFSRequest()) {
      throw new Exception(pht('This is not a Git LFS request!'));
    }

    $path = $this->getGitLFSRequestPath($repository);
    $matches = null;

    if (preg_match('(^upload/(.*)\z)', $path, $matches)) {
      $oid = $matches[1];
      return $this->serveGitLFSUploadRequest($repository, $viewer, $oid);
    } else if ($path == 'objects/batch') {
      return $this->serveGitLFSBatchRequest($repository, $viewer);
    } else {
      return DiffusionGitLFSResponse::newErrorResponse(
        404,
        pht(
          'Git LFS operation "%s" is not supported by this server.',
          $path));
    }
  }

  private function serveGitLFSBatchRequest(
    PhabricatorRepository $repository,
    PhabricatorUser $viewer) {

    $input = $this->getGitLFSInput();

    $operation = idx($input, 'operation');
    switch ($operation) {
      case 'upload':
        $want_upload = true;
        break;
      case 'download':
        $want_upload = false;
        break;
      default:
        return DiffusionGitLFSResponse::newErrorResponse(
          404,
          pht(
            'Git LFS batch operation "%s" is not supported by this server.',
            $operation));
    }

    $objects = idx($input, 'objects', array());

    $hashes = array();
    foreach ($objects as $object) {
      $hashes[] = idx($object, 'oid');
    }

    if ($hashes) {
      $refs = id(new PhabricatorRepositoryGitLFSRefQuery())
        ->setViewer($viewer)
        ->withRepositoryPHIDs(array($repository->getPHID()))
        ->withObjectHashes($hashes)
        ->execute();
      $refs = mpull($refs, null, 'getObjectHash');
    } else {
      $refs = array();
    }

    $file_phids = mpull($refs, 'getFilePHID');
    if ($file_phids) {
      $files = id(new PhabricatorFileQuery())
        ->setViewer($viewer)
        ->withPHIDs($file_phids)
        ->execute();
      $files = mpull($files, null, 'getPHID');
    } else {
      $files = array();
    }

    $authorization = null;
    $output = array();
    foreach ($objects as $object) {
      $oid = idx($object, 'oid');
      $size = idx($object, 'size');
      $ref = idx($refs, $oid);
      $error = null;

      // NOTE: If we already have a ref for this object, we only emit a
      // "download" action. The client should not upload the file again.

      $actions = array();
      if ($ref) {
        $file = idx($files, $ref->getFilePHID());
        if ($file) {
          // Git LFS may prompt users for authentication if the action does
          // not provide an "Authorization" header and does not have a query
          // parameter named "token". See here for discussion:
          // <https://github.com/github/git-lfs/issues/1088>
          $no_authorization = 'Basic '.base64_encode('none');

          $get_uri = $file->getCDNURI();
          $actions['download'] = array(
            'href' => $get_uri,
            'header' => array(
              'Authorization' => $no_authorization,
              'X-Phabricator-Request-Type' => 'git-lfs',
            ),
          );
        } else {
          $error = array(
            'code' => 404,
            'message' => pht(
              'Object "%s" was previously uploaded, but no longer exists '.
              'on this server.',
              $oid),
          );
        }
      } else if ($want_upload) {
        if (!$authorization) {
          // Here, we could reuse the existing authorization if we have one,
          // but it's a little simpler to just generate a new one
          // unconditionally.
          $authorization = $this->newGitLFSHTTPAuthorization(
            $repository,
            $viewer,
            $operation);
        }

        $put_uri = $repository->getGitLFSURI("info/lfs/upload/{$oid}");

        $actions['upload'] = array(
          'href' => $put_uri,
          'header' => array(
            'Authorization' => $authorization,
            'X-Phabricator-Request-Type' => 'git-lfs',
          ),
        );
      }

      $object = array(
        'oid' => $oid,
        'size' => $size,
      );

      if ($actions) {
        $object['actions'] = $actions;
      }

      if ($error) {
        $object['error'] = $error;
      }

      $output[] = $object;
    }

    $output = array(
      'objects' => $output,
    );

    return id(new DiffusionGitLFSResponse())
      ->setContent($output);
  }

  private function serveGitLFSUploadRequest(
    PhabricatorRepository $repository,
    PhabricatorUser $viewer,
    $oid) {

    $ref = id(new PhabricatorRepositoryGitLFSRefQuery())
      ->setViewer($viewer)
      ->withRepositoryPHIDs(array($repository->getPHID()))
      ->withObjectHashes(array($oid))
      ->executeOne();
    if ($ref) {
      return DiffusionGitLFSResponse::newErrorResponse(
        405,
        pht(
          'Content for object "%s" is already known to this server. It can '.
          'not be uploaded again.',
          $oid));
    }

    // Remove the execution time limit because uploading large files may take
    // a while.
    set_time_limit(0);

    $request_stream = new AphrontRequestStream();
    $request_iterator = $request_stream->getIterator();
    $hashing_iterator = id(new PhutilHashingIterator($request_iterator))
      ->setAlgorithm('sha256');

    $source = id(new PhabricatorIteratorFileUploadSource())
      ->setName('lfs-'.$oid)
      ->setViewPolicy(PhabricatorPolicies::POLICY_NOONE)
      ->setIterator($hashing_iterator);

    $unguarded = AphrontWriteGuard::beginScopedUnguardedWrites();
      $file = $source->uploadFile();
    unset($unguarded);

    $hash = $hashing_iterator->getHash();
    if ($hash !== $oid) {
      return DiffusionGitLFSResponse::newErrorResponse(
        400,
        pht(
          'Uploaded data is corrupt or invalid. Expected hash "%s", actual '.
          'hash "%s".',
          $oid,
          $hash));
    }

    $ref = id(new PhabricatorRepositoryGitLFSRef())
      ->setRepositoryPHID($repository->getPHID())
      ->setObjectHash($hash)
      ->setByteSize($file->getByteSize())
      ->setAuthorPHID($viewer->getPHID())
      ->setFilePHID($file->getPHID());

    $unguarded = AphrontWriteGuard::beginScopedUnguardedWrites();
      // Attach the file to the repository to give users permission
      // to access it.
      $file->attachToObject($repository->getPHID());
      $ref->save();
    unset($unguarded);

    // This is just a plain HTTP 200 with no content, which is what `git lfs`
    // expects.
    return new DiffusionGitLFSResponse();
  }

  private function newGitLFSHTTPAuthorization(
    PhabricatorRepository $repository,
    PhabricatorUser $viewer,
    $operation) {

    $unguarded = AphrontWriteGuard::beginScopedUnguardedWrites();

    $authorization = DiffusionGitLFSTemporaryTokenType::newHTTPAuthorization(
      $repository,
      $viewer,
      $operation);

    unset($unguarded);

    return $authorization;
  }

  private function getGitLFSRequestPath(PhabricatorRepository $repository) {
    $request_path = $this->getRequestDirectoryPath($repository);

    $matches = null;
    if (preg_match('(^/info/lfs(?:\z|/)(.*))', $request_path, $matches)) {
      return $matches[1];
    }

    return null;
  }

  private function getGitLFSInput() {
    if (!$this->gitLFSInput) {
      $input = PhabricatorStartup::getRawInput();
      $input = phutil_json_decode($input);
      $this->gitLFSInput = $input;
    }

    return $this->gitLFSInput;
  }

  private function isGitLFSReadOnlyRequest(PhabricatorRepository $repository) {
    if (!$this->getIsGitLFSRequest()) {
      return false;
    }

    $path = $this->getGitLFSRequestPath($repository);

    if ($path === 'objects/batch') {
      $input = $this->getGitLFSInput();
      $operation = idx($input, 'operation');
      switch ($operation) {
        case 'download':
          return true;
        default:
          return false;
      }
    }

    return false;
  }


}
