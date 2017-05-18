<?php

/**
 * @task uri        Repository URI Management
 * @task autoclose  Autoclose
 * @task sync       Cluster Synchronization
 */
final class PhabricatorRepository extends PhabricatorRepositoryDAO
  implements
    PhabricatorApplicationTransactionInterface,
    PhabricatorPolicyInterface,
    PhabricatorFlaggableInterface,
    PhabricatorMarkupInterface,
    PhabricatorDestructibleInterface,
    PhabricatorProjectInterface,
    PhabricatorSpacesInterface,
    PhabricatorConduitResultInterface,
    PhabricatorFulltextInterface {

  /**
   * Shortest hash we'll recognize in raw "a829f32" form.
   */
  const MINIMUM_UNQUALIFIED_HASH = 7;

  /**
   * Shortest hash we'll recognize in qualified "rXab7ef2f8" form.
   */
  const MINIMUM_QUALIFIED_HASH = 5;

  /**
   * Minimum number of commits to an empty repository to trigger "import" mode.
   */
  const IMPORT_THRESHOLD = 7;

  const TABLE_PATH = 'repository_path';
  const TABLE_PATHCHANGE = 'repository_pathchange';
  const TABLE_FILESYSTEM = 'repository_filesystem';
  const TABLE_SUMMARY = 'repository_summary';
  const TABLE_LINTMESSAGE = 'repository_lintmessage';
  const TABLE_PARENTS = 'repository_parents';
  const TABLE_COVERAGE = 'repository_coverage';

  const BECAUSE_REPOSITORY_IMPORTING = 'auto/importing';
  const BECAUSE_AUTOCLOSE_DISABLED = 'auto/disabled';
  const BECAUSE_NOT_ON_AUTOCLOSE_BRANCH = 'auto/nobranch';
  const BECAUSE_BRANCH_UNTRACKED = 'auto/notrack';
  const BECAUSE_BRANCH_NOT_AUTOCLOSE = 'auto/noclose';
  const BECAUSE_AUTOCLOSE_FORCED = 'auto/forced';

  const STATUS_ACTIVE = 'active';
  const STATUS_INACTIVE = 'inactive';

  protected $name;
  protected $callsign;
  protected $repositorySlug;
  protected $uuid;
  protected $viewPolicy;
  protected $editPolicy;
  protected $pushPolicy;

  protected $versionControlSystem;
  protected $details = array();
  protected $credentialPHID;
  protected $almanacServicePHID;
  protected $spacePHID;
  protected $localPath;

  private $commitCount = self::ATTACHABLE;
  private $mostRecentCommit = self::ATTACHABLE;
  private $projectPHIDs = self::ATTACHABLE;
  private $uris = self::ATTACHABLE;


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
        'callsign' => 'sort32?',
        'repositorySlug' => 'sort64?',
        'versionControlSystem' => 'text32',
        'uuid' => 'text64?',
        'pushPolicy' => 'policy',
        'credentialPHID' => 'phid?',
        'almanacServicePHID' => 'phid?',
        'localPath' => 'text128?',
      ),
      self::CONFIG_KEY_SCHEMA => array(
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
        'key_slug' => array(
          'columns' => array('repositorySlug'),
          'unique' => true,
        ),
        'key_local' => array(
          'columns' => array('localPath'),
          'unique' => true,
        ),
      ),
    ) + parent::getConfiguration();
  }

  public function generatePHID() {
    return PhabricatorPHID::generateNewPHID(
      PhabricatorRepositoryRepositoryPHIDType::TYPECONST);
  }

  public static function getStatusMap() {
    return array(
      self::STATUS_ACTIVE => array(
        'name' => pht('Active'),
        'isTracked' => 1,
      ),
      self::STATUS_INACTIVE => array(
        'name' => pht('Inactive'),
        'isTracked' => 0,
      ),
    );
  }

  public static function getStatusNameMap() {
    return ipull(self::getStatusMap(), 'name');
  }

  public function getStatus() {
    if ($this->isTracked()) {
      return self::STATUS_ACTIVE;
    } else {
      return self::STATUS_INACTIVE;
    }
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
      'encoding'    => $this->getDefaultTextEncoding(),
      'staging' => array(
        'supported' => $this->supportsStaging(),
        'prefix' => 'phabricator',
        'uri' => $this->getStagingURI(),
      ),
    );
  }

  public function getDefaultTextEncoding() {
    return $this->getDetail('encoding', 'UTF-8');
  }

  public function getMonogram() {
    $callsign = $this->getCallsign();
    if (strlen($callsign)) {
      return "r{$callsign}";
    }

    $id = $this->getID();
    return "R{$id}";
  }

  public function getDisplayName() {
    $slug = $this->getRepositorySlug();
    if (strlen($slug)) {
      return $slug;
    }

    return $this->getMonogram();
  }

  public function getAllMonograms() {
    $monograms = array();

    $monograms[] = 'R'.$this->getID();

    $callsign = $this->getCallsign();
    if (strlen($callsign)) {
      $monograms[] = 'r'.$callsign;
    }

    return $monograms;
  }

  public function setLocalPath($path) {
    // Convert any extra slashes ("//") in the path to a single slash ("/").
    $path = preg_replace('(//+)', '/', $path);

    return parent::setLocalPath($path);
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
    $name = $this->getRepositorySlug();

    // Make some reasonable effort to produce reasonable default directory
    // names from repository names.
    if (!strlen($name)) {
      $name = $this->getName();
      $name = phutil_utf8_strtolower($name);
      $name = preg_replace('@[/ -:<>]+@', '-', $name);
      $name = trim($name, '-');
      if (!strlen($name)) {
        $name = $this->getCallsign();
      }
    }

    return $name;
  }

  public static function isValidRepositorySlug($slug) {
    try {
      self::assertValidRepositorySlug($slug);
      return true;
    } catch (Exception $ex) {
      return false;
    }
  }

  public static function assertValidRepositorySlug($slug) {
    if (!strlen($slug)) {
      throw new Exception(
        pht(
          'The empty string is not a valid repository short name. '.
          'Repository short names must be at least one character long.'));
    }

    if (strlen($slug) > 64) {
      throw new Exception(
        pht(
          'The name "%s" is not a valid repository short name. Repository '.
          'short names must not be longer than 64 characters.',
          $slug));
    }

    if (preg_match('/[^a-zA-Z0-9._-]/', $slug)) {
      throw new Exception(
        pht(
          'The name "%s" is not a valid repository short name. Repository '.
          'short names may only contain letters, numbers, periods, hyphens '.
          'and underscores.',
          $slug));
    }

    if (!preg_match('/^[a-zA-Z0-9]/', $slug)) {
      throw new Exception(
        pht(
          'The name "%s" is not a valid repository short name. Repository '.
          'short names must begin with a letter or number.',
          $slug));
    }

    if (!preg_match('/[a-zA-Z0-9]\z/', $slug)) {
      throw new Exception(
        pht(
          'The name "%s" is not a valid repository short name. Repository '.
          'short names must end with a letter or number.',
          $slug));
    }

    if (preg_match('/__|--|\\.\\./', $slug)) {
      throw new Exception(
        pht(
          'The name "%s" is not a valid repository short name. Repository '.
          'short names must not contain multiple consecutive underscores, '.
          'hyphens, or periods.',
          $slug));
    }

    if (preg_match('/^[A-Z]+\z/', $slug)) {
      throw new Exception(
        pht(
          'The name "%s" is not a valid repository short name. Repository '.
          'short names may not contain only uppercase letters.',
          $slug));
    }

    if (preg_match('/^\d+\z/', $slug)) {
      throw new Exception(
        pht(
          'The name "%s" is not a valid repository short name. Repository '.
          'short names may not contain only numbers.',
          $slug));
    }

    if (preg_match('/\\.git/', $slug)) {
      throw new Exception(
        pht(
          'The name "%s" is not a valid repository short name. Repository '.
          'short names must not end in ".git". This suffix will be added '.
          'automatically in appropriate contexts.',
          $slug));
    }
  }

  public static function assertValidCallsign($callsign) {
    if (!strlen($callsign)) {
      throw new Exception(
        pht(
          'A repository callsign must be at least one character long.'));
    }

    if (strlen($callsign) > 32) {
      throw new Exception(
        pht(
          'The callsign "%s" is not a valid repository callsign. Callsigns '.
          'must be no more than 32 bytes long.',
          $callsign));
    }

    if (!preg_match('/^[A-Z]+\z/', $callsign)) {
      throw new Exception(
        pht(
          'The callsign "%s" is not a valid repository callsign. Callsigns '.
          'may only contain UPPERCASE letters.',
          $callsign));
    }
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
    return $this->newRemoteCommandEngine($argv)
      ->newFuture();
  }

  private function newRemoteCommandPassthru(array $argv) {
    return $this->newRemoteCommandEngine($argv)
      ->setPassthru(true)
      ->newFuture();
  }

  private function newRemoteCommandEngine(array $argv) {
    return DiffusionCommandEngine::newCommandEngine($this)
      ->setArgv($argv)
      ->setCredentialPHID($this->getCredentialPHID())
      ->setURI($this->getRemoteURIObject());
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

    $future = DiffusionCommandEngine::newCommandEngine($this)
      ->setArgv($argv)
      ->newFuture();

    if ($this->usesLocalWorkingCopy()) {
      $future->setCWD($this->getLocalPath());
    }

    return $future;
  }

  private function newLocalCommandPassthru(array $argv) {
    $this->assertLocalExists();

    $future = DiffusionCommandEngine::newCommandEngine($this)
      ->setArgv($argv)
      ->setPassthru(true)
      ->newFuture();

    if ($this->usesLocalWorkingCopy()) {
      $future->setCWD($this->getLocalPath());
    }

    return $future;
  }

  public function getURI() {
    $short_name = $this->getRepositorySlug();
    if (strlen($short_name)) {
      return "/source/{$short_name}/";
    }

    $callsign = $this->getCallsign();
    if (strlen($callsign)) {
      return "/diffusion/{$callsign}/";
    }

    $id = $this->getID();
    return "/diffusion/{$id}/";
  }

  public function getPathURI($path) {
    return $this->getURI().ltrim($path, '/');
  }

  public function getCommitURI($identifier) {
    $callsign = $this->getCallsign();
    if (strlen($callsign)) {
      return "/r{$callsign}{$identifier}";
    }

    $id = $this->getID();
    return "/R{$id}:{$identifier}";
  }

  public static function parseRepositoryServicePath($request_path, $vcs) {
    $is_git = ($vcs == PhabricatorRepositoryType::REPOSITORY_TYPE_GIT);

    $patterns = array(
      '(^'.
        '(?P<base>/?(?:diffusion|source)/(?P<identifier>[^/]+))'.
        '(?P<path>.*)'.
      '\z)',
    );

    $identifier = null;
    foreach ($patterns as $pattern) {
      $matches = null;
      if (!preg_match($pattern, $request_path, $matches)) {
        continue;
      }

      $identifier = $matches['identifier'];
      if ($is_git) {
        $identifier = preg_replace('/\\.git\z/', '', $identifier);
      }

      $base = $matches['base'];
      $path = $matches['path'];
      break;
    }

    if ($identifier === null) {
      return null;
    }

    return array(
      'identifier' => $identifier,
      'base' => $base,
      'path' => $path,
    );
  }

  public function getCanonicalPath($request_path) {
    $standard_pattern =
      '(^'.
        '(?P<prefix>/(?:diffusion|source)/)'.
        '(?P<identifier>[^/]+)'.
        '(?P<suffix>(?:/.*)?)'.
      '\z)';

    $matches = null;
    if (preg_match($standard_pattern, $request_path, $matches)) {
      $suffix = $matches['suffix'];
      return $this->getPathURI($suffix);
    }

    $commit_pattern =
      '(^'.
        '(?P<prefix>/)'.
        '(?P<monogram>'.
          '(?:'.
            'r(?P<repositoryCallsign>[A-Z]+)'.
            '|'.
            'R(?P<repositoryID>[1-9]\d*):'.
          ')'.
          '(?P<commit>[a-f0-9]+)'.
        ')'.
      '\z)';

    $matches = null;
    if (preg_match($commit_pattern, $request_path, $matches)) {
      $commit = $matches['commit'];
      return $this->getCommitURI($commit);
    }

    return null;
  }

  public function generateURI(array $params) {
    $req_branch = false;
    $req_commit = false;

    $action = idx($params, 'action');
    switch ($action) {
      case 'history':
      case 'browse':
      case 'change':
      case 'lastmodified':
      case 'tags':
      case 'branches':
      case 'lint':
      case 'pathtree':
      case 'refs':
      case 'compare':
        break;
      case 'branch':
        // NOTE: This does not actually require a branch, and won't have one
        // in Subversion. Possibly this should be more clear.
        break;
      case 'commit':
      case 'rendering-ref':
        $req_commit = true;
        break;
      default:
        throw new Exception(
          pht(
            'Action "%s" is not a valid repository URI action.',
            $action));
    }

    $path     = idx($params, 'path');
    $branch   = idx($params, 'branch');
    $commit   = idx($params, 'commit');
    $line     = idx($params, 'line');

    $head = idx($params, 'head');
    $against = idx($params, 'against');

    if ($req_commit && !strlen($commit)) {
      throw new Exception(
        pht(
          'Diffusion URI action "%s" requires commit!',
          $action));
    }

    if ($req_branch && !strlen($branch)) {
      throw new Exception(
        pht(
          'Diffusion URI action "%s" requires branch!',
          $action));
    }

    if ($action === 'commit') {
      return $this->getCommitURI($commit);
    }

    if (strlen($path)) {
      $path = ltrim($path, '/');
      $path = str_replace(array(';', '$'), array(';;', '$$'), $path);
      $path = phutil_escape_uri($path);
    }

    $raw_branch = $branch;
    if (strlen($branch)) {
      $branch = phutil_escape_uri_path_component($branch);
      $path = "{$branch}/{$path}";
    }

    $raw_commit = $commit;
    if (strlen($commit)) {
      $commit = str_replace('$', '$$', $commit);
      $commit = ';'.phutil_escape_uri($commit);
    }

    if (strlen($line)) {
      $line = '$'.phutil_escape_uri($line);
    }

    $query = array();
    switch ($action) {
      case 'change':
      case 'history':
      case 'browse':
      case 'lastmodified':
      case 'tags':
      case 'branches':
      case 'lint':
      case 'pathtree':
      case 'refs':
        $uri = $this->getPathURI("/{$action}/{$path}{$commit}{$line}");
        break;
      case 'compare':
        $uri = $this->getPathURI("/{$action}/");
        if (strlen($head)) {
          $query['head'] = $head;
        } else if (strlen($raw_commit)) {
          $query['commit'] = $raw_commit;
        } else if (strlen($raw_branch)) {
          $query['head'] = $raw_branch;
        }

        if (strlen($against)) {
          $query['against'] = $against;
        }
        break;
      case 'branch':
        if (strlen($path)) {
          $uri = $this->getPathURI("/repository/{$path}");
        } else {
          $uri = $this->getPathURI('/');
        }
        break;
      case 'external':
        $commit = ltrim($commit, ';');
        $uri = "/diffusion/external/{$commit}/";
        break;
      case 'rendering-ref':
        // This isn't a real URI per se, it's passed as a query parameter to
        // the ajax changeset stuff but then we parse it back out as though
        // it came from a URI.
        $uri = rawurldecode("{$path}{$commit}");
        break;
    }

    if ($action == 'rendering-ref') {
      return $uri;
    }

    $uri = new PhutilURI($uri);

    if (isset($params['lint'])) {
      $params['params'] = idx($params, 'params', array()) + array(
        'lint' => $params['lint'],
      );
    }

    $query = idx($params, 'params', array()) + $query;

    if ($query) {
      $uri->setQueryParams($query);
    }

    return $uri;
  }

  public function updateURIIndex() {
    $indexes = array();

    $uris = $this->getURIs();
    foreach ($uris as $uri) {
      if ($uri->getIsDisabled()) {
        continue;
      }

      $indexes[] = $uri->getNormalizedURI();
    }

    PhabricatorRepositoryURIIndex::updateRepositoryURIs(
      $this->getPHID(),
      $indexes);

    return $this;
  }

  public function isTracked() {
    $status = $this->getDetail('tracking-enabled');
    $map = self::getStatusMap();
    $spec = idx($map, $status);

    if (!$spec) {
      if ($status) {
        $status = self::STATUS_ACTIVE;
      } else {
        $status = self::STATUS_INACTIVE;
      }
      $spec = idx($map, $status);
    }

    return (bool)idx($spec, 'isTracked', false);
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

  public function shouldTrackRef(DiffusionRepositoryRef $ref) {
    // At least for now, don't track the staging area tags.
    if ($ref->isTag()) {
      if (preg_match('(^phabricator/)', $ref->getShortName())) {
        return false;
      }
    }

    if (!$ref->isBranch()) {
      return true;
    }

    return $this->shouldTrackBranch($ref->getShortName());
  }

  public function shouldTrackBranch($branch) {
    return $this->isBranchInFilter($branch, 'branch-filter');
  }

  public function formatCommitName($commit_identifier, $local = false) {
    $vcs = $this->getVersionControlSystem();

    $type_git = PhabricatorRepositoryType::REPOSITORY_TYPE_GIT;
    $type_hg = PhabricatorRepositoryType::REPOSITORY_TYPE_MERCURIAL;

    $is_git = ($vcs == $type_git);
    $is_hg = ($vcs == $type_hg);
    if ($is_git || $is_hg) {
      $name = substr($commit_identifier, 0, 12);
      $need_scope = false;
    } else {
      $name = $commit_identifier;
      $need_scope = true;
    }

    if (!$local) {
      $need_scope = true;
    }

    if ($need_scope) {
      $callsign = $this->getCallsign();
      if ($callsign) {
        $scope = "r{$callsign}";
      } else {
        $id = $this->getID();
        $scope = "R{$id}:";
      }
      $name = $scope.$name;
    }

    return $name;
  }

  public function isImporting() {
    return (bool)$this->getDetail('importing', false);
  }

  public function isNewlyInitialized() {
    return (bool)$this->getDetail('newly-initialized', false);
  }

  public function loadImportProgress() {
    $progress = queryfx_all(
      $this->establishConnection('r'),
      'SELECT importStatus, count(*) N FROM %T WHERE repositoryID = %d
        GROUP BY importStatus',
      id(new PhabricatorRepositoryCommit())->getTableName(),
      $this->getID());

    $done = 0;
    $total = 0;
    foreach ($progress as $row) {
      $total += $row['N'] * 4;
      $status = $row['importStatus'];
      if ($status & PhabricatorRepositoryCommit::IMPORTED_MESSAGE) {
        $done += $row['N'];
      }
      if ($status & PhabricatorRepositoryCommit::IMPORTED_CHANGE) {
        $done += $row['N'];
      }
      if ($status & PhabricatorRepositoryCommit::IMPORTED_OWNERS) {
        $done += $row['N'];
      }
      if ($status & PhabricatorRepositoryCommit::IMPORTED_HERALD) {
        $done += $row['N'];
      }
    }

    if ($total) {
      $ratio = ($done / $total);
    } else {
      $ratio = 0;
    }

    // Cap this at "99.99%", because it's confusing to users when the actual
    // fraction is "99.996%" and it rounds up to "100.00%".
    if ($ratio > 0.9999) {
      $ratio = 0.9999;
    }

    return $ratio;
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

    if ($this->getDetail('herald-disabled')) {
      return false;
    }

    return true;
  }


/* -(  Autoclose  )---------------------------------------------------------- */


  public function shouldAutocloseRef(DiffusionRepositoryRef $ref) {
    if (!$ref->isBranch()) {
      return false;
    }

    return $this->shouldAutocloseBranch($ref->getShortName());
  }

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
    return (string)$this->getCloneURIObject();
  }


  /**
   * Get the protocol for the repository's remote.
   *
   * @return string Protocol, like "ssh" or "git".
   * @task uri
   */
  public function getRemoteProtocol() {
    $uri = $this->getRemoteURIObject();
    return $uri->getProtocol();
  }


  /**
   * Get a parsed object representation of the repository's remote URI..
   *
   * @return wild A @{class@libphutil:PhutilURI}.
   * @task uri
   */
  public function getRemoteURIObject() {
    $raw_uri = $this->getDetail('remote-uri');
    if (!strlen($raw_uri)) {
      return new PhutilURI('');
    }

    if (!strncmp($raw_uri, '/', 1)) {
      return new PhutilURI('file://'.$raw_uri);
    }

    return new PhutilURI($raw_uri);
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

    // TODO: This should be cleaned up to deal with all the new URI handling.
    $another_copy = id(new PhabricatorRepositoryQuery())
      ->setViewer(PhabricatorUser::getOmnipotentUser())
      ->withPHIDs(array($this->getPHID()))
      ->needURIs(true)
      ->executeOne();

    $clone_uris = $another_copy->getCloneURIs();
    if (!$clone_uris) {
      return null;
    }

    return head($clone_uris)->getEffectiveURI();
  }

  private function getRawHTTPCloneURIObject() {
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

      $uris = id(new PhabricatorRepositoryURI())
        ->loadAllWhere('repositoryPHID = %s', $this->getPHID());
      foreach ($uris as $uri) {
        $uri->delete();
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

  public function canServeProtocol($protocol, $write) {
    if (!$this->isTracked()) {
      return false;
    }

    $clone_uris = $this->getCloneURIs();
    foreach ($clone_uris as $uri) {
      if ($uri->getBuiltinProtocol() !== $protocol) {
        continue;
      }

      $io_type = $uri->getEffectiveIoType();
      if ($io_type == PhabricatorRepositoryURI::IO_READWRITE) {
        return true;
      }

      if (!$write) {
        if ($io_type == PhabricatorRepositoryURI::IO_READ) {
          return true;
        }
      }
    }

    return false;
  }

  public function hasLocalWorkingCopy() {
    try {
      self::assertLocalExists();
      return true;
    } catch (Exception $ex) {
      return false;
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

  public function canUseGitLFS() {
    if (!$this->isGit()) {
      return false;
    }

    if (!$this->isHosted()) {
      return false;
    }

    // TODO: Unprototype this feature.
    if (!PhabricatorEnv::getEnvConfig('phabricator.show-prototypes')) {
      return false;
    }

    return true;
  }

  public function getGitLFSURI($path = null) {
    if (!$this->canUseGitLFS()) {
      throw new Exception(
        pht(
          'This repository does not support Git LFS, so Git LFS URIs can '.
          'not be generated for it.'));
    }

    $uri = $this->getRawHTTPCloneURIObject();
    $uri = (string)$uri;
    $uri = $uri.'/'.$path;

    return $uri;
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

    // In Git and Mercurial, ref deletions and rewrites are dangerous.
    // In Subversion, editing revprops is dangerous.

    return true;
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
      // If the existing message has the same code (e.g., we just hit an
      // error and also previously hit an error) we increment the message
      // count. This allows us to determine how many times in a row we've
      // run into an error.

      // NOTE: The assignments in "ON DUPLICATE KEY UPDATE" are evaluated
      // in order, so the "messageCount" assignment must occur before the
      // "statusCode" assignment. See T11705.

      queryfx(
        $conn_w,
        'INSERT INTO %T
          (repositoryID, statusType, statusCode, parameters, epoch,
            messageCount)
          VALUES (%d, %s, %s, %s, %d, %d)
          ON DUPLICATE KEY UPDATE
            messageCount =
              IF(
                statusCode = VALUES(statusCode),
                messageCount + VALUES(messageCount),
                VALUES(messageCount)),
            statusCode = VALUES(statusCode),
            parameters = VALUES(parameters),
            epoch = VALUES(epoch)',
        $table_name,
        $this->getID(),
        $status_type,
        $status_code,
        json_encode($parameters),
        time(),
        1);
    }

    return $this;
  }

  public static function assertValidRemoteURI($uri) {
    if (trim($uri) != $uri) {
      throw new Exception(
        pht('The remote URI has leading or trailing whitespace.'));
    }

    $uri_object = new PhutilURI($uri);
    $protocol = $uri_object->getProtocol();

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
            'The URI protocol is unrecognized. It should begin with '.
            '"%s", "%s", "%s", "%s", "%s", "%s", or be in the form "%s".',
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
    // First, check if we've hit errors recently. If we have, wait one period
    // for each consecutive error. Normally, this corresponds to a backoff of
    // 15s, 30s, 45s, etc.

    $message_table = new PhabricatorRepositoryStatusMessage();
    $conn = $message_table->establishConnection('r');
    $error_count = queryfx_one(
      $conn,
      'SELECT MAX(messageCount) error_count FROM %T
        WHERE repositoryID = %d
        AND statusType IN (%Ls)
        AND statusCode IN (%Ls)',
      $message_table->getTableName(),
      $this->getID(),
      array(
        PhabricatorRepositoryStatusMessage::TYPE_INIT,
        PhabricatorRepositoryStatusMessage::TYPE_FETCH,
      ),
      array(
        PhabricatorRepositoryStatusMessage::CODE_ERROR,
      ));

    $error_count = (int)$error_count['error_count'];
    if ($error_count > 0) {
      return (int)($minimum * $error_count);
    }

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
    } else {
      // If the repository has no commits, treat the creation date as
      // though it were the date of the last commit. This makes empty
      // repositories update quickly at first but slow down over time
      // if they don't see any activity.
      $time_since_commit = ($window_start - $this->getDateCreated());
    }

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

    return (int)$smart_wait;
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

    $cache_key = $this->getAlmanacServiceCacheKey();
    if (!$cache_key) {
      return null;
    }

    $cache = PhabricatorCaches::getMutableStructureCache();
    $uris = $cache->getKey($cache_key, false);

    // If we haven't built the cache yet, build it now.
    if ($uris === false) {
      $uris = $this->buildAlmanacServiceURIs();
      $cache->setKey($cache_key, $uris);
    }

    if ($uris === null) {
      return null;
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

    $results = array();
    foreach ($uris as $uri) {
      // If we're never proxying this and it's locally satisfiable, return
      // `null` to tell the caller to handle it locally. If we're allowed to
      // proxy, we skip this check and may proxy the request to ourselves.
      // (That proxied request will end up here with proxying forbidden,
      // return `null`, and then the request will actually run.)

      if ($local_device && $never_proxy) {
        if ($uri['device'] == $local_device) {
          return null;
        }
      }

      if (isset($protocol_map[$uri['protocol']])) {
        $results[] = new PhutilURI($uri['uri']);
      }
    }

    if (!$results) {
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

    if (count($results) > 1) {
      if (!$this->supportsSynchronization()) {
        throw new Exception(
          pht(
            'Repository "%s" is bound to multiple active repository hosts, '.
            'but this repository does not support cluster synchronization. '.
            'Declusterize this repository or move it to a service with only '.
            'one host.',
            $this->getDisplayName()));
      }
    }

    shuffle($results);
    return head($results);
  }

  public function supportsSynchronization() {
    // TODO: For now, this is only supported for Git.
    if (!$this->isGit()) {
      return false;
    }

    return true;
  }

  public function getAlmanacServiceCacheKey() {
    $service_phid = $this->getAlmanacServicePHID();
    if (!$service_phid) {
      return null;
    }

    $repository_phid = $this->getPHID();
    return "diffusion.repository({$repository_phid}).service({$service_phid})";
  }

  private function buildAlmanacServiceURIs() {
    $service = $this->loadAlmanacService();
    if (!$service) {
      return null;
    }

    $bindings = $service->getActiveBindings();
    if (!$bindings) {
      throw new Exception(
        pht(
          'The Almanac service for this repository is not bound to any '.
          'interfaces.'));
    }

    $uris = array();
    foreach ($bindings as $binding) {
      $iface = $binding->getInterface();

      $uri = $this->getClusterRepositoryURIFromBinding($binding);
      $protocol = $uri->getProtocol();
      $device_name = $iface->getDevice()->getName();

      $uris[] = array(
        'protocol' => $protocol,
        'uri' => (string)$uri,
        'device' => $device_name,
      );
    }

    return $uris;
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

  public function getPassthroughEnvironmentalVariables() {
    $env = $_ENV;

    if ($this->isGit()) {
      // $_ENV does not populate in CLI contexts if "E" is missing from
      // "variables_order" in PHP config. Currently, we do not require this
      // to be configured. Since it may not be, explictitly bring expected Git
      // environmental variables into scope. This list is not exhaustive, but
      // only lists variables with a known impact on commit hook behavior.

      // This can be removed if we later require "E" in "variables_order".

      $git_env = array(
        'GIT_OBJECT_DIRECTORY',
        'GIT_ALTERNATE_OBJECT_DIRECTORIES',
        'GIT_QUARANTINE_PATH',
      );
      foreach ($git_env as $key) {
        $value = getenv($key);
        if (strlen($value)) {
          $env[$key] = $value;
        }
      }

      $key = 'GIT_PUSH_OPTION_COUNT';
      $git_count = getenv($key);
      if (strlen($git_count)) {
        $git_count = (int)$git_count;
        $env[$key] = $git_count;
        for ($ii = 0; $ii < $git_count; $ii++) {
          $key = 'GIT_PUSH_OPTION_'.$ii;
          $env[$key] = getenv($key);
        }
      }
    }

    $result = array();
    foreach ($env as $key => $value) {
      // In Git, pass anything matching "GIT_*" though. Some of these variables
      // need to be preserved to allow `git` operations to work properly when
      // running from commit hooks.
      if ($this->isGit()) {
        if (preg_match('/^GIT_/', $key)) {
          $result[$key] = $value;
        }
      }
    }

    return $result;
  }

  public function supportsBranchComparison() {
    return $this->isGit();
  }

/* -(  Repository URIs  )---------------------------------------------------- */


  public function attachURIs(array $uris) {
    $custom_map = array();
    foreach ($uris as $key => $uri) {
      $builtin_key = $uri->getRepositoryURIBuiltinKey();
      if ($builtin_key !== null) {
        $custom_map[$builtin_key] = $key;
      }
    }

    $builtin_uris = $this->newBuiltinURIs();
    $seen_builtins = array();
    foreach ($builtin_uris as $builtin_uri) {
      $builtin_key = $builtin_uri->getRepositoryURIBuiltinKey();
      $seen_builtins[$builtin_key] = true;

      // If this builtin URI is disabled, don't attach it and remove the
      // persisted version if it exists.
      if ($builtin_uri->getIsDisabled()) {
        if (isset($custom_map[$builtin_key])) {
          unset($uris[$custom_map[$builtin_key]]);
        }
        continue;
      }

      // If the URI exists, make sure it's marked as not being disabled.
      if (isset($custom_map[$builtin_key])) {
        $uris[$custom_map[$builtin_key]]->setIsDisabled(false);
      }
    }

    // Remove any builtins which no longer exist.
    foreach ($custom_map as $builtin_key => $key) {
      if (empty($seen_builtins[$builtin_key])) {
        unset($uris[$key]);
      }
    }

    $this->uris = $uris;

    return $this;
  }

  public function getURIs() {
    return $this->assertAttached($this->uris);
  }

  public function getCloneURIs() {
    $uris = $this->getURIs();

    $clone = array();
    foreach ($uris as $uri) {
      if (!$uri->isBuiltin()) {
        continue;
      }

      if ($uri->getIsDisabled()) {
        continue;
      }

      $io_type = $uri->getEffectiveIoType();
      $is_clone =
        ($io_type == PhabricatorRepositoryURI::IO_READ) ||
        ($io_type == PhabricatorRepositoryURI::IO_READWRITE);

      if (!$is_clone) {
        continue;
      }

      $clone[] = $uri;
    }

    $clone = msort($clone, 'getURIScore');
    $clone = array_reverse($clone);

    return $clone;
  }


  public function newBuiltinURIs() {
    $has_callsign = ($this->getCallsign() !== null);
    $has_shortname = ($this->getRepositorySlug() !== null);

    $identifier_map = array(
      PhabricatorRepositoryURI::BUILTIN_IDENTIFIER_CALLSIGN => $has_callsign,
      PhabricatorRepositoryURI::BUILTIN_IDENTIFIER_SHORTNAME => $has_shortname,
      PhabricatorRepositoryURI::BUILTIN_IDENTIFIER_ID => true,
    );

    // If the view policy of the repository is public, support anonymous HTTP
    // even if authenticated HTTP is not supported.
    if ($this->getViewPolicy() === PhabricatorPolicies::POLICY_PUBLIC) {
      $allow_http = true;
    } else {
      $allow_http = PhabricatorEnv::getEnvConfig('diffusion.allow-http-auth');
    }

    $base_uri = PhabricatorEnv::getURI('/');
    $base_uri = new PhutilURI($base_uri);
    $has_https = ($base_uri->getProtocol() == 'https');
    $has_https = ($has_https && $allow_http);

    $has_http = !PhabricatorEnv::getEnvConfig('security.require-https');
    $has_http = ($has_http && $allow_http);

    // HTTP is not supported for Subversion.
    if ($this->isSVN()) {
      $has_http = false;
      $has_https = false;
    }

    $has_ssh = (bool)strlen(PhabricatorEnv::getEnvConfig('phd.user'));

    $protocol_map = array(
      PhabricatorRepositoryURI::BUILTIN_PROTOCOL_SSH => $has_ssh,
      PhabricatorRepositoryURI::BUILTIN_PROTOCOL_HTTPS => $has_https,
      PhabricatorRepositoryURI::BUILTIN_PROTOCOL_HTTP => $has_http,
    );

    $uris = array();
    foreach ($protocol_map as $protocol => $proto_supported) {
      foreach ($identifier_map as $identifier => $id_supported) {
        // This is just a dummy value because it can't be empty; we'll force
        // it to a proper value when using it in the UI.
        $builtin_uri = "{$protocol}://{$identifier}";
        $uris[] = PhabricatorRepositoryURI::initializeNewURI()
          ->setRepositoryPHID($this->getPHID())
          ->attachRepository($this)
          ->setBuiltinProtocol($protocol)
          ->setBuiltinIdentifier($identifier)
          ->setURI($builtin_uri)
          ->setIsDisabled((int)(!$proto_supported || !$id_supported));
      }
    }

    return $uris;
  }


  public function getClusterRepositoryURIFromBinding(
    AlmanacBinding $binding) {
    $protocol = $binding->getAlmanacPropertyValue('protocol');
    if ($protocol === null) {
      $protocol = 'https';
    }

    $iface = $binding->getInterface();
    $address = $iface->renderDisplayAddress();

    $path = $this->getURI();

    return id(new PhutilURI("{$protocol}://{$address}"))
      ->setPath($path);
  }

  public function loadAlmanacService() {
    $service_phid = $this->getAlmanacServicePHID();
    if (!$service_phid) {
      // No service, so this is a local repository.
      return null;
    }

    $service = id(new AlmanacServiceQuery())
      ->setViewer(PhabricatorUser::getOmnipotentUser())
      ->withPHIDs(array($service_phid))
      ->needBindings(true)
      ->needProperties(true)
      ->executeOne();
    if (!$service) {
      throw new Exception(
        pht(
          'The Almanac service for this repository is invalid or could not '.
          'be loaded.'));
    }

    $service_type = $service->getServiceImplementation();
    if (!($service_type instanceof AlmanacClusterRepositoryServiceType)) {
      throw new Exception(
        pht(
          'The Almanac service for this repository does not have the correct '.
          'service type.'));
    }

    return $service;
  }

  public function markImporting() {
    $this->openTransaction();
      $this->beginReadLocking();
        $repository = $this->reload();
        $repository->setDetail('importing', true);
        $repository->save();
      $this->endReadLocking();
    $this->saveTransaction();

    return $repository;
  }


/* -(  Symbols  )-------------------------------------------------------------*/

  public function getSymbolSources() {
    return $this->getDetail('symbol-sources', array());
  }

  public function getSymbolLanguages() {
    return $this->getDetail('symbol-languages', array());
  }


/* -(  Staging  )------------------------------------------------------------ */


  public function supportsStaging() {
    return $this->isGit();
  }


  public function getStagingURI() {
    if (!$this->supportsStaging()) {
      return null;
    }
    return $this->getDetail('staging-uri', null);
  }


/* -(  Automation  )--------------------------------------------------------- */


  public function supportsAutomation() {
    return $this->isGit();
  }

  public function canPerformAutomation() {
    if (!$this->supportsAutomation()) {
      return false;
    }

    if (!$this->getAutomationBlueprintPHIDs()) {
      return false;
    }

    return true;
  }

  public function getAutomationBlueprintPHIDs() {
    if (!$this->supportsAutomation()) {
      return array();
    }
    return $this->getDetail('automation.blueprintPHIDs', array());
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

    $phid = $this->getPHID();

    $this->openTransaction();

      $this->delete();

      PhabricatorRepositoryURIIndex::updateRepositoryURIs($phid, array());

      $books = id(new DivinerBookQuery())
        ->setViewer($engine->getViewer())
        ->withRepositoryPHIDs(array($phid))
        ->execute();
      foreach ($books as $book) {
        $engine->destroyObject($book);
      }

      $atoms = id(new DivinerAtomQuery())
        ->setViewer($engine->getViewer())
        ->withRepositoryPHIDs(array($phid))
        ->execute();
      foreach ($atoms as $atom) {
        $engine->destroyObject($atom);
      }

      $lfs_refs = id(new PhabricatorRepositoryGitLFSRefQuery())
        ->setViewer($engine->getViewer())
        ->withRepositoryPHIDs(array($phid))
        ->execute();
      foreach ($lfs_refs as $ref) {
        $engine->destroyObject($ref);
      }

    $this->saveTransaction();
  }


/* -(  PhabricatorSpacesInterface  )----------------------------------------- */


  public function getSpacePHID() {
    return $this->spacePHID;
  }

/* -(  PhabricatorConduitResultInterface  )---------------------------------- */


  public function getFieldSpecificationsForConduit() {
    return array(
      id(new PhabricatorConduitSearchFieldSpecification())
        ->setKey('name')
        ->setType('string')
        ->setDescription(pht('The repository name.')),
      id(new PhabricatorConduitSearchFieldSpecification())
        ->setKey('vcs')
        ->setType('string')
        ->setDescription(
          pht('The VCS this repository uses ("git", "hg" or "svn").')),
      id(new PhabricatorConduitSearchFieldSpecification())
        ->setKey('callsign')
        ->setType('string')
        ->setDescription(pht('The repository callsign, if it has one.')),
      id(new PhabricatorConduitSearchFieldSpecification())
        ->setKey('shortName')
        ->setType('string')
        ->setDescription(pht('Unique short name, if the repository has one.')),
      id(new PhabricatorConduitSearchFieldSpecification())
        ->setKey('status')
        ->setType('string')
        ->setDescription(pht('Active or inactive status.')),
      id(new PhabricatorConduitSearchFieldSpecification())
        ->setKey('isImporting')
        ->setType('bool')
        ->setDescription(
          pht(
            'True if the repository is importing initial commits.')),
    );
  }

  public function getFieldValuesForConduit() {
    return array(
      'name' => $this->getName(),
      'vcs' => $this->getVersionControlSystem(),
      'callsign' => $this->getCallsign(),
      'shortName' => $this->getRepositorySlug(),
      'status' => $this->getStatus(),
      'isImporting' => (bool)$this->isImporting(),
    );
  }

  public function getConduitSearchAttachments() {
    return array(
      id(new DiffusionRepositoryURIsSearchEngineAttachment())
        ->setAttachmentKey('uris'),
    );
  }

/* -(  PhabricatorFulltextInterface  )--------------------------------------- */


  public function newFulltextEngine() {
    return new PhabricatorRepositoryFulltextEngine();
  }

}
