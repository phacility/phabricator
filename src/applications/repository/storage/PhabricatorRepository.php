<?php

/**
 * @task uri Repository URI Management
 */
final class PhabricatorRepository extends PhabricatorRepositoryDAO
  implements PhabricatorPolicyInterface {

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

  protected $phid;
  protected $name;
  protected $callsign;
  protected $uuid;

  protected $versionControlSystem;
  protected $details = array();

  private $sshKeyfile;

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
      PhabricatorPHIDConstants::PHID_TYPE_REPO);
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

  public function setDetail($key, $value) {
    $this->details[$key] = $value;
    return $this;
  }

  public function getDiffusionBrowseURIForPath($path,
                                               $line = null,
                                               $branch = null) {
    $drequest = DiffusionRequest::newFromDictionary(
      array(
        'repository' => $this,
        'path'       => $path,
        'branch'     => $branch,
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
    $args = func_get_args();
    $args = $this->formatLocalCommand($args);
    return call_user_func_array('exec_manual', $args);
  }

  public function execxLocalCommand($pattern /* , $arg, ... */) {
    $args = func_get_args();
    $args = $this->formatLocalCommand($args);
    return call_user_func_array('execx', $args);
  }

  public function getLocalCommandFuture($pattern /* , $arg, ... */) {
    $args = func_get_args();
    $args = $this->formatLocalCommand($args);
    return newv('ExecFuture', $args);
  }

  public function passthruLocalCommand($pattern /* , $arg, ... */) {
    $args = func_get_args();
    $args = $this->formatLocalCommand($args);
    return call_user_func_array('phutil_passthru', $args);
  }


  private function formatRemoteCommand(array $args) {
    $pattern = $args[0];
    $args = array_slice($args, 1);

    if ($this->shouldUseSSH()) {
      switch ($this->getVersionControlSystem()) {
        case PhabricatorRepositoryType::REPOSITORY_TYPE_SVN:
          $pattern = "SVN_SSH=%s svn --non-interactive {$pattern}";
          array_unshift(
            $args,
            csprintf(
              'ssh -l %s -i %s',
              $this->getSSHLogin(),
              $this->getSSHKeyfile()));
          break;
        case PhabricatorRepositoryType::REPOSITORY_TYPE_GIT:
          $command = call_user_func_array(
            'csprintf',
            array_merge(
              array(
                "(ssh-add %s && git {$pattern})",
                $this->getSSHKeyfile(),
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
              'ssh -l %s -i %s',
              $this->getSSHLogin(),
              $this->getSSHKeyfile()));
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
            "--username %s ".
            "--password %s ".
            $pattern;
          array_unshift(
            $args,
            $this->getDetail('http-login'),
            $this->getDetail('http-pass'));
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
              "--username %s ".
              "--password %s ".
              $pattern;
            array_unshift(
              $args,
              $this->getDetail('http-login'),
              $this->getDetail('http-pass'));
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
          $pattern = "git {$pattern}";
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

    switch ($this->getVersionControlSystem()) {
      case PhabricatorRepositoryType::REPOSITORY_TYPE_SVN:
        $pattern = "(cd %s && svn --non-interactive {$pattern})";
        array_unshift($args, $this->getLocalPath());
        break;
      case PhabricatorRepositoryType::REPOSITORY_TYPE_GIT:
        $pattern = "(cd %s && git {$pattern})";
        array_unshift($args, $this->getLocalPath());
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

  public static function loadAllByPHIDOrCallsign(array $names) {
    $repositories = array();
    foreach ($names as $name) {
      $repo = id(new PhabricatorRepository())->loadOneWhere(
        'phid = %s OR callsign = %s',
        $name,
        $name);
      if (!$repo) {
        throw new Exception(
          "No repository with PHID or callsign '{$name}' exists!");
      }
      $repositories[$repo->getID()] = $repo;
    }
    return $repositories;
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


  /**
   * Link external bug tracking system if defined.
   *
   * @param string Plain text.
   * @param string Commit identifier.
   * @return string Remarkup
   */
  public function linkBugtraq($message, $revision = null) {
    $bugtraq_url = PhabricatorEnv::getEnvConfig('bugtraq.url');
    list($bugtraq_re, $id_re) =
      PhabricatorEnv::getEnvConfig('bugtraq.logregex') +
      array('', '');

    switch ($this->getVersionControlSystem()) {
      case PhabricatorRepositoryType::REPOSITORY_TYPE_SVN:
        // TODO: Get bugtraq:logregex and bugtraq:url from SVN properties.
        break;
    }

    if (!$bugtraq_url || $bugtraq_re == '') {
      return $message;
    }

    $matches = null;
    $flags = PREG_SET_ORDER | PREG_OFFSET_CAPTURE;
    preg_match_all('('.$bugtraq_re.')', $message, $matches, $flags);
    foreach ($matches as $match) {
      list($all, $all_offset) = array_shift($match);

      if ($id_re != '') {
        // Match substrings with bug IDs
        preg_match_all('('.$id_re.')', $all, $match, PREG_OFFSET_CAPTURE);
        list(, $match) = $match;
      } else {
        $all_offset = 0;
      }

      foreach ($match as $val) {
        list($s, $offset) = $val;
        $message = substr_replace(
          $message,
          '[[ '.str_replace('%BUGID%', $s, $bugtraq_url).' | '.$s.' ]]',
          $offset + $all_offset,
          strlen($s));
      }
    }

    return $message;
  }


/* -(  PhabricatorPolicyInterface  )----------------------------------------- */


  public function getCapabilities() {
    return array(
      PhabricatorPolicyCapability::CAN_VIEW,
      PhabricatorPolicyCapability::CAN_EDIT,
    );
  }

  public function getPolicy($capability) {
    switch ($capability) {
      case PhabricatorPolicyCapability::CAN_VIEW:
        return PhabricatorPolicies::POLICY_USER;
      case PhabricatorPolicyCapability::CAN_EDIT:
        return PhabricatorPolicies::POLICY_ADMIN;
    }
  }

  public function hasAutomaticCapability($capability, PhabricatorUser $user) {
    return false;
  }

}
