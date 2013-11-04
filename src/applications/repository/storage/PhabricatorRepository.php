<?php

/**
 * @task uri Repository URI Management
 */
final class PhabricatorRepository extends PhabricatorRepositoryDAO
  implements
    PhabricatorPolicyInterface,
    PhabricatorFlaggableInterface,
    PhabricatorMarkupInterface {

  /**
   * Shortest hash we'll recognize in raw "a829f32" form.
   */
  const MINIMUM_UNQUALIFIED_HASH = 7;

  /**
   * Shortest hash we'll recognize in qualified "rXab7ef2f8" form.
   */
  const MINIMUM_QUALIFIED_HASH = 5;

  const TABLE_PATH = 'repository_path';
  const TABLE_PATHCHANGE = 'repository_pathchange';
  const TABLE_FILESYSTEM = 'repository_filesystem';
  const TABLE_SUMMARY = 'repository_summary';
  const TABLE_BADCOMMIT = 'repository_badcommit';
  const TABLE_LINTMESSAGE = 'repository_lintmessage';

  const SERVE_OFF = 'off';
  const SERVE_READONLY = 'readonly';
  const SERVE_READWRITE = 'readwrite';

  protected $name;
  protected $callsign;
  protected $uuid;
  protected $viewPolicy;
  protected $editPolicy;
  protected $pushPolicy;

  protected $versionControlSystem;
  protected $details = array();

  private $sshKeyfile;

  private $commitCount = self::ATTACHABLE;
  private $mostRecentCommit = self::ATTACHABLE;

  public static function initializeNewRepository(PhabricatorUser $actor) {
    $app = id(new PhabricatorApplicationQuery())
      ->setViewer($actor)
      ->withClasses(array('PhabricatorApplicationDiffusion'))
      ->executeOne();

    $view_policy = $app->getPolicy(DiffusionCapabilityDefaultView::CAPABILITY);
    $edit_policy = $app->getPolicy(DiffusionCapabilityDefaultEdit::CAPABILITY);
    $push_policy = $app->getPolicy(DiffusionCapabilityDefaultPush::CAPABILITY);

    return id(new PhabricatorRepository())
      ->setViewPolicy($view_policy)
      ->setEditPolicy($edit_policy)
      ->setPushPolicy($push_policy);
  }

  public function getConfiguration() {
    return array(
      self::CONFIG_AUX_PHID => true,
      self::CONFIG_SERIALIZATION => array(
        'details' => self::SERIALIZATION_JSON,
      ),
    ) + parent::getConfiguration();
  }

  public function generatePHID() {
    return PhabricatorPHID::generateNewPHID(
      PhabricatorRepositoryPHIDTypeRepository::TYPECONST);
  }

  public function toDictionary() {
    return array(
      'name'        => $this->getName(),
      'phid'        => $this->getPHID(),
      'callsign'    => $this->getCallsign(),
      'vcs'         => $this->getVersionControlSystem(),
      'uri'         => PhabricatorEnv::getProductionURI($this->getURI()),
      'remoteURI'   => (string)$this->getPublicRemoteURI(),
      'tracking'    => $this->getDetail('tracking-enabled'),
      'description' => $this->getDetail('description'),
    );
  }

  public function getDetail($key, $default = null) {
    return idx($this->details, $key, $default);
  }

  public function getHumanReadableDetail($key, $default = null) {
    $value = $this->getDetail($key, $default);

    switch ($key) {
      case 'branch-filter':
      case 'close-commits-filter':
        $value = array_keys($value);
        $value = implode(', ', $value);
        break;
    }

    return $value;
  }

  public function setDetail($key, $value) {
    $this->details[$key] = $value;
    return $this;
  }

  public function attachCommitCount($count) {
    $this->commitCount = $count;
    return $this;
  }

  public function getCommitCount() {
    return $this->assertAttached($this->commitCount);
  }

  public function attachMostRecentCommit(
    PhabricatorRepositoryCommit $commit = null) {
    $this->mostRecentCommit = $commit;
    return $this;
  }

  public function getMostRecentCommit() {
    return $this->assertAttached($this->mostRecentCommit);
  }

  public function getDiffusionBrowseURIForPath(
    PhabricatorUser $user,
    $path,
    $line = null,
    $branch = null) {

    $drequest = DiffusionRequest::newFromDictionary(
      array(
        'user' => $user,
        'repository' => $this,
        'path' => $path,
        'branch' => $branch,
      ));

    return $drequest->generateURI(
      array(
        'action' => 'browse',
        'line'   => $line,
      ));
  }

  public function getLocalPath() {
    return $this->getDetail('local-path');
  }

  public function getSubversionBaseURI() {
    $vcs = $this->getVersionControlSystem();
    if ($vcs != PhabricatorRepositoryType::REPOSITORY_TYPE_SVN) {
      throw new Exception("Not a subversion repository!");
    }

    if ($this->isHosted()) {
      $uri = 'file://'.$this->getLocalPath();
    } else {
      $uri = $this->getDetail('remote-uri');
    }

    $subpath = $this->getDetail('svn-subpath');
    if ($subpath) {
      $subpath = '/'.ltrim($subpath, '/');
    }

    return $uri.$subpath;
  }

  public function execRemoteCommand($pattern /* , $arg, ... */) {
    $args = func_get_args();
    $args = $this->formatRemoteCommand($args);
    return call_user_func_array('exec_manual', $args);
  }

  public function execxRemoteCommand($pattern /* , $arg, ... */) {
    $args = func_get_args();
    $args = $this->formatRemoteCommand($args);
    return call_user_func_array('execx', $args);
  }

  public function getRemoteCommandFuture($pattern /* , $arg, ... */) {
    $args = func_get_args();
    $args = $this->formatRemoteCommand($args);
    return newv('ExecFuture', $args);
  }

  public function passthruRemoteCommand($pattern /* , $arg, ... */) {
    $args = func_get_args();
    $args = $this->formatRemoteCommand($args);
    return call_user_func_array('phutil_passthru', $args);
  }

  public function execLocalCommand($pattern /* , $arg, ... */) {
    $this->assertLocalExists();

    $args = func_get_args();
    $args = $this->formatLocalCommand($args);
    return call_user_func_array('exec_manual', $args);
  }

  public function execxLocalCommand($pattern /* , $arg, ... */) {
    $this->assertLocalExists();

    $args = func_get_args();
    $args = $this->formatLocalCommand($args);
    return call_user_func_array('execx', $args);
  }

  public function getLocalCommandFuture($pattern /* , $arg, ... */) {
    $this->assertLocalExists();

    $args = func_get_args();
    $args = $this->formatLocalCommand($args);
    return newv('ExecFuture', $args);
  }

  public function passthruLocalCommand($pattern /* , $arg, ... */) {
    $this->assertLocalExists();

    $args = func_get_args();
    $args = $this->formatLocalCommand($args);
    return call_user_func_array('phutil_passthru', $args);
  }


  private function formatRemoteCommand(array $args) {
    $pattern = $args[0];
    $args = array_slice($args, 1);

    $empty = $this->getEmptyReadableDirectoryPath();

    if ($this->shouldUseSSH()) {
      switch ($this->getVersionControlSystem()) {
        case PhabricatorRepositoryType::REPOSITORY_TYPE_SVN:
          $pattern = "SVN_SSH=%s svn --non-interactive {$pattern}";
          array_unshift(
            $args,
            csprintf(
              'ssh -l %P -i %P',
              new PhutilOpaqueEnvelope($this->getSSHLogin()),
              new PhutilOpaqueEnvelope($this->getSSHKeyfile())));
          break;
        case PhabricatorRepositoryType::REPOSITORY_TYPE_GIT:
          $command = call_user_func_array(
            'csprintf',
            array_merge(
              array(
                "(ssh-add %P && HOME=%s git {$pattern})",
                new PhutilOpaqueEnvelope($this->getSSHKeyfile()),
                $empty,
              ),
              $args));
          $pattern = "ssh-agent sh -c %s";
          $args = array($command);
          break;
        case PhabricatorRepositoryType::REPOSITORY_TYPE_MERCURIAL:
          $pattern = "hg --config ui.ssh=%s {$pattern}";
          array_unshift(
            $args,
            csprintf(
              'ssh -l %P -i %P',
              new PhutilOpaqueEnvelope($this->getSSHLogin()),
              new PhutilOpaqueEnvelope($this->getSSHKeyfile())));
          break;
        default:
          throw new Exception("Unrecognized version control system.");
      }
    } else if ($this->shouldUseHTTP()) {
      switch ($this->getVersionControlSystem()) {
        case PhabricatorRepositoryType::REPOSITORY_TYPE_SVN:
          $pattern =
            "svn ".
            "--non-interactive ".
            "--no-auth-cache ".
            "--trust-server-cert ".
            "--username %P ".
            "--password %P ".
            $pattern;
          array_unshift(
            $args,
            new PhutilOpaqueEnvelope($this->getDetail('http-login')),
            new PhutilOpaqueEnvelope($this->getDetail('http-pass')));
          break;
        default:
          throw new Exception(
            "No support for HTTP Basic Auth in this version control system.");
      }
    } else if ($this->shouldUseSVNProtocol()) {
      switch ($this->getVersionControlSystem()) {
        case PhabricatorRepositoryType::REPOSITORY_TYPE_SVN:
            $pattern =
              "svn ".
              "--non-interactive ".
              "--no-auth-cache ".
              "--username %P ".
              "--password %P ".
              $pattern;
            array_unshift(
              $args,
              new PhutilOpaqueEnvelope($this->getDetail('http-login')),
              new PhutilOpaqueEnvelope($this->getDetail('http-pass')));
            break;
        default:
          throw new Exception(
            "SVN protocol is SVN only.");
      }
    } else {
      switch ($this->getVersionControlSystem()) {
        case PhabricatorRepositoryType::REPOSITORY_TYPE_SVN:
          $pattern = "svn --non-interactive {$pattern}";
          break;
        case PhabricatorRepositoryType::REPOSITORY_TYPE_GIT:
          $pattern = "HOME=%s git {$pattern}";
          array_unshift($args, $empty);
          break;
        case PhabricatorRepositoryType::REPOSITORY_TYPE_MERCURIAL:
          $pattern = "hg {$pattern}";
          break;
        default:
          throw new Exception("Unrecognized version control system.");
      }
    }

    array_unshift($args, $pattern);

    return $args;
  }

  private function formatLocalCommand(array $args) {
    $pattern = $args[0];
    $args = array_slice($args, 1);

    $empty = $this->getEmptyReadableDirectoryPath();

    switch ($this->getVersionControlSystem()) {
      case PhabricatorRepositoryType::REPOSITORY_TYPE_SVN:
        $pattern = "(cd %s && svn --non-interactive {$pattern})";
        array_unshift($args, $this->getLocalPath());
        break;
      case PhabricatorRepositoryType::REPOSITORY_TYPE_GIT:
        $pattern = "(cd %s && HOME=%s git {$pattern})";
        array_unshift($args, $this->getLocalPath(), $empty);
        break;
      case PhabricatorRepositoryType::REPOSITORY_TYPE_MERCURIAL:
        $hgplain = (phutil_is_windows() ? "set HGPLAIN=1 &&" : "HGPLAIN=1");
        $pattern = "(cd %s && {$hgplain} hg {$pattern})";
        array_unshift($args, $this->getLocalPath());
        break;
      default:
        throw new Exception("Unrecognized version control system.");
    }

    array_unshift($args, $pattern);

    return $args;
  }

  private function getEmptyReadableDirectoryPath() {
    // See T2965. Some time after Git 1.7.5.4, Git started fataling if it can
    // not read $HOME. For many users, $HOME points at /root (this seems to be
    // a default result of Apache setup). Instead, explicitly point $HOME at a
    // readable, empty directory so that Git looks for the config file it's
    // after, fails to locate it, and moves on. This is really silly, but seems
    // like the least damaging approach to mitigating the issue.
    $root = dirname(phutil_get_library_root('phabricator'));
    return $root.'/support/empty/';
  }

  private function getSSHLogin() {
    return $this->getDetail('ssh-login');
  }

  private function getSSHKeyfile() {
    if ($this->sshKeyfile === null) {
      $key = $this->getDetail('ssh-key');
      $keyfile = $this->getDetail('ssh-keyfile');
      if ($keyfile) {
        // Make sure we can read the file, that it exists, etc.
        Filesystem::readFile($keyfile);
        $this->sshKeyfile = $keyfile;
      } else if ($key) {
        $keyfile = new TempFile('phabricator-repository-ssh-key');
        chmod($keyfile, 0600);
        Filesystem::writeFile($keyfile, $key);
        $this->sshKeyfile = $keyfile;
      } else {
        $this->sshKeyfile = '';
      }
    }

    return (string)$this->sshKeyfile;
  }

  public function getURI() {
    return '/diffusion/'.$this->getCallsign().'/';
  }

  public function isTracked() {
    return $this->getDetail('tracking-enabled', false);
  }

  public function getDefaultBranch() {
    $default = $this->getDetail('default-branch');
    if (strlen($default)) {
      return $default;
    }

    $default_branches = array(
      PhabricatorRepositoryType::REPOSITORY_TYPE_GIT        => 'master',
      PhabricatorRepositoryType::REPOSITORY_TYPE_MERCURIAL  => 'default',
    );

    return idx($default_branches, $this->getVersionControlSystem());
  }

  public function getDefaultArcanistBranch() {
    return coalesce($this->getDefaultBranch(), 'svn');
  }

  private function isBranchInFilter($branch, $filter_key) {
    $vcs = $this->getVersionControlSystem();

    $is_git = ($vcs == PhabricatorRepositoryType::REPOSITORY_TYPE_GIT);

    $use_filter = ($is_git);

    if ($use_filter) {
      $filter = $this->getDetail($filter_key, array());
      if ($filter && empty($filter[$branch])) {
        return false;
      }
    }

    // By default, all branches pass.
    return true;
  }

  public function shouldTrackBranch($branch) {
    return $this->isBranchInFilter($branch, 'branch-filter');
  }

  public function shouldAutocloseBranch($branch) {
    if ($this->isImporting()) {
      return false;
    }

    if ($this->getDetail('disable-autoclose', false)) {
      return false;
    }

    return $this->isBranchInFilter($branch, 'close-commits-filter');
  }

  public function shouldAutocloseCommit(
    PhabricatorRepositoryCommit $commit,
    PhabricatorRepositoryCommitData $data) {

    if ($this->getDetail('disable-autoclose', false)) {
      return false;
    }

    switch ($this->getVersionControlSystem()) {
      case PhabricatorRepositoryType::REPOSITORY_TYPE_SVN:
        return true;
      case PhabricatorRepositoryType::REPOSITORY_TYPE_GIT:
        break;
      case PhabricatorRepositoryType::REPOSITORY_TYPE_MERCURIAL:
        return true;
      default:
        throw new Exception("Unrecognized version control system.");
    }

    $branches = $data->getCommitDetail('seenOnBranches', array());
    foreach ($branches as $branch) {
      if ($this->shouldAutocloseBranch($branch)) {
        return true;
      }
    }

    return false;
  }

  public function formatCommitName($commit_identifier) {
    $vcs = $this->getVersionControlSystem();

    $type_git = PhabricatorRepositoryType::REPOSITORY_TYPE_GIT;
    $type_hg = PhabricatorRepositoryType::REPOSITORY_TYPE_MERCURIAL;

    $is_git = ($vcs == $type_git);
    $is_hg = ($vcs == $type_hg);
    if ($is_git || $is_hg) {
      $short_identifier = substr($commit_identifier, 0, 12);
    } else {
      $short_identifier = $commit_identifier;
    }

    return 'r'.$this->getCallsign().$short_identifier;
  }

  public function isImporting() {
    return (bool)$this->getDetail('importing', false);
  }

/* -(  Repository URI Management  )------------------------------------------ */


  /**
   * Get the remote URI for this repository.
   *
   * @return string
   * @task uri
   */
  public function getRemoteURI() {
    return (string)$this->getRemoteURIObject();
  }


  /**
   * Get the remote URI for this repository, without authentication information.
   *
   * @return string Repository URI.
   * @task uri
   */
  public function getPublicRemoteURI() {
    $uri = $this->getRemoteURIObject();

    // Make sure we don't leak anything if this repo is using HTTP Basic Auth
    // with the credentials in the URI or something zany like that.

    if ($uri instanceof PhutilGitURI) {
      if (!$this->getDetail('show-user', false)) {
        $uri->setUser(null);
      }
    } else {
      if (!$this->getDetail('show-user', false)) {
        $uri->setUser(null);
      }
      $uri->setPass(null);
    }

    return (string)$uri;
  }


  /**
   * Get the protocol for the repository's remote.
   *
   * @return string Protocol, like "ssh" or "git".
   * @task uri
   */
  public function getRemoteProtocol() {
    $uri = $this->getRemoteURIObject();

    if ($uri instanceof PhutilGitURI) {
      return 'ssh';
    } else {
      return $uri->getProtocol();
    }
  }


  /**
   * Get a parsed object representation of the repository's remote URI. This
   * may be a normal URI (returned as a @{class@libphutil:PhutilURI}) or a git
   * URI (returned as a @{class@libphutil:PhutilGitURI}).
   *
   * @return wild A @{class@libphutil:PhutilURI} or
   *              @{class@libphutil:PhutilGitURI}.
   * @task uri
   */
  private function getRemoteURIObject() {
    $raw_uri = $this->getDetail('remote-uri');
    if (!$raw_uri) {
      return new PhutilURI('');
    }

    if (!strncmp($raw_uri, '/', 1)) {
      return new PhutilURI('file://'.$raw_uri);
    }

    $uri = new PhutilURI($raw_uri);
    if ($uri->getProtocol()) {
      if ($this->isSSHProtocol($uri->getProtocol())) {
        if ($this->getSSHLogin()) {
          $uri->setUser($this->getSSHLogin());
        }
      }
      return $uri;
    }

    $uri = new PhutilGitURI($raw_uri);
    if ($uri->getDomain()) {
      if ($this->getSSHLogin()) {
        $uri->setUser($this->getSSHLogin());
      }
      return $uri;
    }

    throw new Exception("Remote URI '{$raw_uri}' could not be parsed!");
  }


  /**
   * Determine if we should connect to the remote using SSH flags and
   * credentials.
   *
   * @return bool True to use the SSH protocol.
   * @task uri
   */
  private function shouldUseSSH() {
    if ($this->isHosted()) {
      return false;
    }

    $protocol = $this->getRemoteProtocol();
    if ($this->isSSHProtocol($protocol)) {
      return (bool)$this->getSSHKeyfile();
    } else {
      return false;
    }
  }


  /**
   * Determine if we should connect to the remote using HTTP flags and
   * credentials.
   *
   * @return bool True to use the HTTP protocol.
   * @task uri
   */
  private function shouldUseHTTP() {
    if ($this->isHosted()) {
      return false;
    }

    $protocol = $this->getRemoteProtocol();
    if ($protocol == 'http' || $protocol == 'https') {
      return (bool)$this->getDetail('http-login');
    } else {
      return false;
    }
  }


  /**
   * Determine if we should connect to the remote using SVN flags and
   * credentials.
   *
   * @return bool True to use the SVN protocol.
   * @task uri
   */
  private function shouldUseSVNProtocol() {
    if ($this->isHosted()) {
      return false;
    }

    $protocol = $this->getRemoteProtocol();
    if ($protocol == 'svn') {
      return (bool)$this->getDetail('http-login');
    } else {
      return false;
    }
  }


  /**
   * Determine if a protocol is SSH or SSH-like.
   *
   * @param string A protocol string, like "http" or "ssh".
   * @return bool True if the protocol is SSH-like.
   * @task uri
   */
  private function isSSHProtocol($protocol) {
    return ($protocol == 'ssh' || $protocol == 'svn+ssh');
  }

  public function delete() {
    $this->openTransaction();

      $paths = id(new PhabricatorOwnersPath())
        ->loadAllWhere('repositoryPHID = %s', $this->getPHID());
      foreach ($paths as $path) {
        $path->delete();
      }

      $projects = id(new PhabricatorRepositoryArcanistProject())
        ->loadAllWhere('repositoryID = %d', $this->getID());
      foreach ($projects as $project) {
        // note each project deletes its PhabricatorRepositorySymbols
        $project->delete();
      }

      $commits = id(new PhabricatorRepositoryCommit())
        ->loadAllWhere('repositoryID = %d', $this->getID());
      foreach ($commits as $commit) {
        // note PhabricatorRepositoryAuditRequests and
        // PhabricatorRepositoryCommitData are deleted here too.
        $commit->delete();
      }

      $conn_w = $this->establishConnection('w');

      queryfx(
        $conn_w,
        'DELETE FROM %T WHERE repositoryID = %d',
        self::TABLE_FILESYSTEM,
        $this->getID());

      queryfx(
        $conn_w,
        'DELETE FROM %T WHERE repositoryID = %d',
        self::TABLE_PATHCHANGE,
        $this->getID());

      queryfx(
        $conn_w,
        'DELETE FROM %T WHERE repositoryID = %d',
        self::TABLE_SUMMARY,
        $this->getID());

      $result = parent::delete();

    $this->saveTransaction();
    return $result;
  }

  public function isGit() {
    $vcs = $this->getVersionControlSystem();
    return ($vcs == PhabricatorRepositoryType::REPOSITORY_TYPE_GIT);
  }

  public function isSVN() {
    $vcs = $this->getVersionControlSystem();
    return ($vcs == PhabricatorRepositoryType::REPOSITORY_TYPE_SVN);
  }

  public function isHg() {
    $vcs = $this->getVersionControlSystem();
    return ($vcs == PhabricatorRepositoryType::REPOSITORY_TYPE_MERCURIAL);
  }

  public function isHosted() {
    return (bool)$this->getDetail('hosting-enabled', false);
  }

  public function setHosted($enabled) {
    return $this->setDetail('hosting-enabled', $enabled);
  }

  public function getServeOverHTTP() {
    $serve = $this->getDetail('serve-over-http', self::SERVE_OFF);
    return $this->normalizeServeConfigSetting($serve);
  }

  public function setServeOverHTTP($mode) {
    return $this->setDetail('serve-over-http', $mode);
  }

  public function getServeOverSSH() {
    $serve = $this->getDetail('serve-over-ssh', self::SERVE_OFF);
    return $this->normalizeServeConfigSetting($serve);
  }

  public function setServeOverSSH($mode) {
    return $this->setDetail('serve-over-ssh', $mode);
  }

  public static function getProtocolAvailabilityName($constant) {
    switch ($constant) {
      case self::SERVE_OFF:
        return pht('Off');
      case self::SERVE_READONLY:
        return pht('Read Only');
      case self::SERVE_READWRITE:
        return pht('Read/Write');
      default:
        return pht('Unknown');
    }
  }

  private function normalizeServeConfigSetting($value) {
    switch ($value) {
      case self::SERVE_OFF:
      case self::SERVE_READONLY:
        return $value;
      case self::SERVE_READWRITE:
        if ($this->isHosted()) {
          return self::SERVE_READWRITE;
        } else {
          return self::SERVE_READONLY;
        }
      default:
        return self::SERVE_OFF;
    }
  }


  /**
   * Raise more useful errors when there are basic filesystem problems.
   */
  private function assertLocalExists() {
    if (!$this->usesLocalWorkingCopy()) {
      return;
    }

    $local = $this->getLocalPath();
    Filesystem::assertExists($local);
    Filesystem::assertIsDirectory($local);
    Filesystem::assertReadable($local);
  }

  /**
   * Determine if the working copy is bare or not. In Git, this corresponds
   * to `--bare`. In Mercurial, `--noupdate`.
   */
  public function isWorkingCopyBare() {
    switch ($this->getVersionControlSystem()) {
      case PhabricatorRepositoryType::REPOSITORY_TYPE_SVN:
      case PhabricatorRepositoryType::REPOSITORY_TYPE_MERCURIAL:
        return false;
      case PhabricatorRepositoryType::REPOSITORY_TYPE_GIT:
        $local = $this->getLocalPath();
        if (Filesystem::pathExists($local.'/.git')) {
          return false;
        } else {
          return true;
        }
    }
  }

  public function usesLocalWorkingCopy() {
    switch ($this->getVersionControlSystem()) {
      case PhabricatorRepositoryType::REPOSITORY_TYPE_SVN:
        return $this->isHosted();
      case PhabricatorRepositoryType::REPOSITORY_TYPE_GIT:
      case PhabricatorRepositoryType::REPOSITORY_TYPE_MERCURIAL:
        return true;
    }
  }

  public function writeStatusMessage(
    $status_type,
    $status_code,
    array $parameters = array()) {

    $table = new PhabricatorRepositoryStatusMessage();
    $conn_w = $table->establishConnection('w');
    $table_name = $table->getTableName();

    if ($status_code === null) {
      queryfx(
        $conn_w,
        'DELETE FROM %T WHERE repositoryID = %d AND statusType = %s',
        $table_name,
        $this->getID(),
        $status_type);
    } else {
      queryfx(
        $conn_w,
        'INSERT INTO %T
          (repositoryID, statusType, statusCode, parameters, epoch)
          VALUES (%d, %s, %s, %s, %d)
          ON DUPLICATE KEY UPDATE
            statusCode = VALUES(statusCode),
            parameters = VALUES(parameters),
            epoch = VALUES(epoch)',
        $table_name,
        $this->getID(),
        $status_type,
        $status_code,
        json_encode($parameters),
        time());
    }

    return $this;
  }


/* -(  PhabricatorPolicyInterface  )----------------------------------------- */


  public function getCapabilities() {
    return array(
      PhabricatorPolicyCapability::CAN_VIEW,
      PhabricatorPolicyCapability::CAN_EDIT,
      DiffusionCapabilityPush::CAPABILITY,
    );
  }

  public function getPolicy($capability) {
    switch ($capability) {
      case PhabricatorPolicyCapability::CAN_VIEW:
        return $this->getViewPolicy();
      case PhabricatorPolicyCapability::CAN_EDIT:
        return $this->getEditPolicy();
      case DiffusionCapabilityPush::CAPABILITY:
        return $this->getPushPolicy();
    }
  }

  public function hasAutomaticCapability($capability, PhabricatorUser $user) {
    return false;
  }

  public function describeAutomaticCapability($capability) {
    return null;
  }



/* -(  PhabricatorMarkupInterface  )----------------------------------------- */


  public function getMarkupFieldKey($field) {
    $hash = PhabricatorHash::digestForIndex($this->getMarkupText($field));
    return "repo:{$hash}";
  }

  public function newMarkupEngine($field) {
    return PhabricatorMarkupEngine::newMarkupEngine(array());
  }

  public function getMarkupText($field) {
    return $this->getDetail('description');
  }

  public function didMarkupText(
    $field,
    $output,
    PhutilMarkupEngine $engine) {
    require_celerity_resource('phabricator-remarkup-css');
    return phutil_tag(
      'div',
      array(
        'class' => 'phabricator-remarkup',
      ),
      $output);
  }

  public function shouldUseMarkupCache($field) {
    return true;
  }

}
