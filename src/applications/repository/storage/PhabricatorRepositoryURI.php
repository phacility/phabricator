<?php

final class PhabricatorRepositoryURI
  extends PhabricatorRepositoryDAO {

  protected $repositoryPHID;
  protected $uri;
  protected $builtinProtocol;
  protected $builtinIdentifier;
  protected $credentialPHID;
  protected $ioType;
  protected $displayType;
  protected $isDisabled;

  private $repository = self::ATTACHABLE;

  const BUILTIN_PROTOCOL_SSH = 'ssh';
  const BUILTIN_PROTOCOL_HTTP = 'http';
  const BUILTIN_PROTOCOL_HTTPS = 'https';

  const BUILTIN_IDENTIFIER_ID = 'id';
  const BUILTIN_IDENTIFIER_SHORTNAME = 'shortname';
  const BUILTIN_IDENTIFIER_CALLSIGN = 'callsign';

  const DISPLAY_DEFAULT = 'default';
  const DISPLAY_NEVER = 'never';
  const DISPLAY_ALWAYS = 'always';

  const IO_DEFAULT = 'default';
  const IO_OBSERVE = 'observe';
  const IO_MIRROR = 'mirror';
  const IO_NONE = 'none';
  const IO_READ = 'read';
  const IO_READWRITE = 'readwrite';

  protected function getConfiguration() {
    return array(
      self::CONFIG_AUX_PHID => true,
      self::CONFIG_COLUMN_SCHEMA => array(
        'uri' => 'text255',
        'builtinProtocol' => 'text32?',
        'builtinIdentifier' => 'text32?',
        'credentialPHID' => 'phid?',
        'ioType' => 'text32',
        'displayType' => 'text32',
        'isDisabled' => 'bool',
      ),
      self::CONFIG_KEY_SCHEMA => array(
        'key_builtin' => array(
          'columns' => array(
            'repositoryPHID',
            'builtinProtocol',
            'builtinIdentifier',
          ),
          'unique' => true,
        ),
      ),
    ) + parent::getConfiguration();
  }

  public static function initializeNewURI(PhabricatorRepository $repository) {
    return id(new self())
      ->attachRepository($repository)
      ->setRepositoryPHID($repository->getPHID())
      ->setIoType(self::IO_DEFAULT)
      ->setDisplayType(self::DISPLAY_DEFAULT)
      ->setIsDisabled(0);
  }

  public function attachRepository(PhabricatorRepository $repository) {
    $this->repository = $repository;
    return $this;
  }

  public function getRepository() {
    return $this->assertAttached($this->repository);
  }

  public function getRepositoryURIBuiltinKey() {
    if (!$this->getBuiltinProtocol()) {
      return null;
    }

    $parts = array(
      $this->getBuiltinProtocol(),
      $this->getBuiltinIdentifier(),
    );
    return implode('.', $parts);
  }

  public function isBuiltin() {
    return (bool)$this->getBuiltinProtocol();
  }

  public function getEffectiveDisplayType() {
    $display = $this->getDisplayType();

    if ($display != self::IO_DEFAULT) {
      return $display;
    }

    switch ($this->getEffectiveIOType()) {
      case self::IO_MIRROR:
      case self::IO_OBSERVE:
        return self::DISPLAY_NEVER;
      case self::IO_NONE:
        if ($this->isBuiltin()) {
          return self::DISPLAY_NEVER;
        } else {
          return self::DISPLAY_ALWAYS;
        }
      case self::IO_READ:
      case self::IO_READWRITE:
        // By default, only show the "best" version of the builtin URI, not the
        // other redundant versions.
        if ($this->isBuiltin()) {
          $repository = $this->getRepository();
          $other_uris = $repository->getURIs();

          $identifier_value = array(
            self::BUILTIN_IDENTIFIER_CALLSIGN => 3,
            self::BUILTIN_IDENTIFIER_SHORTNAME => 2,
            self::BUILTIN_IDENTIFIER_ID => 1,
          );

          $have_identifiers = array();
          foreach ($other_uris as $other_uri) {
            if ($other_uri->getIsDisabled()) {
              continue;
            }

            $identifier = $other_uri->getBuiltinIdentifier();
            if (!$identifier) {
              continue;
            }

            $have_identifiers[$identifier] = $identifier_value[$identifier];
          }

          $best_identifier = max($have_identifiers);
          $this_identifier = $identifier_value[$this->getBuiltinIdentifier()];

          if ($this_identifier < $best_identifier) {
            return self::DISPLAY_NEVER;
          }
        }

        return self::DISPLAY_ALWAYS;
    }
  }


  public function getEffectiveIOType() {
    $io = $this->getIoType();

    if ($io != self::IO_DEFAULT) {
      return $io;
    }

    if ($this->isBuiltin()) {
      $repository = $this->getRepository();
      $other_uris = $repository->getURIs();

      $any_observe = false;
      foreach ($other_uris as $other_uri) {
        if ($other_uri->getIoType() == self::IO_OBSERVE) {
          $any_observe = true;
          break;
        }
      }

      if ($any_observe) {
        return self::IO_READ;
      } else {
        return self::IO_READWRITE;
      }
    }

    return self::IO_IGNORE;
  }


  public function getDisplayURI() {
    $uri = new PhutilURI($this->getURI());

    $protocol = $this->getForcedProtocol();
    if ($protocol) {
      $uri->setProtocol($protocol);
    }

    $user = $this->getForcedUser();
    if ($user) {
      $uri->setUser($user);
    }

    $host = $this->getForcedHost();
    if ($host) {
      $uri->setDomain($host);
    }

    $port = $this->getForcedPort();
    if ($port) {
      $uri->setPort($port);
    }

    $path = $this->getForcedPath();
    if ($path) {
      $uri->setPath($path);
    }

    return $uri;
  }

  private function getForcedProtocol() {
    switch ($this->getBuiltinProtocol()) {
      case self::BUILTIN_PROTOCOL_SSH:
        return 'ssh';
      case self::BUILTIN_PROTOCOL_HTTP:
        return 'http';
      case self::BUILTIN_PROTOCOL_HTTPS:
        return 'https';
      default:
        return null;
    }
  }

  private function getForcedUser() {
    switch ($this->getBuiltinProtocol()) {
      case self::BUILTIN_PROTOCOL_SSH:
        return AlmanacKeys::getClusterSSHUser();
      default:
        return null;
    }
  }

  private function getForcedHost() {
    $phabricator_uri = PhabricatorEnv::getURI('/');
    $phabricator_uri = new PhutilURI($phabricator_uri);

    $phabricator_host = $phabricator_uri->getDomain();

    switch ($this->getBuiltinProtocol()) {
      case self::BUILTIN_PROTOCOL_SSH:
        $ssh_host = PhabricatorEnv::getEnvConfig('diffusion.ssh-host');
        if ($ssh_host !== null) {
          return $ssh_host;
        }
        return $phabricator_host;
      case self::BUILTIN_PROTOCOL_HTTP:
      case self::BUILTIN_PROTOCOL_HTTPS:
        return $phabricator_host;
      default:
        return null;
    }
  }

  private function getForcedPort() {
    switch ($this->getBuiltinProtocol()) {
      case self::BUILTIN_PROTOCOL_SSH:
        return PhabricatorEnv::getEnvConfig('diffusion.ssh-port');
      case self::BUILTIN_PROTOCOL_HTTP:
      case self::BUILTIN_PROTOCOL_HTTPS:
      default:
        return null;
    }
  }

  private function getForcedPath() {
    if (!$this->isBuiltin()) {
      return null;
    }

    $repository = $this->getRepository();

    $id = $repository->getID();
    $callsign = $repository->getCallsign();
    $short_name = $repository->getRepositorySlug();

    $clone_name = $repository->getCloneName();

    if ($repository->isGit()) {
      $suffix = '.git';
    } else if ($repository->isHg()) {
      $suffix = '/';
    } else {
      $suffix = '';
    }

    switch ($this->getBuiltinIdentifier()) {
      case self::BUILTIN_IDENTIFIER_ID:
        return "/diffusion/{$id}/{$clone_name}{$suffix}";
      case self::BUILTIN_IDENTIFIER_SHORTNAME:
        return "/source/{$short_name}{$suffix}";
      case self::BUILTIN_IDENTIFIER_CALLSIGN:
        return "/diffusion/{$callsign}/{$clone_name}{$suffix}";
      default:
        return null;
    }
  }

}
