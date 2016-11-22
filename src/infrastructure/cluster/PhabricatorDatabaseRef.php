<?php

final class PhabricatorDatabaseRef
  extends Phobject {

  const STATUS_OKAY = 'okay';
  const STATUS_FAIL = 'fail';
  const STATUS_AUTH = 'auth';
  const STATUS_REPLICATION_CLIENT = 'replication-client';

  const REPLICATION_OKAY = 'okay';
  const REPLICATION_MASTER_REPLICA = 'master-replica';
  const REPLICATION_REPLICA_NONE = 'replica-none';
  const REPLICATION_SLOW = 'replica-slow';
  const REPLICATION_NOT_REPLICATING = 'not-replicating';

  const KEY_REFS = 'cluster.db.refs';
  const KEY_INDIVIDUAL = 'cluster.db.individual';

  private $host;
  private $port;
  private $user;
  private $pass;
  private $disabled;
  private $isMaster;
  private $isIndividual;

  private $connectionLatency;
  private $connectionStatus;
  private $connectionMessage;

  private $replicaStatus;
  private $replicaMessage;
  private $replicaDelay;

  private $healthRecord;
  private $didFailToConnect;

  private $isDefaultPartition;
  private $applicationMap = array();
  private $masterRef;
  private $replicaRefs = array();
  private $usePersistentConnections;

  public function setHost($host) {
    $this->host = $host;
    return $this;
  }

  public function getHost() {
    return $this->host;
  }

  public function setPort($port) {
    $this->port = $port;
    return $this;
  }

  public function getPort() {
    return $this->port;
  }

  public function setUser($user) {
    $this->user = $user;
    return $this;
  }

  public function getUser() {
    return $this->user;
  }

  public function setPass(PhutilOpaqueEnvelope $pass) {
    $this->pass = $pass;
    return $this;
  }

  public function getPass() {
    return $this->pass;
  }

  public function setIsMaster($is_master) {
    $this->isMaster = $is_master;
    return $this;
  }

  public function getIsMaster() {
    return $this->isMaster;
  }

  public function setDisabled($disabled) {
    $this->disabled = $disabled;
    return $this;
  }

  public function getDisabled() {
    return $this->disabled;
  }

  public function setConnectionLatency($connection_latency) {
    $this->connectionLatency = $connection_latency;
    return $this;
  }

  public function getConnectionLatency() {
    return $this->connectionLatency;
  }

  public function setConnectionStatus($connection_status) {
    $this->connectionStatus = $connection_status;
    return $this;
  }

  public function getConnectionStatus() {
    if ($this->connectionStatus === null) {
      throw new PhutilInvalidStateException('queryAll');
    }

    return $this->connectionStatus;
  }

  public function setConnectionMessage($connection_message) {
    $this->connectionMessage = $connection_message;
    return $this;
  }

  public function getConnectionMessage() {
    return $this->connectionMessage;
  }

  public function setReplicaStatus($replica_status) {
    $this->replicaStatus = $replica_status;
    return $this;
  }

  public function getReplicaStatus() {
    return $this->replicaStatus;
  }

  public function setReplicaMessage($replica_message) {
    $this->replicaMessage = $replica_message;
    return $this;
  }

  public function getReplicaMessage() {
    return $this->replicaMessage;
  }

  public function setReplicaDelay($replica_delay) {
    $this->replicaDelay = $replica_delay;
    return $this;
  }

  public function getReplicaDelay() {
    return $this->replicaDelay;
  }

  public function setIsIndividual($is_individual) {
    $this->isIndividual = $is_individual;
    return $this;
  }

  public function getIsIndividual() {
    return $this->isIndividual;
  }

  public function setIsDefaultPartition($is_default_partition) {
    $this->isDefaultPartition = $is_default_partition;
    return $this;
  }

  public function getIsDefaultPartition() {
    return $this->isDefaultPartition;
  }

  public function setUsePersistentConnections($use_persistent_connections) {
    $this->usePersistentConnections = $use_persistent_connections;
    return $this;
  }

  public function getUsePersistentConnections() {
    return $this->usePersistentConnections;
  }

  public function setApplicationMap(array $application_map) {
    $this->applicationMap = $application_map;
    return $this;
  }

  public function getApplicationMap() {
    return $this->applicationMap;
  }

  public function getPartitionStateForCommit() {
    $state = PhabricatorEnv::getEnvConfig('cluster.databases');
    foreach ($state as $key => $value) {
      // Don't store passwords, since we don't care if they differ and
      // users may find it surprising.
      unset($state[$key]['pass']);
    }

    return phutil_json_encode($state);
  }

  public function setMasterRef(PhabricatorDatabaseRef $master_ref) {
    $this->masterRef = $master_ref;
    return $this;
  }

  public function getMasterRef() {
    return $this->masterRef;
  }

  public function addReplicaRef(PhabricatorDatabaseRef $replica_ref) {
    $this->replicaRefs[] = $replica_ref;
    return $this;
  }

  public function getReplicaRefs() {
    return $this->replicaRefs;
  }


  public function getRefKey() {
    $host = $this->getHost();

    $port = $this->getPort();
    if (strlen($port)) {
      return "{$host}:{$port}";
    }

    return $host;
  }

  public static function getConnectionStatusMap() {
    return array(
      self::STATUS_OKAY => array(
        'icon' => 'fa-exchange',
        'color' => 'green',
        'label' => pht('Okay'),
      ),
      self::STATUS_FAIL => array(
        'icon' => 'fa-times',
        'color' => 'red',
        'label' => pht('Failed'),
      ),
      self::STATUS_AUTH => array(
        'icon' => 'fa-key',
        'color' => 'red',
        'label' => pht('Invalid Credentials'),
      ),
      self::STATUS_REPLICATION_CLIENT => array(
        'icon' => 'fa-eye-slash',
        'color' => 'yellow',
        'label' => pht('Missing Permission'),
      ),
    );
  }

  public static function getReplicaStatusMap() {
    return array(
      self::REPLICATION_OKAY => array(
        'icon' => 'fa-download',
        'color' => 'green',
        'label' => pht('Okay'),
      ),
      self::REPLICATION_MASTER_REPLICA => array(
        'icon' => 'fa-database',
        'color' => 'red',
        'label' => pht('Replicating Master'),
      ),
      self::REPLICATION_REPLICA_NONE => array(
        'icon' => 'fa-download',
        'color' => 'red',
        'label' => pht('Not A Replica'),
      ),
      self::REPLICATION_SLOW => array(
        'icon' => 'fa-hourglass',
        'color' => 'red',
        'label' => pht('Slow Replication'),
      ),
      self::REPLICATION_NOT_REPLICATING => array(
        'icon' => 'fa-exclamation-triangle',
        'color' => 'red',
        'label' => pht('Not Replicating'),
      ),
    );
  }

  public static function getClusterRefs() {
    $cache = PhabricatorCaches::getRequestCache();

    $refs = $cache->getKey(self::KEY_REFS);
    if (!$refs) {
      $refs = self::newRefs();
      $cache->setKey(self::KEY_REFS, $refs);
    }

    return $refs;
  }

  public static function getLiveIndividualRef() {
    $cache = PhabricatorCaches::getRequestCache();

    $ref = $cache->getKey(self::KEY_INDIVIDUAL);
    if (!$ref) {
      $ref = self::newIndividualRef();
      $cache->setKey(self::KEY_INDIVIDUAL, $ref);
    }

    return $ref;
  }

  public static function newRefs() {
    $default_port = PhabricatorEnv::getEnvConfig('mysql.port');
    $default_port = nonempty($default_port, 3306);

    $default_user = PhabricatorEnv::getEnvConfig('mysql.user');

    $default_pass = PhabricatorEnv::getEnvConfig('mysql.pass');
    $default_pass = new PhutilOpaqueEnvelope($default_pass);

    $config = PhabricatorEnv::getEnvConfig('cluster.databases');

    return id(new PhabricatorDatabaseRefParser())
      ->setDefaultPort($default_port)
      ->setDefaultUser($default_user)
      ->setDefaultPass($default_pass)
      ->newRefs($config);
  }

  public static function queryAll() {
    $refs = self::getActiveDatabaseRefs();
    return self::queryRefs($refs);
  }

  private static function queryRefs(array $refs) {
    foreach ($refs as $ref) {
      $conn = $ref->newManagementConnection();

      $t_start = microtime(true);
      $replica_status = false;
      try {
        $replica_status = queryfx_one($conn, 'SHOW SLAVE STATUS');
        $ref->setConnectionStatus(self::STATUS_OKAY);
      } catch (AphrontAccessDeniedQueryException $ex) {
        $ref->setConnectionStatus(self::STATUS_REPLICATION_CLIENT);
        $ref->setConnectionMessage(
          pht(
            'No permission to run "SHOW SLAVE STATUS". Grant this user '.
            '"REPLICATION CLIENT" permission to allow Phabricator to '.
            'monitor replica health.'));
      } catch (AphrontInvalidCredentialsQueryException $ex) {
        $ref->setConnectionStatus(self::STATUS_AUTH);
        $ref->setConnectionMessage($ex->getMessage());
      } catch (AphrontQueryException $ex) {
        $ref->setConnectionStatus(self::STATUS_FAIL);

        $class = get_class($ex);
        $message = $ex->getMessage();
        $ref->setConnectionMessage(
          pht(
            '%s: %s',
            get_class($ex),
            $ex->getMessage()));
      }
      $t_end = microtime(true);
      $ref->setConnectionLatency($t_end - $t_start);

      if ($replica_status !== false) {
        $is_replica = (bool)$replica_status;
        if ($ref->getIsMaster() && $is_replica) {
          $ref->setReplicaStatus(self::REPLICATION_MASTER_REPLICA);
          $ref->setReplicaMessage(
            pht(
              'This host has a "master" role, but is replicating data from '.
              'another host ("%s")!',
              idx($replica_status, 'Master_Host')));
        } else if (!$ref->getIsMaster() && !$is_replica) {
          $ref->setReplicaStatus(self::REPLICATION_REPLICA_NONE);
          $ref->setReplicaMessage(
            pht(
              'This host has a "replica" role, but is not replicating data '.
              'from a master (no output from "SHOW SLAVE STATUS").'));
        } else {
          $ref->setReplicaStatus(self::REPLICATION_OKAY);
        }

        if ($is_replica) {
          $latency = idx($replica_status, 'Seconds_Behind_Master');
          if (!strlen($latency)) {
            $ref->setReplicaStatus(self::REPLICATION_NOT_REPLICATING);
          } else {
            $latency = (int)$latency;
            $ref->setReplicaDelay($latency);
            if ($latency > 30) {
              $ref->setReplicaStatus(self::REPLICATION_SLOW);
              $ref->setReplicaMessage(
                pht(
                  'This replica is lagging far behind the master. Data is at '.
                  'risk!'));
            }
          }
        }
      }
    }

    return $refs;
  }

  public function newManagementConnection() {
    return $this->newConnection(
      array(
        'retries' => 0,
        'timeout' => 2,
      ));
  }

  public function newApplicationConnection($database) {
    return $this->newConnection(
      array(
        'database' => $database,
      ));
  }

  public function isSevered() {
    // If we only have an individual database, never sever our connection to
    // it, at least for now. It's possible that using the same severing rules
    // might eventually make sense to help alleviate load-related failures,
    // but we should wait for all the cluster stuff to stabilize first.
    if ($this->getIsIndividual()) {
      return false;
    }

    if ($this->didFailToConnect) {
      return true;
    }

    $record = $this->getHealthRecord();
    $is_healthy = $record->getIsHealthy();
    if (!$is_healthy) {
      return true;
    }

    return false;
  }

  public function isReachable(AphrontDatabaseConnection $connection) {
    $record = $this->getHealthRecord();
    $should_check = $record->getShouldCheck();

    if ($this->isSevered() && !$should_check) {
      return false;
    }

    try {
      $connection->openConnection();
      $reachable = true;
    } catch (AphrontSchemaQueryException $ex) {
      // We get one of these if the database we're trying to select does not
      // exist. In this case, just re-throw the exception. This is expected
      // during first-time setup, when databases like "config" will not exist
      // yet.
      throw $ex;
    } catch (Exception $ex) {
      $reachable = false;
    }

    if ($should_check) {
      $record->didHealthCheck($reachable);
    }

    if (!$reachable) {
      $this->didFailToConnect = true;
    }

    return $reachable;
  }

  public function checkHealth() {
    $health = $this->getHealthRecord();

    $should_check = $health->getShouldCheck();
    if ($should_check) {
      // This does an implicit health update.
      $connection = $this->newManagementConnection();
      $this->isReachable($connection);
    }

    return $this;
  }

  public function getHealthRecord() {
    if (!$this->healthRecord) {
      $this->healthRecord = new PhabricatorDatabaseHealthRecord($this);
    }
    return $this->healthRecord;
  }

  public static function getActiveDatabaseRefs() {
    $refs = array();

    foreach (self::getMasterDatabaseRefs() as $ref) {
      $refs[] = $ref;
    }

    foreach (self::getReplicaDatabaseRefs() as $ref) {
      $refs[] = $ref;
    }

    return $refs;
  }

  public static function getAllMasterDatabaseRefs() {
    $refs = self::getClusterRefs();

    if (!$refs) {
      return array(self::getLiveIndividualRef());
    }

    $masters = array();
    foreach ($refs as $ref) {
      if ($ref->getIsMaster()) {
        $masters[] = $ref;
      }
    }

    return $masters;
  }

  public static function getMasterDatabaseRefs() {
    $refs = self::getAllMasterDatabaseRefs();
    return self::getEnabledRefs($refs);
  }

  public function isApplicationHost($database) {
    return isset($this->applicationMap[$database]);
  }

  public function loadRawMySQLConfigValue($key) {
    $conn = $this->newManagementConnection();

    try {
      $value = queryfx_one($conn, 'SELECT @@%Q', $key);
      $value = $value['@@'.$key];
    } catch (AphrontQueryException $ex) {
      $value = null;
    }

    return $value;
  }

  public static function getMasterDatabaseRefForApplication($application) {
    $masters = self::getMasterDatabaseRefs();

    $application_master = null;
    $default_master = null;
    foreach ($masters as $master) {
      if ($master->isApplicationHost($application)) {
        $application_master = $master;
        break;
      }
      if ($master->getIsDefaultPartition()) {
        $default_master = $master;
      }
    }

    if ($application_master) {
      $masters = array($application_master);
    } else if ($default_master) {
      $masters = array($default_master);
    } else {
      $masters = array();
    }

    $masters = self::getEnabledRefs($masters);
    $master = head($masters);

    return $master;
  }

  public static function newIndividualRef() {
    $default_user = PhabricatorEnv::getEnvConfig('mysql.user');
    $default_pass = new PhutilOpaqueEnvelope(
      PhabricatorEnv::getEnvConfig('mysql.pass'));
    $default_host = PhabricatorEnv::getEnvConfig('mysql.host');
    $default_port = PhabricatorEnv::getEnvConfig('mysql.port');

    return id(new self())
      ->setUser($default_user)
      ->setPass($default_pass)
      ->setHost($default_host)
      ->setPort($default_port)
      ->setIsIndividual(true)
      ->setIsMaster(true)
      ->setIsDefaultPartition(true)
      ->setUsePersistentConnections(false);
  }

  public static function getAllReplicaDatabaseRefs() {
    $refs = self::getClusterRefs();

    if (!$refs) {
      return array();
    }

    $replicas = array();
    foreach ($refs as $ref) {
      if ($ref->getIsMaster()) {
        continue;
      }

      $replicas[] = $ref;
    }

    return $replicas;
  }

  public static function getReplicaDatabaseRefs() {
    $refs = self::getAllReplicaDatabaseRefs();
    return self::getEnabledRefs($refs);
  }

  private static function getEnabledRefs(array $refs) {
    foreach ($refs as $key => $ref) {
      if ($ref->getDisabled()) {
        unset($refs[$key]);
      }
    }
    return $refs;
  }

  public static function getReplicaDatabaseRefForApplication($application) {
    $replicas = self::getReplicaDatabaseRefs();

    $application_replicas = array();
    $default_replicas = array();
    foreach ($replicas as $replica) {
      $master = $replica->getMaster();

      if ($master->isApplicationHost($application)) {
        $application_replicas[] = $replica;
      }

      if ($master->getIsDefaultPartition()) {
        $default_replicas[] = $replica;
      }
    }

    if ($application_replicas) {
      $replicas = $application_replicas;
    } else {
      $replicas = $default_replicas;
    }

    $replicas = self::getEnabledRefs($replicas);

    // TODO: We may have multiple replicas to choose from, and could make
    // more of an effort to pick the "best" one here instead of always
    // picking the first one. Once we've picked one, we should try to use
    // the same replica for the rest of the request, though.

    return head($replicas);
  }

  private function newConnection(array $options) {
    // If we believe the database is unhealthy, don't spend as much time
    // trying to connect to it, since it's likely to continue to fail and
    // hammering it can only make the problem worse.
    $record = $this->getHealthRecord();
    if ($record->getIsHealthy()) {
      $default_retries = 3;
      $default_timeout = 10;
    } else {
      $default_retries = 0;
      $default_timeout = 2;
    }

    $spec = $options + array(
      'user' => $this->getUser(),
      'pass' => $this->getPass(),
      'host' => $this->getHost(),
      'port' => $this->getPort(),
      'database' => null,
      'retries' => $default_retries,
      'timeout' => $default_timeout,
      'persistent' => $this->getUsePersistentConnections(),
    );

    $is_cli = (php_sapi_name() == 'cli');

    $use_persistent = false;
    if (!empty($spec['persistent']) && !$is_cli) {
      $use_persistent = true;
    }
    unset($spec['persistent']);

    $connection = self::newRawConnection($spec);

    // If configured, use persistent connections. See T11672 for details.
    if ($use_persistent) {
      $connection->setPersistent($use_persistent);
    }

    // Unless this is a script running from the CLI, prevent any query from
    // running for more than 30 seconds. See T10849 for details.
    if (!$is_cli) {
      $connection->setQueryTimeout(30);
    }

    return $connection;
  }

  public static function newRawConnection(array $options) {
    if (extension_loaded('mysqli')) {
      return new AphrontMySQLiDatabaseConnection($options);
    } else {
      return new AphrontMySQLDatabaseConnection($options);
    }
  }

}
