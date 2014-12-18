<?php

abstract class DiffusionQuery extends PhabricatorQuery {

  private $request;

  final protected function __construct() {
    // <protected>
  }

  protected static function newQueryObject(
    $base_class,
    DiffusionRequest $request) {

    $repository = $request->getRepository();

    $obj = self::initQueryObject($base_class, $repository);
    $obj->request = $request;

    return $obj;
  }

  final protected static function initQueryObject(
    $base_class,
    PhabricatorRepository $repository) {

    $map = array(
      PhabricatorRepositoryType::REPOSITORY_TYPE_GIT        => 'Git',
      PhabricatorRepositoryType::REPOSITORY_TYPE_MERCURIAL  => 'Mercurial',
      PhabricatorRepositoryType::REPOSITORY_TYPE_SVN        => 'Svn',
    );

    $name = idx($map, $repository->getVersionControlSystem());
    if (!$name) {
      throw new Exception('Unsupported VCS!');
    }

    $class = str_replace('Diffusion', 'Diffusion'.$name, $base_class);
    $obj = new $class();
    return $obj;
  }

  final protected function getRequest() {
    return $this->request;
  }

  final public static function callConduitWithDiffusionRequest(
    PhabricatorUser $user,
    DiffusionRequest $drequest,
    $method,
    array $params = array()) {

    $repository = $drequest->getRepository();

    $core_params = array(
      'callsign' => $repository->getCallsign(),
    );

    if ($drequest->getBranch() !== null) {
      $core_params['branch'] = $drequest->getBranch();
    }

    $params = $params + $core_params;

    $service_phid = $repository->getAlmanacServicePHID();
    if ($service_phid === null) {
      return id(new ConduitCall($method, $params))
        ->setUser($user)
        ->execute();
    }

    $service = id(new AlmanacServiceQuery())
      ->setViewer(PhabricatorUser::getOmnipotentUser())
      ->withPHIDs(array($service_phid))
      ->needBindings(true)
      ->executeOne();
    if (!$service) {
      throw new Exception(
        pht(
          'The Alamnac service for this repository is invalid or could not '.
          'be loaded.'));
    }

    $service_type = $service->getServiceType();
    if (!($service_type instanceof AlmanacClusterRepositoryServiceType)) {
      throw new Exception(
        pht(
          'The Alamnac service for this repository does not have the correct '.
          'service type.'));
    }

    $bindings = $service->getBindings();
    if (!$bindings) {
      throw new Exception(
        pht(
          'The Alamanc service for this repository is not bound to any '.
          'interfaces.'));
    }

    $uris = array();
    foreach ($bindings as $binding) {
      $iface = $binding->getInterface();

      $protocol = $binding->getAlmanacPropertyValue('protocol');
      if ($protocol === 'http') {
        $uris[] = 'http://'.$iface->renderDisplayAddress().'/';
      } else if ($protocol === 'https' || $protocol === null) {
        $uris[] = 'https://'.$iface->renderDisplayAddress().'/';
      } else {
        throw new Exception(
          pht(
            'The Almanac service for this repository has a binding to an '.
            'invalid interface with an unknown protocol ("%s").',
            $protocol));
      }
    }

    shuffle($uris);
    $uri = head($uris);

    $domain = id(new PhutilURI(PhabricatorEnv::getURI('/')))->getDomain();

    $client = id(new ConduitClient($uri))
      ->setHost($domain);

    $token = PhabricatorConduitToken::loadClusterTokenForUser($user);
    if ($token) {
      $client->setConduitToken($token->getToken());
    }

    return $client->callMethodSynchronous($method, $params);
  }

  public function execute() {
    return $this->executeQuery();
  }

  abstract protected function executeQuery();


/* -(  Query Utilities  )---------------------------------------------------- */


  final public static function loadCommitsByIdentifiers(
    array $identifiers,
    DiffusionRequest $drequest) {
    if (!$identifiers) {
      return array();
    }

    $commits = array();
    $commit_data = array();

    $repository = $drequest->getRepository();

    $commits = id(new PhabricatorRepositoryCommit())->loadAllWhere(
      'repositoryID = %d AND commitIdentifier IN (%Ls)',
        $repository->getID(),
      $identifiers);
    $commits = mpull($commits, null, 'getCommitIdentifier');

    // Build empty commit objects for every commit, so we can show unparsed
    // commits in history views (as "Importing") instead of not showing them.
    // This makes the process of importing and parsing commits clearer to the
    // user.

    $commit_list = array();
    foreach ($identifiers as $identifier) {
      $commit_obj = idx($commits, $identifier);
      if (!$commit_obj) {
        $commit_obj = new PhabricatorRepositoryCommit();
        $commit_obj->setRepositoryID($repository->getID());
        $commit_obj->setCommitIdentifier($identifier);
        $commit_obj->makeEphemeral();
      }
      $commit_list[$identifier] = $commit_obj;
    }
    $commits = $commit_list;

    $commit_ids = array_filter(mpull($commits, 'getID'));
    if ($commit_ids) {
      $commit_data = id(new PhabricatorRepositoryCommitData())->loadAllWhere(
        'commitID in (%Ld)',
        $commit_ids);
      $commit_data = mpull($commit_data, null, 'getCommitID');
    }

    foreach ($commits as $commit) {
      if (!$commit->getID()) {
        continue;
      }
      if (idx($commit_data, $commit->getID())) {
        $commit->attachCommitData($commit_data[$commit->getID()]);
      }
    }

    return $commits;
  }

  final public static function loadHistoryForCommitIdentifiers(
    array $identifiers,
    DiffusionRequest $drequest) {

    if (!$identifiers) {
      return array();
    }

    $repository = $drequest->getRepository();
    $commits = self::loadCommitsByIdentifiers($identifiers, $drequest);

    if (!$commits) {
      return array();
    }

    $path = $drequest->getPath();

    $conn_r = $repository->establishConnection('r');

    $path_normal = DiffusionPathIDQuery::normalizePath($path);
    $paths = queryfx_all(
      $conn_r,
      'SELECT id, path FROM %T WHERE pathHash IN (%Ls)',
      PhabricatorRepository::TABLE_PATH,
      array(md5($path_normal)));
    $paths = ipull($paths, 'id', 'path');
    $path_id = idx($paths, $path_normal);

    $commit_ids = array_filter(mpull($commits, 'getID'));

    $path_changes = array();
    if ($path_id && $commit_ids) {
      $path_changes = queryfx_all(
        $conn_r,
        'SELECT * FROM %T WHERE commitID IN (%Ld) AND pathID = %d',
        PhabricatorRepository::TABLE_PATHCHANGE,
        $commit_ids,
        $path_id);
      $path_changes = ipull($path_changes, null, 'commitID');
    }

    $history = array();
    foreach ($identifiers as $identifier) {
      $item = new DiffusionPathChange();
      $item->setCommitIdentifier($identifier);
      $commit = idx($commits, $identifier);
      if ($commit) {
        $item->setCommit($commit);
        try {
          $item->setCommitData($commit->getCommitData());
        } catch (Exception $ex) {
          // Ignore, commit just doesn't have data.
        }
        $change = idx($path_changes, $commit->getID());
        if ($change) {
          $item->setChangeType($change['changeType']);
          $item->setFileType($change['fileType']);
        }
      }
      $history[] = $item;
    }

    return $history;
  }
}
