<?php

/**
 * Contains logic to parse Diffusion requests, which have a complicated URI
 * structure.
 *
 * @task new Creating Requests
 * @task uri Managing Diffusion URIs
 */
abstract class DiffusionRequest extends Phobject {

  protected $path;
  protected $line;
  protected $branch;
  protected $lint;

  protected $symbolicCommit;
  protected $symbolicType;
  protected $stableCommit;

  protected $repository;
  protected $repositoryCommit;
  protected $repositoryCommitData;

  private $isClusterRequest = false;
  private $initFromConduit = true;
  private $user;
  private $branchObject = false;
  private $refAlternatives;

  abstract public function supportsBranches();
  abstract protected function isStableCommit($symbol);

  protected function didInitialize() {
    return null;
  }


/* -(  Creating Requests  )-------------------------------------------------- */


  /**
   * Create a new synthetic request from a parameter dictionary. If you need
   * a @{class:DiffusionRequest} object in order to issue a DiffusionQuery, you
   * can use this method to build one.
   *
   * Parameters are:
   *
   *   - `repository` Repository object or identifier.
   *   - `user` Viewing user. Required if `repository` is an identifier.
   *   - `branch` Optional, branch name.
   *   - `path` Optional, file path.
   *   - `commit` Optional, commit identifier.
   *   - `line` Optional, line range.
   *
   * @param   map                 See documentation.
   * @return  DiffusionRequest    New request object.
   * @task new
   */
  final public static function newFromDictionary(array $data) {
    $repository_key = 'repository';
    $identifier_key = 'callsign';
    $viewer_key = 'user';

    $repository = idx($data, $repository_key);
    $identifier = idx($data, $identifier_key);

    $have_repository = ($repository !== null);
    $have_identifier = ($identifier !== null);

    if ($have_repository && $have_identifier) {
      throw new Exception(
        pht(
          'Specify "%s" or "%s", but not both.',
          $repository_key,
          $identifier_key));
    }

    if (!$have_repository && !$have_identifier) {
      throw new Exception(
        pht(
          'One of "%s" and "%s" is required.',
          $repository_key,
          $identifier_key));
    }

    if ($have_repository) {
      if (!($repository instanceof PhabricatorRepository)) {
        if (empty($data[$viewer_key])) {
          throw new Exception(
            pht(
              'Parameter "%s" is required if "%s" is provided.',
              $viewer_key,
              $identifier_key));
        }

        $identifier = $repository;
        $repository = null;
      }
    }

    if ($identifier !== null) {
      $object = self::newFromIdentifier(
        $identifier,
        $data[$viewer_key],
        idx($data, 'edit'));
    } else {
      $object = self::newFromRepository($repository);
    }

    if (!$object) {
      return null;
    }

    $object->initializeFromDictionary($data);

    return $object;
  }

  /**
   * Internal.
   *
   * @task new
   */
  final private function __construct() {
    // <private>
  }


  /**
   * Internal. Use @{method:newFromDictionary}, not this method.
   *
   * @param   string              Repository identifier.
   * @param   PhabricatorUser     Viewing user.
   * @return  DiffusionRequest    New request object.
   * @task new
   */
  final private static function newFromIdentifier(
    $identifier,
    PhabricatorUser $viewer,
    $need_edit = false) {

    $query = id(new PhabricatorRepositoryQuery())
      ->setViewer($viewer)
      ->withIdentifiers(array($identifier));

    if ($need_edit) {
      $query->requireCapabilities(
        array(
          PhabricatorPolicyCapability::CAN_VIEW,
          PhabricatorPolicyCapability::CAN_EDIT,
        ));
    }

    $repository = $query->executeOne();

    if (!$repository) {
      return null;
    }

    return self::newFromRepository($repository);
  }


  /**
   * Internal. Use @{method:newFromDictionary}, not this method.
   *
   * @param   PhabricatorRepository   Repository object.
   * @return  DiffusionRequest        New request object.
   * @task new
   */
  final private static function newFromRepository(
    PhabricatorRepository $repository) {

    $map = array(
      PhabricatorRepositoryType::REPOSITORY_TYPE_GIT => 'DiffusionGitRequest',
      PhabricatorRepositoryType::REPOSITORY_TYPE_SVN => 'DiffusionSvnRequest',
      PhabricatorRepositoryType::REPOSITORY_TYPE_MERCURIAL =>
        'DiffusionMercurialRequest',
    );

    $class = idx($map, $repository->getVersionControlSystem());

    if (!$class) {
      throw new Exception(pht('Unknown version control system!'));
    }

    $object = new $class();

    $object->repository = $repository;

    return $object;
  }


  /**
   * Internal. Use @{method:newFromDictionary}, not this method.
   *
   * @param map Map of parsed data.
   * @return void
   * @task new
   */
  final private function initializeFromDictionary(array $data) {
    $blob = idx($data, 'blob');
    if (strlen($blob)) {
      $blob = self::parseRequestBlob($blob, $this->supportsBranches());
      $data = $blob + $data;
    }

    $this->path = idx($data, 'path');
    $this->line = idx($data, 'line');
    $this->initFromConduit = idx($data, 'initFromConduit', true);
    $this->lint = idx($data, 'lint');

    $this->symbolicCommit = idx($data, 'commit');
    if ($this->supportsBranches()) {
      $this->branch = idx($data, 'branch');
    }

    if (!$this->getUser()) {
      $user = idx($data, 'user');
      if (!$user) {
        throw new Exception(
          pht(
            'You must provide a %s in the dictionary!',
            'PhabricatorUser'));
      }
      $this->setUser($user);
    }

    $this->didInitialize();
  }

  final public function setUser(PhabricatorUser $user) {
    $this->user = $user;
    return $this;
  }
  final public function getUser() {
    return $this->user;
  }

  public function getRepository() {
    return $this->repository;
  }

  public function setPath($path) {
    $this->path = $path;
    return $this;
  }

  public function getPath() {
    return $this->path;
  }

  public function getLine() {
    return $this->line;
  }

  public function getCommit() {

    // TODO: Probably remove all of this.

    if ($this->getSymbolicCommit() !== null) {
      return $this->getSymbolicCommit();
    }

    return $this->getStableCommit();
  }

  /**
   * Get the symbolic commit associated with this request.
   *
   * A symbolic commit may be a commit hash, an abbreviated commit hash, a
   * branch name, a tag name, or an expression like "HEAD^^^". The symbolic
   * commit may also be absent.
   *
   * This method always returns the symbol present in the original request,
   * in unmodified form.
   *
   * See also @{method:getStableCommit}.
   *
   * @return string|null  Symbolic commit, if one was present in the request.
   */
  public function getSymbolicCommit() {
    return $this->symbolicCommit;
  }


  /**
   * Modify the request to move the symbolic commit elsewhere.
   *
   * @param string New symbolic commit.
   * @return this
   */
  public function updateSymbolicCommit($symbol) {
    $this->symbolicCommit = $symbol;
    $this->symbolicType = null;
    $this->stableCommit = null;
    return $this;
  }


  /**
   * Get the ref type (`commit` or `tag`) of the location associated with this
   * request.
   *
   * If a symbolic commit is present in the request, this method identifies
   * the type of the symbol. Otherwise, it identifies the type of symbol of
   * the location the request is implicitly associated with. This will probably
   * always be `commit`.
   *
   * @return string   Symbolic commit type (`commit` or `tag`).
   */
  public function getSymbolicType() {
    if ($this->symbolicType === null) {
      // As a side effect, this resolves the symbolic type.
      $this->getStableCommit();
    }
    return $this->symbolicType;
  }


  /**
   * Retrieve the stable, permanent commit name identifying the repository
   * location associated with this request.
   *
   * This returns a non-symbolic identifier for the current commit: in Git and
   * Mercurial, a 40-character SHA1; in SVN, a revision number.
   *
   * See also @{method:getSymbolicCommit}.
   *
   * @return string Stable commit name, like a git hash or SVN revision. Not
   *                a symbolic commit reference.
   */
  public function getStableCommit() {
    if (!$this->stableCommit) {
      if ($this->isStableCommit($this->symbolicCommit)) {
        $this->stableCommit = $this->symbolicCommit;
        $this->symbolicType = 'commit';
      } else {
        $this->queryStableCommit();
      }
    }
    return $this->stableCommit;
  }


  public function getBranch() {
    return $this->branch;
  }

  public function getLint() {
    return $this->lint;
  }

  protected function getArcanistBranch() {
    return $this->getBranch();
  }

  public function loadBranch() {
    // TODO: Get rid of this and do real Queries on real objects.

    if ($this->branchObject === false) {
      $this->branchObject = PhabricatorRepositoryBranch::loadBranch(
        $this->getRepository()->getID(),
        $this->getArcanistBranch());
    }

    return $this->branchObject;
  }

  public function loadCoverage() {
    // TODO: This should also die.
    $branch = $this->loadBranch();
    if (!$branch) {
      return;
    }

    $path = $this->getPath();
    $path_map = id(new DiffusionPathIDQuery(array($path)))->loadPathIDs();

    $coverage_row = queryfx_one(
      id(new PhabricatorRepository())->establishConnection('r'),
      'SELECT * FROM %T WHERE branchID = %d AND pathID = %d
        ORDER BY commitID DESC LIMIT 1',
      'repository_coverage',
      $branch->getID(),
      $path_map[$path]);

    if (!$coverage_row) {
      return null;
    }

    return idx($coverage_row, 'coverage');
  }


  public function loadCommit() {
    if (empty($this->repositoryCommit)) {
      $repository = $this->getRepository();

      $commit = id(new DiffusionCommitQuery())
        ->setViewer($this->getUser())
        ->withRepository($repository)
        ->withIdentifiers(array($this->getStableCommit()))
        ->executeOne();
      if ($commit) {
        $commit->attachRepository($repository);
      }
      $this->repositoryCommit = $commit;
    }
    return $this->repositoryCommit;
  }

  public function loadCommitData() {
    if (empty($this->repositoryCommitData)) {
      $commit = $this->loadCommit();
      $data = id(new PhabricatorRepositoryCommitData())->loadOneWhere(
        'commitID = %d',
        $commit->getID());
      if (!$data) {
        $data = new PhabricatorRepositoryCommitData();
        $data->setCommitMessage(
          pht('(This commit has not been fully parsed yet.)'));
      }
      $this->repositoryCommitData = $data;
    }
    return $this->repositoryCommitData;
  }

/* -(  Managing Diffusion URIs  )-------------------------------------------- */


  public function generateURI(array $params) {
    if (empty($params['stable'])) {
      $default_commit = $this->getSymbolicCommit();
    } else {
      $default_commit = $this->getStableCommit();
    }

    $defaults = array(
      'path'      => $this->getPath(),
      'branch'    => $this->getBranch(),
      'commit'    => $default_commit,
      'lint'      => idx($params, 'lint', $this->getLint()),
    );

    foreach ($defaults as $key => $val) {
      if (!isset($params[$key])) { // Overwrite NULL.
        $params[$key] = $val;
      }
    }

    return $this->getRepository()->generateURI($params);
  }

  /**
   * Internal. Public only for unit tests.
   *
   * Parse the request URI into components.
   *
   * @param   string  URI blob.
   * @param   bool    True if this VCS supports branches.
   * @return  map     Parsed URI.
   *
   * @task uri
   */
  public static function parseRequestBlob($blob, $supports_branches) {
    $result = array(
      'branch'  => null,
      'path'    => null,
      'commit'  => null,
      'line'    => null,
    );

    $matches = null;

    if ($supports_branches) {
      // Consume the front part of the URI, up to the first "/". This is the
      // path-component encoded branch name.
      if (preg_match('@^([^/]+)/@', $blob, $matches)) {
        $result['branch'] = phutil_unescape_uri_path_component($matches[1]);
        $blob = substr($blob, strlen($matches[1]) + 1);
      }
    }

    // Consume the back part of the URI, up to the first "$". Use a negative
    // lookbehind to prevent matching '$$'. We double the '$' symbol when
    // encoding so that files with names like "money/$100" will survive.
    $pattern = '@(?:(?:^|[^$])(?:[$][$])*)[$]([\d-,]+)$@';
    if (preg_match($pattern, $blob, $matches)) {
      $result['line'] = $matches[1];
      $blob = substr($blob, 0, -(strlen($matches[1]) + 1));
    }

    // We've consumed the line number if it exists, so unescape "$" in the
    // rest of the string.
    $blob = str_replace('$$', '$', $blob);

    // Consume the commit name, stopping on ';;'. We allow any character to
    // appear in commits names, as they can sometimes be symbolic names (like
    // tag names or refs).
    if (preg_match('@(?:(?:^|[^;])(?:;;)*);([^;].*)$@', $blob, $matches)) {
      $result['commit'] = $matches[1];
      $blob = substr($blob, 0, -(strlen($matches[1]) + 1));
    }

    // We've consumed the commit if it exists, so unescape ";" in the rest
    // of the string.
    $blob = str_replace(';;', ';', $blob);

    if (strlen($blob)) {
      $result['path'] = $blob;
    }

    $parts = explode('/', $result['path']);
    foreach ($parts as $part) {
      // Prevent any hyjinx since we're ultimately shipping this to the
      // filesystem under a lot of workflows.
      if ($part == '..') {
        throw new Exception(pht('Invalid path URI.'));
      }
    }

    return $result;
  }

  /**
   * Check that the working copy of the repository is present and readable.
   *
   * @param   string  Path to the working copy.
   */
  protected function validateWorkingCopy($path) {
    if (!is_readable(dirname($path))) {
      $this->raisePermissionException();
    }

    if (!Filesystem::pathExists($path)) {
      $this->raiseCloneException();
    }
  }

  protected function raisePermissionException() {
    $host = php_uname('n');
    throw new DiffusionSetupException(
      pht(
        'The clone of this repository ("%s") on the local machine ("%s") '.
        'could not be read. Ensure that the repository is in a '.
        'location where the web server has read permissions.',
        $this->getRepository()->getDisplayName(),
        $host));
  }

  protected function raiseCloneException() {
    $host = php_uname('n');
    throw new DiffusionSetupException(
      pht(
        'The working copy for this repository ("%s") has not been cloned yet '.
        'on this machine ("%s"). Make sure you havestarted the Phabricator '.
        'daemons. If this problem persists for longer than a clone should '.
        'take, check the daemon logs (in the Daemon Console) to see if there '.
        'were errors cloning the repository. Consult the "Diffusion User '.
        'Guide" in the documentation for help setting up repositories.',
        $this->getRepository()->getDisplayName(),
        $host));
  }

  private function queryStableCommit() {
    $types = array();
    if ($this->symbolicCommit) {
      $ref = $this->symbolicCommit;
    } else {
      if ($this->supportsBranches()) {
        $ref = $this->getBranch();
        $types = array(
          PhabricatorRepositoryRefCursor::TYPE_BRANCH,
        );
      } else {
        $ref = 'HEAD';
      }
    }

    $results = $this->resolveRefs(array($ref), $types);

    $matches = idx($results, $ref, array());
    if (!$matches) {
      $message = pht(
        'Ref "%s" does not exist in this repository.',
        $ref);
      throw id(new DiffusionRefNotFoundException($message))
        ->setRef($ref);
    }

    if (count($matches) > 1) {
      $match = $this->chooseBestRefMatch($ref, $matches);
    } else {
      $match = head($matches);
    }

    $this->stableCommit = $match['identifier'];
    $this->symbolicType = $match['type'];
  }

  public function getRefAlternatives() {
    // Make sure we've resolved the reference into a stable commit first.
    try {
      $this->getStableCommit();
    } catch (DiffusionRefNotFoundException $ex) {
      // If we have a bad reference, just return the empty set of
      // alternatives.
    }
    return $this->refAlternatives;
  }

  private function chooseBestRefMatch($ref, array $results) {
    // First, filter out less-desirable matches.
    $candidates = array();
    foreach ($results as $result) {
      // Exclude closed heads.
      if ($result['type'] == 'branch') {
        if (idx($result, 'closed')) {
          continue;
        }
      }

      $candidates[] = $result;
    }

    // If we filtered everything, undo the filtering.
    if (!$candidates) {
      $candidates = $results;
    }

    // TODO: Do a better job of selecting the best match?
    $match = head($candidates);

    // After choosing the best alternative, save all the alternatives so the
    // UI can show them to the user.
    if (count($candidates) > 1) {
      $this->refAlternatives = $candidates;
    }

    return $match;
  }

  private function resolveRefs(array $refs, array $types) {
    // First, try to resolve refs from fast cache sources.
    $cached_query = id(new DiffusionCachedResolveRefsQuery())
      ->setRepository($this->getRepository())
      ->withRefs($refs);

    if ($types) {
      $cached_query->withTypes($types);
    }

    $cached_results = $cached_query->execute();

    // Throw away all the refs we resolved. Hopefully, we'll throw away
    // everything here.
    foreach ($refs as $key => $ref) {
      if (isset($cached_results[$ref])) {
        unset($refs[$key]);
      }
    }

    // If we couldn't pull everything out of the cache, execute the underlying
    // VCS operation.
    if ($refs) {
      $vcs_results = DiffusionQuery::callConduitWithDiffusionRequest(
        $this->getUser(),
        $this,
        'diffusion.resolverefs',
        array(
          'types' => $types,
          'refs' => $refs,
        ));
    } else {
      $vcs_results = array();
    }

    return $vcs_results + $cached_results;
  }

  public function setIsClusterRequest($is_cluster_request) {
    $this->isClusterRequest = $is_cluster_request;
    return $this;
  }

  public function getIsClusterRequest() {
    return $this->isClusterRequest;
  }

}
