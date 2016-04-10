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

  const KEY_REFS = 'cluster.db.refs';

  private $host;
  private $port;
  private $user;
  private $pass;
  private $disabled;
  private $isMaster;

  private $connectionLatency;
  private $connectionStatus;
  private $connectionMessage;

  private $replicaStatus;
  private $replicaMessage;
  private $replicaDelay;

  private $didFailToConnect;

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
        'label' => pht('Not Replicating'),
      ),
      self::REPLICATION_SLOW => array(
        'icon' => 'fa-hourglass',
        'color' => 'red',
        'label' => pht('Slow Replication'),
      ),
    );
  }

  public static function getLiveRefs() {
    $cache = PhabricatorCaches::getRequestCache();

    $refs = $cache->getKey(self::KEY_REFS);
    if (!$refs) {
      $refs = self::newRefs();
      $cache->setKey(self::KEY_REFS, $refs);
    }

    return $refs;
  }

  public static function newRefs() {
    $refs = array();

    $default_port = PhabricatorEnv::getEnvConfig('mysql.port');
    $default_port = nonempty($default_port, 3306);

    $default_user = PhabricatorEnv::getEnvConfig('mysql.user');

    $default_pass = PhabricatorEnv::getEnvConfig('mysql.pass');
    $default_pass = new PhutilOpaqueEnvelope($default_pass);

    $config = PhabricatorEnv::getEnvConfig('cluster.databases');
    foreach ($config as $server) {
      $host = $server['host'];
      $port = idx($server, 'port', $default_port);
      $user = idx($server, 'user', $default_user);
      $disabled = idx($server, 'disabled', false);

      $pass = idx($server, 'pass');
      if ($pass) {
        $pass = new PhutilOpaqueEnvelope($pass);
      } else {
        $pass = clone $default_pass;
      }

      $role = $server['role'];

      $ref = id(new self())
        ->setHost($host)
        ->setPort($port)
        ->setUser($user)
        ->setPass($pass)
        ->setDisabled($disabled)
        ->setIsMaster(($role == 'master'));

      $refs[] = $ref;
    }

    return $refs;
  }

  public static function queryAll() {
    $refs = self::newRefs();

    foreach ($refs as $ref) {
      if ($ref->getDisabled()) {
        continue;
      }

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
          $latency = (int)idx($replica_status, 'Seconds_Behind_Master');
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

    return $refs;
  }

  public function newManagementConnection() {
    return $this->newConnection(
      array(
        'retries' => 0,
        'timeout' => 3,
      ));
  }

  public function newApplicationConnection($database) {
    return $this->newConnection(
      array(
        'database' => $database,
      ));
  }

  public function isSevered() {
    return $this->didFailToConnect;
  }

  public function isReachable(AphrontDatabaseConnection $connection) {
    if ($this->isSevered()) {
      return false;
    }

    try {
      $connection->openConnection();
      $reachable = true;
    } catch (Exception $ex) {
      $reachable = false;
    }

    if (!$reachable) {
      $this->didFailToConnect = true;
    }

    return $reachable;
  }

  public static function getMasterDatabaseRef() {
    $refs = self::getLiveRefs();

    if (!$refs) {
      $conf = PhabricatorEnv::newObjectFromConfig(
        'mysql.configuration-provider',
        array(null, 'w', null));

      return id(new self())
        ->setHost($conf->getHost())
        ->setPort($conf->getPort())
        ->setUser($conf->getUser())
        ->setPass($conf->getPassword())
        ->setIsMaster(true);
    }

    $master = null;
    foreach ($refs as $ref) {
      if ($ref->getDisabled()) {
        continue;
      }
      if ($ref->getIsMaster()) {
        return $ref;
      }
    }

    return null;
  }

  public static function getReplicaDatabaseRef() {
    $refs = self::getLiveRefs();

    if (!$refs) {
      return null;
    }

    // TODO: We may have multiple replicas to choose from, and could make
    // more of an effort to pick the "best" one here instead of always
    // picking the first one. Once we've picked one, we should try to use
    // the same replica for the rest of the request, though.

    foreach ($refs as $ref) {
      if ($ref->getDisabled()) {
        continue;
      }
      if ($ref->getIsMaster()) {
        continue;
      }
      return $ref;
    }

    return null;
  }

  private function newConnection(array $options) {
    $spec = $options + array(
      'user' => $this->getUser(),
      'pass' => $this->getPass(),
      'host' => $this->getHost(),
      'port' => $this->getPort(),
      'database' => null,
      'retries' => 3,
      'timeout' => 15,
    );

    return PhabricatorEnv::newObjectFromConfig(
      'mysql.implementation',
      array(
        $spec,
      ));
  }

}
