<?php

/**
 * @task uri        Repository URI Management
 * @task autoclose  Autoclose
 */
final class PhabricatorRepository extends PhabricatorRepositoryDAO
  implements
    PhabricatorApplicationTransactionInterface,
    PhabricatorPolicyInterface,
    PhabricatorFlaggableInterface,
    PhabricatorMarkupInterface,
    PhabricatorDestructibleInterface,
    PhabricatorProjectInterface,
    PhabricatorSpacesInterface {

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
  const TABLE_PARENTS = 'repository_parents';
  const TABLE_COVERAGE = 'repository_coverage';

  const SERVE_OFF = 'off';
  const SERVE_READONLY = 'readonly';
  const SERVE_READWRITE = 'readwrite';

  const BECAUSE_REPOSITORY_IMPORTING = 'auto/importing';
  const BECAUSE_AUTOCLOSE_DISABLED = 'auto/disabled';
  const BECAUSE_NOT_ON_AUTOCLOSE_BRANCH = 'auto/nobranch';
  const BECAUSE_BRANCH_UNTRACKED = 'auto/notrack';
  const BECAUSE_BRANCH_NOT_AUTOCLOSE = 'auto/noclose';
  const BECAUSE_AUTOCLOSE_FORCED = 'auto/forced';

  protected $name;
  protected $callsign;
  protected $uuid;
  protected $viewPolicy;
  protected $editPolicy;
  protected $pushPolicy;

  protected $versionControlSystem;
  protected $details = array();
  protected $credentialPHID;
  protected $almanacServicePHID;
  protected $spacePHID;

  private $commitCount = self::ATTACHABLE;
  private $mostRecentCommit = self::ATTACHABLE;
  private $projectPHIDs = self::ATTACHABLE;

  public static function initializeNewRepository(PhabricatorUser $actor) {
    $app = id(new PhabricatorApplicationQuery())
      ->setViewer($actor)
      ->withClasses(array('PhabricatorDiffusionApplication'))
      ->executeOne();

    $view_policy = $app->getPolicy(DiffusionDefaultViewCapability::CAPABILITY);
    $edit_policy = $app->getPolicy(DiffusionDefaultEditCapability::CAPABILITY);
    $push_policy = $app->getPolicy(DiffusionDefaultPushCapability::CAPABILITY);

    $repository = id(new PhabricatorRepository())
      ->setViewPolicy($view_policy)
      ->setEditPolicy($edit_policy)
      ->setPushPolicy($push_policy)
      ->setSpacePHID($actor->getDefaultSpacePHID());

    // Put the repository in "Importing" mode until we finish
    // parsing it.
    $repository->setDetail('importing', true);

    return $repository;
  }

  protected function getConfiguration() {
    return array(
      self::CONFIG_AUX_PHID => true,
      self::CONFIG_SERIALIZATION => array(
        'details' => self::SERIALIZATION_JSON,
      ),
      self::CONFIG_COLUMN_SCHEMA => array(
        'name' => 'sort255',
        'callsign' => 'sort32',
        'versionControlSystem' => 'text32',
        'uuid' => 'text64?',
        'pushPolicy' => 'policy',
        'credentialPHID' => 'phid?',
        'almanacServicePHID' => 'phid?',
      ),
      self::CONFIG_KEY_SCHEMA => array(
        'key_phid' => null,
        'phid' => array(
          'columns' => array('phid'),
          'unique' => true,
        ),
        'callsign' => array(
          'columns' => array('callsign'),
          'unique' => true,
        ),
        'key_name' => array(
          'columns' => array('name(128)'),
        ),
        'key_vcs' => array(
          'columns' => array('versionControlSystem'),
        ),
      ),
    ) + parent::getConfiguration();
  }

  public function generatePHID() {
    return PhabricatorPHID::generateNewPHID(
      PhabricatorRepositoryRepositoryPHIDType::TYPECONST);
  }

  public function toDictionary() {
    return array(
      'id'          => $this->getID(),
      'name'        => $this->getName(),
      'phid'        => $this->getPHID(),
      'callsign'    => $this->getCallsign(),
      'monogram'    => $this->getMonogram(),
      'vcs'         => $this->getVersionControlSystem(),
      'uri'         => PhabricatorEnv::getProductionURI($this->getURI()),
      'remoteURI'   => (string)$this->getRemoteURI(),
      'description' => $this->getDetail('description'),
      'isActive'    => $this->isTracked(),
      'isHosted'    => $this->isHosted(),
      'isImporting' => $this->isImporting(),
      'encoding'    => $this->getDetail('encoding'),
      'staging' => array(
        'supported' => $this->supportsStaging(),
        'prefix' => 'phabricator',
        'uri' => $this->getStagingURI(),
      ),
    );
  }

  public function getMonogram() {
    return 'r'.$this->getCallsign();
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

  public function getSubversionBaseURI($commit = null) {
    $subpath = $this->getDetail('svn-subpath');
    if (!strlen($subpath)) {
      $subpath = null;
    }
    return $this->getSubversionPathURI($subpath, $commit);
  }

  public function getSubversionPathURI($path = null, $commit = null) {
    $vcs = $this->getVersionControlSystem();
    if ($vcs != PhabricatorRepositoryType::REPOSITORY_TYPE_SVN) {
      throw new Exception(pht('Not a subversion repository!'));
    }

    if ($this->isHosted()) {
      $uri = 'file://'.$this->getLocalPath();
    } else {
      $uri = $this->getDetail('remote-uri');
    }

    $uri = rtrim($uri, '/');

    if (strlen($path)) {
      $path = rawurlencode($path);
      $path = str_replace('%2F', '/', $path);
      $uri = $uri.'/'.ltrim($path, '/');
    }

    if ($path !== null || $commit !== null) {
      $uri .= '@';
    }

    if ($commit !== null) {
      $uri .= $commit;
    }

    return $uri;
  }

  public function attachProjectPHIDs(array $project_phids) {
    $this->projectPHIDs = $project_phids;
    return $this;
  }

  public function getProjectPHIDs() {
    return $this->assertAttached($this->projectPHIDs);
  }


  /**
   * Get the name of the directory this repository should clone or checkout
   * into. For example, if the repository name is "Example Repository", a
   * reasonable name might be "example-repository". This is used to help users
   * get reasonable results when cloning repositories, since they generally do
   * not want to clone into directories called "X/" or "Example Repository/".
   *
   * @return string
   */
  public function getCloneName() {
    $name = $this->getDetail('clone-name');

    // Make some reasonable effort to produce reasonable default directory
    // names from repository names.
    if (!strlen($name)) {
      $name = $this->getName();
      $name = phutil_utf8_strtolower($name);
      $name = preg_replace('@[/ -:]+@', '-', $name);
      $name = trim($name, '-');
      if (!strlen($name)) {
        $name = $this->getCallsign();
      }
    }

    return $name;
  }


/* -(  Remote Command Execution  )------------------------------------------- */


  public function execRemoteCommand($pattern /* , $arg, ... */) {
    $args = func_get_args();
    return $this->newRemoteCommandFuture($args)->resolve();
  }

  public function execxRemoteCommand($pattern /* , $arg, ... */) {
    $args = func_get_args();
    return $this->newRemoteCommandFuture($args)->resolvex();
  }

  public function getRemoteCommandFuture($pattern /* , $arg, ... */) {
    $args = func_get_args();
    return $this->newRemoteCommandFuture($args);
  }

  public function passthruRemoteCommand($pattern /* , $arg, ... */) {
    $args = func_get_args();
    return $this->newRemoteCommandPassthru($args)->execute();
  }

  private function newRemoteCommandFuture(array $argv) {
    $argv = $this->formatRemoteCommand($argv);
    $future = newv('ExecFuture', $argv);
    $future->setEnv($this->getRemoteCommandEnvironment());
    return $future;
  }

  private function newRemoteCommandPassthru(array $argv) {
    $argv = $this->formatRemoteCommand($argv);
    $passthru = newv('PhutilExecPassthru', $argv);
    $passthru->setEnv($this->getRemoteCommandEnvironment());
    return $passthru;
  }


/* -(  Local Command Execution  )-------------------------------------------- */


  public function execLocalCommand($pattern /* , $arg, ... */) {
    $args = func_get_args();
    return $this->newLocalCommandFuture($args)->resolve();
  }

  public function execxLocalCommand($pattern /* , $arg, ... */) {
    $args = func_get_args();
    return $this->newLocalCommandFuture($args)->resolvex();
  }

  public function getLocalCommandFuture($pattern /* , $arg, ... */) {
    $args = func_get_args();
    return $this->newLocalCommandFuture($args);
  }

  public function passthruLocalCommand($pattern /* , $arg, ... */) {
    $args = func_get_args();
    return $this->newLocalCommandPassthru($args)->execute();
  }

  private function newLocalCommandFuture(array $argv) {
    $this->assertLocalExists();

    $argv = $this->formatLocalCommand($argv);
    $future = newv('ExecFuture', $argv);
    $future->setEnv($this->getLocalCommandEnvironment());

    if ($this->usesLocalWorkingCopy()) {
      $future->setCWD($this->getLocalPath());
    }

    return $future;
  }

  private function newLocalCommandPassthru(array $argv) {
    $this->assertLocalExists();

    $argv = $this->formatLocalCommand($argv);
    $future = newv('PhutilExecPassthru', $argv);
    $future->setEnv($this->getLocalCommandEnvironment());

    if ($this->usesLocalWorkingCopy()) {
      $future->setCWD($this->getLocalPath());
    }

    return $future;
  }


/* -(  Command Infrastructure  )--------------------------------------------- */


  private function getSSHWrapper() {
    $root = dirname(phutil_get_library_root('phabricator'));
    return $root.'/bin/ssh-connect';
  }

  private function getCommonCommandEnvironment() {
    $env = array(
      // NOTE: Force the language to "en_US.UTF-8", which overrides locale
      // settings. This makes stuff print in English instead of, e.g., French,
      // so we can parse the output of some commands, error messages, etc.
      'LANG' => 'en_US.UTF-8',

      // Propagate PHABRICATOR_ENV explicitly. For discussion, see T4155.
      'PHABRICATOR_ENV' => PhabricatorEnv::getSelectedEnvironmentName(),
    );

    switch ($this->getVersionControlSystem()) {
      case PhabricatorRepositoryType::REPOSITORY_TYPE_SVN:
        break;
      case PhabricatorRepositoryType::REPOSITORY_TYPE_GIT:
        // NOTE: See T2965. Some time after Git 1.7.5.4, Git started fataling if
        // it can not read $HOME. For many users, $HOME points at /root (this
        // seems to be a default result of Apache setup). Instead, explicitly
        // point $HOME at a readable, empty directory so that Git looks for the
        // config file it's after, fails to locate it, and moves on. This is
        // really silly, but seems like the least damaging approach to
        // mitigating the issue.

        $root = dirname(phutil_get_library_root('phabricator'));
        $env['HOME'] = $root.'/support/empty/';
        break;
      case PhabricatorRepositoryType::REPOSITORY_TYPE_MERCURIAL:
        // NOTE: This overrides certain configuration, extensions, and settings
        // which make Mercurial commands do random unusual things.
        $env['HGPLAIN'] = 1;
        break;
      default:
        throw new Exception(pht('Unrecognized version control system.'));
    }

    return $env;
  }

  private function getLocalCommandEnvironment() {
    return $this->getCommonCommandEnvironment();
  }

  private function getRemoteCommandEnvironment() {
    $env = $this->getCommonCommandEnvironment();

    if ($this->shouldUseSSH()) {
      // NOTE: This is read by `bin/ssh-connect`, and tells it which credentials
      // to use.
      $env['PHABRICATOR_CREDENTIAL'] = $this->getCredentialPHID();
      switch ($this->getVersionControlSystem()) {
        case PhabricatorRepositoryType::REPOSITORY_TYPE_SVN:
          // Force SVN to use `bin/ssh-connect`.
          $env['SVN_SSH'] = $this->getSSHWrapper();
          break;
        case PhabricatorRepositoryType::REPOSITORY_TYPE_GIT:
          // Force Git to use `bin/ssh-connect`.
          $env['GIT_SSH'] = $this->getSSHWrapper();
          break;
        case PhabricatorRepositoryType::REPOSITORY_TYPE_MERCURIAL:
          // We force Mercurial through `bin/ssh-connect` too, but it uses a
          // command-line flag instead of an environmental variable.
          break;
        default:
          throw new Exception(pht('Unrecognized version control system.'));
      }
    }

    return $env;
  }

  private function formatRemoteCommand(array $args) {
    $pattern = $args[0];
    $args = array_slice($args, 1);

    switch ($this->getVersionControlSystem()) {
      case PhabricatorRepositoryType::REPOSITORY_TYPE_SVN:
        if ($this->shouldUseHTTP() || $this->shouldUseSVNProtocol()) {
          $flags = array();
          $flag_args = array();
          $flags[] = '--non-interactive';
          $flags[] = '--no-auth-cache';
          if ($this->shouldUseHTTP()) {
            $flags[] = '--trust-server-cert';
          }

          $credential_phid = $this->getCredentialPHID();
          if ($credential_phid) {
            $key = PassphrasePasswordKey::loadFromPHID(
              $credential_phid,
              PhabricatorUser::getOmnipotentUser());
            $flags[] = '--username %P';
            $flags[] = '--password %P';
            $flag_args[] = $key->getUsernameEnvelope();
            $flag_args[] = $key->getPasswordEnvelope();
          }

          $flags = implode(' ', $flags);
          $pattern = "svn {$flags} {$pattern}";
          $args = array_mergev(array($flag_args, $args));
        } else {
          $pattern = "svn --non-interactive {$pattern}";
        }
        break;
      case PhabricatorRepositoryType::REPOSITORY_TYPE_GIT:
        $pattern = "git {$pattern}";
        break;
      case PhabricatorRepositoryType::REPOSITORY_TYPE_MERCURIAL:
        if ($this->shouldUseSSH()) {
          $pattern = "hg --config ui.ssh=%s {$pattern}";
          array_unshift(
            $args,
            $this->getSSHWrapper());
        } else {
          $pattern = "hg {$pattern}";
        }
        break;
      default:
        throw new Exception(pht('Unrecognized version control system.'));
    }

    array_unshift($args, $pattern);

    return $args;
  }

  private function formatLocalCommand(array $args) {
    $pattern = $args[0];
    $args = array_slice($args, 1);

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
        throw new Exception(pht('Unrecognized version control system.'));
    }

    array_unshift($args, $pattern);

    return $args;
  }

  /**
   * Sanitize output of an `hg` command invoked with the `--debug` flag to make
   * it usable.
   *
   * @param string Output from `hg --debug ...`
   * @return string Usable output.
   */
  public static function filterMercurialDebugOutput($stdout) {
    // When hg commands are run with `--debug` and some config file isn't
    // trusted, Mercurial prints out a warning to stdout, twice, after Feb 2011.
    //
    // http://selenic.com/pipermail/mercurial-devel/2011-February/028541.html
    //
    // After Jan 2015, it may also fail to write to a revision branch cache.

    $ignore = array(
      'ignoring untrusted configuration option',
      "couldn't write revision branch cache:",
    );

    foreach ($ignore as $key => $pattern) {
      $ignore[$key] = preg_quote($pattern, '/');
    }

    $ignore = '('.implode('|', $ignore).')';

    $lines = preg_split('/(?<=\n)/', $stdout);
    $regex = '/'.$ignore.'.*\n$/';

    foreach ($lines as $key => $line) {
      $lines[$key] = preg_replace($regex, '', $line);
    }

    return implode('', $lines);
  }

  public function getURI() {
    return '/diffusion/'.$this->getCallsign().'/';
  }

  public function getNormalizedPath() {
    $uri = (string)$this->getCloneURIObject();

    switch ($this->getVersionControlSystem()) {
      case PhabricatorRepositoryType::REPOSITORY_TYPE_GIT:
        $normalized_uri = new PhabricatorRepositoryURINormalizer(
          PhabricatorRepositoryURINormalizer::TYPE_GIT,
          $uri);
        break;
      case PhabricatorRepositoryType::REPOSITORY_TYPE_SVN:
        $normalized_uri = new PhabricatorRepositoryURINormalizer(
          PhabricatorRepositoryURINormalizer::TYPE_SVN,
          $uri);
        break;
      case PhabricatorRepositoryType::REPOSITORY_TYPE_MERCURIAL:
        $normalized_uri = new PhabricatorRepositoryURINormalizer(
          PhabricatorRepositoryURINormalizer::TYPE_MERCURIAL,
          $uri);
        break;
      default:
        throw new Exception(pht('Unrecognized version control system.'));
    }

    return $normalized_uri->getNormalizedPath();
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
    if (!$use_filter) {
      // If this VCS doesn't use filters, pass everything through.
      return true;
    }


    $filter = $this->getDetail($filter_key, array());

    // If there's no filter set, let everything through.
    if (!$filter) {
      return true;
    }

    // If this branch isn't literally named `regexp(...)`, and it's in the
    // filter list, let it through.
    if (isset($filter[$branch])) {
      if (self::extractBranchRegexp($branch) === null) {
        return true;
      }
    }

    // If the branch matches a regexp, let it through.
    foreach ($filter as $pattern => $ignored) {
      $regexp = self::extractBranchRegexp($pattern);
      if ($regexp !== null) {
        if (preg_match($regexp, $branch)) {
          return true;
        }
      }
    }

    // Nothing matched, so filter this branch out.
    return false;
  }

  public static function extractBranchRegexp($pattern) {
    $matches = null;
    if (preg_match('/^regexp\\((.*)\\)\z/', $pattern, $matches)) {
      return $matches[1];
    }
    return null;
  }

  public function shouldTrackBranch($branch) {
    return $this->isBranchInFilter($branch, 'branch-filter');
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


  /**
   * Should this repository publish feed, notifications, audits, and email?
   *
   * We do not publish information about repositories during initial import,
   * or if the repository has been set not to publish.
   */
  public function shouldPublish() {
    if ($this->isImporting()) {
      return false;
    }

    if ($this->getDetail('disable-herald')) {
      return false;
    }

    return true;
  }


/* -(  Autoclose  )---------------------------------------------------------- */


  /**
   * Determine if autoclose is active for a branch.
   *
   * For more details about why, use @{method:shouldSkipAutocloseBranch}.
   *
   * @param string Branch name to check.
   * @return bool True if autoclose is active for the branch.
   * @task autoclose
   */
  public function shouldAutocloseBranch($branch) {
    return ($this->shouldSkipAutocloseBranch($branch) === null);
  }

  /**
   * Determine if autoclose is active for a commit.
   *
   * For more details about why, use @{method:shouldSkipAutocloseCommit}.
   *
   * @param PhabricatorRepositoryCommit Commit to check.
   * @return bool True if autoclose is active for the commit.
   * @task autoclose
   */
  public function shouldAutocloseCommit(PhabricatorRepositoryCommit $commit) {
    return ($this->shouldSkipAutocloseCommit($commit) === null);
  }


  /**
   * Determine why autoclose should be skipped for a branch.
   *
   * This method gives a detailed reason why autoclose will be skipped. To
   * perform a simple test, use @{method:shouldAutocloseBranch}.
   *
   * @param string Branch name to check.
   * @return const|null Constant identifying reason to skip this branch, or null
   *   if autoclose is active.
   * @task autoclose
   */
  public function shouldSkipAutocloseBranch($branch) {
    $all_reason = $this->shouldSkipAllAutoclose();
    if ($all_reason) {
      return $all_reason;
    }

    if (!$this->shouldTrackBranch($branch)) {
      return self::BECAUSE_BRANCH_UNTRACKED;
    }

    if (!$this->isBranchInFilter($branch, 'close-commits-filter')) {
      return self::BECAUSE_BRANCH_NOT_AUTOCLOSE;
    }

    return null;
  }


  /**
   * Determine why autoclose should be skipped for a commit.
   *
   * This method gives a detailed reason why autoclose will be skipped. To
   * perform a simple test, use @{method:shouldAutocloseCommit}.
   *
   * @param PhabricatorRepositoryCommit Commit to check.
   * @return const|null Constant identifying reason to skip this commit, or null
   *   if autoclose is active.
   * @task autoclose
   */
  public function shouldSkipAutocloseCommit(
    PhabricatorRepositoryCommit $commit) {

    $all_reason = $this->shouldSkipAllAutoclose();
    if ($all_reason) {
      return $all_reason;
    }

    switch ($this->getVersionControlSystem()) {
      case PhabricatorRepositoryType::REPOSITORY_TYPE_SVN:
      case PhabricatorRepositoryType::REPOSITORY_TYPE_MERCURIAL:
        return null;
      case PhabricatorRepositoryType::REPOSITORY_TYPE_GIT:
        break;
      default:
        throw new Exception(pht('Unrecognized version control system.'));
    }

    $closeable_flag = PhabricatorRepositoryCommit::IMPORTED_CLOSEABLE;
    if (!$commit->isPartiallyImported($closeable_flag)) {
      return self::BECAUSE_NOT_ON_AUTOCLOSE_BRANCH;
    }

    return null;
  }


  /**
   * Determine why all autoclose operations should be skipped for this
   * repository.
   *
   * @return const|null Constant identifying reason to skip all autoclose
   *   operations, or null if autoclose operations are not blocked at the
   *   repository level.
   * @task autoclose
   */
  private function shouldSkipAllAutoclose() {
    if ($this->isImporting()) {
      return self::BECAUSE_REPOSITORY_IMPORTING;
    }

    if ($this->getDetail('disable-autoclose', false)) {
      return self::BECAUSE_AUTOCLOSE_DISABLED;
    }

    return null;
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
   * Get the remote URI for this repository, including credentials if they're
   * used by this repository.
   *
   * @return PhutilOpaqueEnvelope URI, possibly including credentials.
   * @task uri
   */
  public function getRemoteURIEnvelope() {
    $uri = $this->getRemoteURIObject();

    $remote_protocol = $this->getRemoteProtocol();
    if ($remote_protocol == 'http' || $remote_protocol == 'https') {
      // For SVN, we use `--username` and `--password` flags separately, so
      // don't add any credentials here.
      if (!$this->isSVN()) {
        $credential_phid = $this->getCredentialPHID();
        if ($credential_phid) {
          $key = PassphrasePasswordKey::loadFromPHID(
            $credential_phid,
            PhabricatorUser::getOmnipotentUser());

          $uri->setUser($key->getUsernameEnvelope()->openEnvelope());
          $uri->setPass($key->getPasswordEnvelope()->openEnvelope());
        }
      }
    }

    return new PhutilOpaqueEnvelope((string)$uri);
  }


  /**
   * Get the clone (or checkout) URI for this repository, without authentication
   * information.
   *
   * @return string Repository URI.
   * @task uri
   */
  public function getPublicCloneURI() {
    $uri = $this->getCloneURIObject();

    // Make sure we don't leak anything if this repo is using HTTP Basic Auth
    // with the credentials in the URI or something zany like that.

    // If repository is not accessed over SSH we remove both username and
    // password.
    if (!$this->isHosted()) {
      if (!$this->shouldUseSSH()) {
        $uri->setUser(null);

        // This might be a Git URI or a normal URI. If it's Git, there's no
        // password support.
        if ($uri instanceof PhutilURI) {
          $uri->setPass(null);
        }
      }
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
  public function getRemoteURIObject() {
    $raw_uri = $this->getDetail('remote-uri');
    if (!$raw_uri) {
      return new PhutilURI('');
    }

    if (!strncmp($raw_uri, '/', 1)) {
      return new PhutilURI('file://'.$raw_uri);
    }

    $uri = new PhutilURI($raw_uri);
    if ($uri->getProtocol()) {
      return $uri;
    }

    $uri = new PhutilGitURI($raw_uri);
    if ($uri->getDomain()) {
      return $uri;
    }

    throw new Exception(pht("Remote URI '%s' could not be parsed!", $raw_uri));
  }


  /**
   * Get the "best" clone/checkout URI for this repository, on any protocol.
   */
  public function getCloneURIObject() {
    if (!$this->isHosted()) {
      if ($this->isSVN()) {
        // Make sure we pick up the "Import Only" path for Subversion, so
        // the user clones the repository starting at the correct path, not
        // from the root.
        $base_uri = $this->getSubversionBaseURI();
        $base_uri = new PhutilURI($base_uri);
        $path = $base_uri->getPath();
        if (!$path) {
          $path = '/';
        }

        // If the trailing "@" is not required to escape the URI, strip it for
        // readability.
        if (!preg_match('/@.*@/', $path)) {
          $path = rtrim($path, '@');
        }

        $base_uri->setPath($path);
        return $base_uri;
      } else {
        return $this->getRemoteURIObject();
      }
    }

    // Choose the best URI: pick a read/write URI over a URI which is not
    // read/write, and SSH over HTTP.

    $serve_ssh = $this->getServeOverSSH();
    $serve_http = $this->getServeOverHTTP();

    if ($serve_ssh === self::SERVE_READWRITE) {
      return $this->getSSHCloneURIObject();
    } else if ($serve_http === self::SERVE_READWRITE) {
      return $this->getHTTPCloneURIObject();
    } else if ($serve_ssh !== self::SERVE_OFF) {
      return $this->getSSHCloneURIObject();
    } else if ($serve_http !== self::SERVE_OFF) {
      return $this->getHTTPCloneURIObject();
    } else {
      return null;
    }
  }


  /**
   * Get the repository's SSH clone/checkout URI, if one exists.
   */
  public function getSSHCloneURIObject() {
    if (!$this->isHosted()) {
      if ($this->shouldUseSSH()) {
        return $this->getRemoteURIObject();
      } else {
        return null;
      }
    }

    $serve_ssh = $this->getServeOverSSH();
    if ($serve_ssh === self::SERVE_OFF) {
      return null;
    }

    $uri = new PhutilURI(PhabricatorEnv::getProductionURI($this->getURI()));

    if ($this->isSVN()) {
      $uri->setProtocol('svn+ssh');
    } else {
      $uri->setProtocol('ssh');
    }

    if ($this->isGit()) {
      $uri->setPath($uri->getPath().$this->getCloneName().'.git');
    } else if ($this->isHg()) {
      $uri->setPath($uri->getPath().$this->getCloneName().'/');
    }

    $ssh_user = PhabricatorEnv::getEnvConfig('diffusion.ssh-user');
    if ($ssh_user) {
      $uri->setUser($ssh_user);
    }

    $ssh_host = PhabricatorEnv::getEnvConfig('diffusion.ssh-host');
    if (strlen($ssh_host)) {
      $uri->setDomain($ssh_host);
    }

    $uri->setPort(PhabricatorEnv::getEnvConfig('diffusion.ssh-port'));

    return $uri;
  }


  /**
   * Get the repository's HTTP clone/checkout URI, if one exists.
   */
  public function getHTTPCloneURIObject() {
    if (!$this->isHosted()) {
      if ($this->shouldUseHTTP()) {
        return $this->getRemoteURIObject();
      } else {
        return null;
      }
    }

    $serve_http = $this->getServeOverHTTP();
    if ($serve_http === self::SERVE_OFF) {
      return null;
    }

    $uri = PhabricatorEnv::getProductionURI($this->getURI());
    $uri = new PhutilURI($uri);

    if ($this->isGit()) {
      $uri->setPath($uri->getPath().$this->getCloneName().'.git');
    } else if ($this->isHg()) {
      $uri->setPath($uri->getPath().$this->getCloneName().'/');
    }

    return $uri;
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
      return true;
    }

    return false;
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
    return ($protocol == 'http' || $protocol == 'https');
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
    return ($protocol == 'svn');
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
        $project->delete();
      }

      queryfx(
        $this->establishConnection('w'),
        'DELETE FROM %T WHERE repositoryPHID = %s',
        id(new PhabricatorRepositorySymbol())->getTableName(),
        $this->getPHID());

      $commits = id(new PhabricatorRepositoryCommit())
        ->loadAllWhere('repositoryID = %d', $this->getID());
      foreach ($commits as $commit) {
        // note PhabricatorRepositoryAuditRequests and
        // PhabricatorRepositoryCommitData are deleted here too.
        $commit->delete();
      }

      $mirrors = id(new PhabricatorRepositoryMirror())
        ->loadAllWhere('repositoryPHID = %s', $this->getPHID());
      foreach ($mirrors as $mirror) {
        $mirror->delete();
      }

      $ref_cursors = id(new PhabricatorRepositoryRefCursor())
        ->loadAllWhere('repositoryPHID = %s', $this->getPHID());
      foreach ($ref_cursors as $cursor) {
        $cursor->delete();
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
    if ($this->isSVN()) {
      return self::SERVE_OFF;
    }
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

  public function getHookDirectories() {
    $directories = array();
    if (!$this->isHosted()) {
      return $directories;
    }

    $root = $this->getLocalPath();

    switch ($this->getVersionControlSystem()) {
      case PhabricatorRepositoryType::REPOSITORY_TYPE_GIT:
        if ($this->isWorkingCopyBare()) {
          $directories[] = $root.'/hooks/pre-receive-phabricator.d/';
        } else {
          $directories[] = $root.'/.git/hooks/pre-receive-phabricator.d/';
        }
        break;
      case PhabricatorRepositoryType::REPOSITORY_TYPE_SVN:
        $directories[] = $root.'/hooks/pre-commit-phabricator.d/';
        break;
      case PhabricatorRepositoryType::REPOSITORY_TYPE_MERCURIAL:
        // NOTE: We don't support custom Mercurial hooks for now because they're
        // messy and we can't easily just drop a `hooks.d/` directory next to
        // the hooks.
        break;
    }

    return $directories;
  }

  public function canDestroyWorkingCopy() {
    if ($this->isHosted()) {
      // Never destroy hosted working copies.
      return false;
    }

    $default_path = PhabricatorEnv::getEnvConfig(
      'repository.default-local-path');
    return Filesystem::isDescendant($this->getLocalPath(), $default_path);
  }

  public function canUsePathTree() {
    return !$this->isSVN();
  }

  public function canMirror() {
    if ($this->isGit() || $this->isHg()) {
      return true;
    }

    return false;
  }

  public function canAllowDangerousChanges() {
    if (!$this->isHosted()) {
      return false;
    }

    if ($this->isGit() || $this->isHg()) {
      return true;
    }

    return false;
  }

  public function shouldAllowDangerousChanges() {
    return (bool)$this->getDetail('allow-dangerous-changes');
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

  public static function getRemoteURIProtocol($raw_uri) {
    $uri = new PhutilURI($raw_uri);
    if ($uri->getProtocol()) {
      return strtolower($uri->getProtocol());
    }

    $git_uri = new PhutilGitURI($raw_uri);
    if (strlen($git_uri->getDomain()) && strlen($git_uri->getPath())) {
      return 'ssh';
    }

    return null;
  }

  public static function assertValidRemoteURI($uri) {
    if (trim($uri) != $uri) {
      throw new Exception(
        pht('The remote URI has leading or trailing whitespace.'));
    }

    $protocol = self::getRemoteURIProtocol($uri);

    // Catch confusion between Git/SCP-style URIs and normal URIs. See T3619
    // for discussion. This is usually a user adding "ssh://" to an implicit
    // SSH Git URI.
    if ($protocol == 'ssh') {
      if (preg_match('(^[^:@]+://[^/:]+:[^\d])', $uri)) {
        throw new Exception(
          pht(
            "The remote URI is not formatted correctly. Remote URIs ".
            "with an explicit protocol should be in the form ".
            "'%s', not '%s'. The '%s' syntax is only valid in SCP-style URIs.",
            'proto://domain/path',
            'proto://domain:/path',
            ':/path'));
      }
    }

    switch ($protocol) {
      case 'ssh':
      case 'http':
      case 'https':
      case 'git':
      case 'svn':
      case 'svn+ssh':
        break;
      default:
        // NOTE: We're explicitly rejecting 'file://' because it can be
        // used to clone from the working copy of another repository on disk
        // that you don't normally have permission to access.

        throw new Exception(
          pht(
            "The URI protocol is unrecognized. It should begin ".
            "'%s', '%s', '%s', '%s', '%s', '%s', or be in the form '%s'.",
            'ssh://',
            'http://',
            'https://',
            'git://',
            'svn://',
            'svn+ssh://',
            'git@domain.com:path'));
    }

    return true;
  }


  /**
   * Load the pull frequency for this repository, based on the time since the
   * last activity.
   *
   * We pull rarely used repositories less frequently. This finds the most
   * recent commit which is older than the current time (which prevents us from
   * spinning on repositories with a silly commit post-dated to some time in
   * 2037). We adjust the pull frequency based on when the most recent commit
   * occurred.
   *
   * @param   int   The minimum update interval to use, in seconds.
   * @return  int   Repository update interval, in seconds.
   */
  public function loadUpdateInterval($minimum = 15) {
    // If a repository is still importing, always pull it as frequently as
    // possible. This prevents us from hanging for a long time at 99.9% when
    // importing an inactive repository.
    if ($this->isImporting()) {
      return $minimum;
    }

    $window_start = (PhabricatorTime::getNow() + $minimum);

    $table = id(new PhabricatorRepositoryCommit());
    $last_commit = queryfx_one(
      $table->establishConnection('r'),
      'SELECT epoch FROM %T
        WHERE repositoryID = %d AND epoch <= %d
        ORDER BY epoch DESC LIMIT 1',
      $table->getTableName(),
      $this->getID(),
      $window_start);
    if ($last_commit) {
      $time_since_commit = ($window_start - $last_commit['epoch']);

      $last_few_days = phutil_units('3 days in seconds');

      if ($time_since_commit <= $last_few_days) {
        // For repositories with activity in the recent past, we wait one
        // extra second for every 10 minutes since the last commit. This
        // shorter backoff is intended to handle weekends and other short
        // breaks from development.
        $smart_wait = ($time_since_commit / 600);
      } else {
        // For repositories without recent activity, we wait one extra second
        // for every 4 minutes since the last commit. This longer backoff
        // handles rarely used repositories, up to the maximum.
        $smart_wait = ($time_since_commit / 240);
      }

      // We'll never wait more than 6 hours to pull a repository.
      $longest_wait = phutil_units('6 hours in seconds');
      $smart_wait = min($smart_wait, $longest_wait);

      $smart_wait = max($minimum, $smart_wait);
    } else {
      $smart_wait = $minimum;
    }

    return $smart_wait;
  }


  /**
   * Retrieve the sevice URI for the device hosting this repository.
   *
   * See @{method:newConduitClient} for a general discussion of interacting
   * with repository services. This method provides lower-level resolution of
   * services, returning raw URIs.
   *
   * @param PhabricatorUser Viewing user.
   * @param bool `true` to throw if a remote URI would be returned.
   * @param list<string> List of allowable protocols.
   * @return string|null URI, or `null` for local repositories.
   */
  public function getAlmanacServiceURI(
    PhabricatorUser $viewer,
    $never_proxy,
    array $protocols) {

    $service_phid = $this->getAlmanacServicePHID();
    if (!$service_phid) {
      // No service, so this is a local repository.
      return null;
    }

    $service = id(new AlmanacServiceQuery())
      ->setViewer(PhabricatorUser::getOmnipotentUser())
      ->withPHIDs(array($service_phid))
      ->needBindings(true)
      ->executeOne();
    if (!$service) {
      throw new Exception(
        pht(
          'The Almanac service for this repository is invalid or could not '.
          'be loaded.'));
    }

    $service_type = $service->getServiceType();
    if (!($service_type instanceof AlmanacClusterRepositoryServiceType)) {
      throw new Exception(
        pht(
          'The Almanac service for this repository does not have the correct '.
          'service type.'));
    }

    $bindings = $service->getBindings();
    if (!$bindings) {
      throw new Exception(
        pht(
          'The Almanac service for this repository is not bound to any '.
          'interfaces.'));
    }

    $local_device = AlmanacKeys::getDeviceID();

    if ($never_proxy && !$local_device) {
      throw new Exception(
        pht(
          'Unable to handle proxied service request. This device is not '.
          'registered, so it can not identify local services. Register '.
          'this device before sending requests here.'));
    }

    $protocol_map = array_fuse($protocols);

    $uris = array();
    foreach ($bindings as $binding) {
      $iface = $binding->getInterface();

      // If we're never proxying this and it's locally satisfiable, return
      // `null` to tell the caller to handle it locally. If we're allowed to
      // proxy, we skip this check and may proxy the request to ourselves.
      // (That proxied request will end up here with proxying forbidden,
      // return `null`, and then the request will actually run.)

      if ($local_device && $never_proxy) {
        if ($iface->getDevice()->getName() == $local_device) {
          return null;
        }
      }

      $protocol = $binding->getAlmanacPropertyValue('protocol');
      if ($protocol === null) {
        $protocol = 'https';
      }

      if (empty($protocol_map[$protocol])) {
        continue;
      }

      $uris[] = $protocol.'://'.$iface->renderDisplayAddress().'/';
    }

    if (!$uris) {
      throw new Exception(
        pht(
          'The Almanac service for this repository is not bound to any '.
          'interfaces which support the required protocols (%s).',
          implode(', ', $protocols)));
    }

    if ($never_proxy) {
      throw new Exception(
        pht(
          'Refusing to proxy a repository request from a cluster host. '.
          'Cluster hosts must correctly route their intracluster requests.'));
    }

    shuffle($uris);
    return head($uris);
  }


  /**
   * Build a new Conduit client in order to make a service call to this
   * repository.
   *
   * If the repository is hosted locally, this method may return `null`. The
   * caller should use `ConduitCall` or other local logic to complete the
   * request.
   *
   * By default, we will return a @{class:ConduitClient} for any repository with
   * a service, even if that service is on the current device.
   *
   * We do this because this configuration does not make very much sense in a
   * production context, but is very common in a test/development context
   * (where the developer's machine is both the web host and the repository
   * service). By proxying in development, we get more consistent behavior
   * between development and production, and don't have a major untested
   * codepath.
   *
   * The `$never_proxy` parameter can be used to prevent this local proxying.
   * If the flag is passed:
   *
   *   - The method will return `null` (implying a local service call)
   *     if the repository service is hosted on the current device.
   *   - The method will throw if it would need to return a client.
   *
   * This is used to prevent loops in Conduit: the first request will proxy,
   * even in development, but the second request will be identified as a
   * cluster request and forced not to proxy.
   *
   * For lower-level service resolution, see @{method:getAlmanacServiceURI}.
   *
   * @param PhabricatorUser Viewing user.
   * @param bool `true` to throw if a client would be returned.
   * @return ConduitClient|null Client, or `null` for local repositories.
   */
  public function newConduitClient(
    PhabricatorUser $viewer,
    $never_proxy = false) {

    $uri = $this->getAlmanacServiceURI(
      $viewer,
      $never_proxy,
      array(
        'http',
        'https',
      ));
    if ($uri === null) {
      return null;
    }

    $domain = id(new PhutilURI(PhabricatorEnv::getURI('/')))->getDomain();

    $client = id(new ConduitClient($uri))
      ->setHost($domain);

    if ($viewer->isOmnipotent()) {
      // If the caller is the omnipotent user (normally, a daemon), we will
      // sign the request with this host's asymmetric keypair.

      $public_path = AlmanacKeys::getKeyPath('device.pub');
      try {
        $public_key = Filesystem::readFile($public_path);
      } catch (Exception $ex) {
        throw new PhutilAggregateException(
          pht(
            'Unable to read device public key while attempting to make '.
            'authenticated method call within the Phabricator cluster. '.
            'Use `%s` to register keys for this device. Exception: %s',
            'bin/almanac register',
            $ex->getMessage()),
          array($ex));
      }

      $private_path = AlmanacKeys::getKeyPath('device.key');
      try {
        $private_key = Filesystem::readFile($private_path);
        $private_key = new PhutilOpaqueEnvelope($private_key);
      } catch (Exception $ex) {
        throw new PhutilAggregateException(
          pht(
            'Unable to read device private key while attempting to make '.
            'authenticated method call within the Phabricator cluster. '.
            'Use `%s` to register keys for this device. Exception: %s',
            'bin/almanac register',
            $ex->getMessage()),
          array($ex));
      }

      $client->setSigningKeys($public_key, $private_key);
    } else {
      // If the caller is a normal user, we generate or retrieve a cluster
      // API token.

      $token = PhabricatorConduitToken::loadClusterTokenForUser($viewer);
      if ($token) {
        $client->setConduitToken($token->getToken());
      }
    }

    return $client;
  }


/* -(  Symbols  )-------------------------------------------------------------*/

  public function getSymbolSources() {
    return $this->getDetail('symbol-sources', array());
  }

  public function getSymbolLanguages() {
    return $this->getDetail('symbol-languages', array());
  }


/* -(  Staging  )-------------------------------------------------------------*/


  public function supportsStaging() {
    return $this->isGit();
  }


  public function getStagingURI() {
    if (!$this->supportsStaging()) {
      return null;
    }
    return $this->getDetail('staging-uri', null);
  }


/* -(  PhabricatorApplicationTransactionInterface  )------------------------- */


  public function getApplicationTransactionEditor() {
    return new PhabricatorRepositoryEditor();
  }

  public function getApplicationTransactionObject() {
    return $this;
  }

  public function getApplicationTransactionTemplate() {
    return new PhabricatorRepositoryTransaction();
  }

  public function willRenderTimeline(
    PhabricatorApplicationTransactionView $timeline,
    AphrontRequest $request) {

    return $timeline;
  }


/* -(  PhabricatorPolicyInterface  )----------------------------------------- */


  public function getCapabilities() {
    return array(
      PhabricatorPolicyCapability::CAN_VIEW,
      PhabricatorPolicyCapability::CAN_EDIT,
      DiffusionPushCapability::CAPABILITY,
    );
  }

  public function getPolicy($capability) {
    switch ($capability) {
      case PhabricatorPolicyCapability::CAN_VIEW:
        return $this->getViewPolicy();
      case PhabricatorPolicyCapability::CAN_EDIT:
        return $this->getEditPolicy();
      case DiffusionPushCapability::CAPABILITY:
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


/* -(  PhabricatorDestructibleInterface  )----------------------------------- */


  public function destroyObjectPermanently(
    PhabricatorDestructionEngine $engine) {

    $this->openTransaction();

      $this->delete();

      $books = id(new DivinerBookQuery())
        ->setViewer($engine->getViewer())
        ->withRepositoryPHIDs(array($this->getPHID()))
        ->execute();
      foreach ($books as $book) {
        $engine->destroyObject($book);
      }

      $atoms = id(new DivinerAtomQuery())
        ->setViewer($engine->getViewer())
        ->withRepositoryPHIDs(array($this->getPHID()))
        ->execute();
      foreach ($atoms as $atom) {
        $engine->destroyObject($atom);
      }

    $this->saveTransaction();
  }


/* -(  PhabricatorSpacesInterface  )----------------------------------------- */


  public function getSpacePHID() {
    return $this->spacePHID;
  }

}
