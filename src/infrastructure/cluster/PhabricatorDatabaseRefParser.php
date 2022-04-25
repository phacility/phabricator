<?php

final class PhabricatorDatabaseRefParser
  extends Phobject {

  private $defaultPort = 3306;
  private $defaultUser;
  private $defaultPass;

  public function setDefaultPort($default_port) {
    $this->defaultPort = $default_port;
    return $this;
  }

  public function getDefaultPort() {
    return $this->defaultPort;
  }

  public function setDefaultUser($default_user) {
    $this->defaultUser = $default_user;
    return $this;
  }

  public function getDefaultUser() {
    return $this->defaultUser;
  }

  public function setDefaultPass($default_pass) {
    $this->defaultPass = $default_pass;
    return $this;
  }

  public function getDefaultPass() {
    return $this->defaultPass;
  }

  public function newRefs(array $config) {
    $default_port = $this->getDefaultPort();
    $default_user = $this->getDefaultUser();
    $default_pass = $this->getDefaultPass();

    $refs = array();

    $master_count = 0;
    foreach ($config as $key => $server) {
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
      $is_master = ($role == 'master');

      $use_persistent = (bool)idx($server, 'persistent', false);

      $ref = id(new PhabricatorDatabaseRef())
        ->setHost($host)
        ->setPort($port)
        ->setUser($user)
        ->setPass($pass)
        ->setDisabled($disabled)
        ->setIsMaster($is_master)
        ->setUsePersistentConnections($use_persistent);

      if ($is_master) {
        $master_count++;
      }

      $refs[$key] = $ref;
    }

    $is_partitioned = ($master_count > 1);
    if ($is_partitioned) {
      $default_ref = null;
      $partition_map = array();
      foreach ($refs as $key => $ref) {
        if (!$ref->getIsMaster()) {
          continue;
        }

        $server = $config[$key];
        $partition = idx($server, 'partition');
        if (!is_array($partition)) {
          throw new Exception(
            pht(
              'This server is configured with multiple master databases, '.
              'but master "%s" is missing a "partition" configuration key to '.
              'define application partitioning.',
              $ref->getRefKey()));
        }

        $application_map = array();
        foreach ($partition as $application) {
          if ($application === 'default') {
            if ($default_ref) {
              throw new Exception(
                pht(
                  'Multiple masters (databases "%s" and "%s") specify that '.
                  'they are the "default" partition. Only one master may be '.
                  'the default.',
                  $ref->getRefKey(),
                  $default_ref->getRefKey()));
            } else {
              $default_ref = $ref;
              $ref->setIsDefaultPartition(true);
            }
          } else if (isset($partition_map[$application])) {
            throw new Exception(
              pht(
                'Multiple masters (databases "%s" and "%s") specify that '.
                'they are the partition for application "%s". Each '.
                'application may be allocated to only one partition.',
                $partition_map[$application]->getRefKey(),
                $ref->getRefKey(),
                $application));
          } else {
            // TODO: We should check that the application is valid, to
            // prevent typos in application names. However, we do not
            // currently have an efficient way to enumerate all of the valid
            // application database names.

            $partition_map[$application] = $ref;
            $application_map[$application] = $application;
          }
        }

        $ref->setApplicationMap($application_map);
      }
    } else {
      // If we only have one master, make it the default.
      foreach ($refs as $ref) {
        if ($ref->getIsMaster()) {
          $ref->setIsDefaultPartition(true);
        }
      }
    }

    $ref_map = array();
    $master_keys = array();
    foreach ($refs as $ref) {
      $ref_key = $ref->getRefKey();
      if (isset($ref_map[$ref_key])) {
        throw new Exception(
          pht(
            'Multiple configured databases have the same internal '.
            'key, "%s". You may have listed a database multiple times.',
            $ref_key));
      } else {
        $ref_map[$ref_key] = $ref;
        if ($ref->getIsMaster()) {
          $master_keys[] = $ref_key;
        }
      }
    }

    foreach ($refs as $key => $ref) {
      if ($ref->getIsMaster()) {
        continue;
      }

      $server = $config[$key];

      $partition = idx($server, 'partition');
      if ($partition !== null) {
        throw new Exception(
          pht(
            'Database "%s" is configured as a replica, but specifies a '.
            '"partition". Only master databases may have a partition '.
            'configuration. Replicas use the same configuration as the '.
            'master they follow.',
            $ref->getRefKey()));
      }

      $master_key = idx($server, 'master');
      if ($master_key === null) {
        if ($is_partitioned) {
          throw new Exception(
            pht(
              'Database "%s" is configured as a replica, but does not '.
              'specify which "master" it follows in configuration. Valid '.
              'masters are: %s.',
              $ref->getRefKey(),
              implode(', ', $master_keys)));
        } else if ($master_keys) {
          $master_key = head($master_keys);
        } else {
          throw new Exception(
            pht(
              'Database "%s" is configured as a replica, but there is no '.
              'master configured.',
              $ref->getRefKey()));
        }
      }

      if (!isset($ref_map[$master_key])) {
        throw new Exception(
          pht(
            'Database "%s" is configured as a replica and specifies a '.
            'master ("%s"), but that master is not a valid master. Valid '.
            'masters are: %s.',
            $ref->getRefKey(),
            $master_key,
            implode(', ', $master_keys)));
      }

      $master_ref = $ref_map[$master_key];
      $ref->setMasterRef($ref_map[$master_key]);
      $master_ref->addReplicaRef($ref);
    }

    return array_values($refs);
  }

}
