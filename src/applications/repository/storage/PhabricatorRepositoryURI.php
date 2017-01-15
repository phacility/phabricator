<?php

final class PhabricatorRepositoryURI
  extends PhabricatorRepositoryDAO
  implements
    PhabricatorApplicationTransactionInterface,
    PhabricatorPolicyInterface,
    PhabricatorExtendedPolicyInterface,
    PhabricatorConduitResultInterface {

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

  public static function initializeNewURI() {
    return id(new self())
      ->setIoType(self::IO_DEFAULT)
      ->setDisplayType(self::DISPLAY_DEFAULT)
      ->setIsDisabled(0);
  }

  public function generatePHID() {
    return PhabricatorPHID::generateNewPHID(
      PhabricatorRepositoryURIPHIDType::TYPECONST);
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

    if ($display != self::DISPLAY_DEFAULT) {
      return $display;
    }

    return $this->getDefaultDisplayType();
  }

  public function getDefaultDisplayType() {
    switch ($this->getEffectiveIOType()) {
      case self::IO_MIRROR:
      case self::IO_OBSERVE:
      case self::IO_NONE:
        return self::DISPLAY_NEVER;
      case self::IO_READ:
      case self::IO_READWRITE:
        // By default, only show the "best" version of the builtin URI, not the
        // other redundant versions.
        $repository = $this->getRepository();
        $other_uris = $repository->getURIs();

        $identifier_value = array(
          self::BUILTIN_IDENTIFIER_SHORTNAME => 3,
          self::BUILTIN_IDENTIFIER_CALLSIGN => 2,
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

        return self::DISPLAY_ALWAYS;
    }

    return self::DISPLAY_NEVER;
  }


  public function getEffectiveIOType() {
    $io = $this->getIoType();

    if ($io != self::IO_DEFAULT) {
      return $io;
    }

    return $this->getDefaultIOType();
  }

  public function getDefaultIOType() {
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

    return self::IO_NONE;
  }

  public function getNormalizedURI() {
    $vcs = $this->getRepository()->getVersionControlSystem();

    $map = array(
      PhabricatorRepositoryType::REPOSITORY_TYPE_GIT =>
        PhabricatorRepositoryURINormalizer::TYPE_GIT,
      PhabricatorRepositoryType::REPOSITORY_TYPE_SVN =>
        PhabricatorRepositoryURINormalizer::TYPE_SVN,
      PhabricatorRepositoryType::REPOSITORY_TYPE_MERCURIAL =>
        PhabricatorRepositoryURINormalizer::TYPE_MERCURIAL,
    );

    $type = $map[$vcs];
    $display = (string)$this->getDisplayURI();

    $normal_uri = new PhabricatorRepositoryURINormalizer($type, $display);

    return $normal_uri->getNormalizedURI();
  }

  public function getDisplayURI() {
    return $this->getURIObject();
  }

  public function getEffectiveURI() {
    return $this->getURIObject();
  }

  public function getURIEnvelope() {
    $uri = $this->getEffectiveURI();

    $command_engine = $this->newCommandEngine();

    $is_http = $command_engine->isAnyHTTPProtocol();
    // For SVN, we use `--username` and `--password` flags separately in the
    // CommandEngine, so we don't need to add any credentials here.
    $is_svn = $this->getRepository()->isSVN();
    $credential_phid = $this->getCredentialPHID();

    if ($is_http && !$is_svn && $credential_phid) {
      $key = PassphrasePasswordKey::loadFromPHID(
        $credential_phid,
        PhabricatorUser::getOmnipotentUser());

      $uri->setUser($key->getUsernameEnvelope()->openEnvelope());
      $uri->setPass($key->getPasswordEnvelope()->openEnvelope());
    }

    return new PhutilOpaqueEnvelope((string)$uri);
  }

  private function getURIObject() {
    // Users can provide Git/SCP-style URIs in the form "user@host:path".
    // In the general case, these are not equivalent to any "ssh://..." form
    // because the path is relative.

    if ($this->isBuiltin()) {
      $builtin_protocol = $this->getForcedProtocol();
      $builtin_domain = $this->getForcedHost();
      $raw_uri = "{$builtin_protocol}://{$builtin_domain}";
    } else {
      $raw_uri = $this->getURI();
    }

    $port = $this->getForcedPort();

    $default_ports = array(
      'ssh' => 22,
      'http' => 80,
      'https' => 443,
    );

    $uri = new PhutilURI($raw_uri);

    // Make sure to remove any password from the URI before we do anything
    // with it; this should always be provided by the associated credential.
    $uri->setPass(null);

    $protocol = $this->getForcedProtocol();
    if ($protocol) {
      $uri->setProtocol($protocol);
    }

    if ($port) {
      $uri->setPort($port);
    }

    // Remove any explicitly set default ports.
    $uri_port = $uri->getPort();
    $uri_protocol = $uri->getProtocol();

    $uri_default = idx($default_ports, $uri_protocol);
    if ($uri_default && ($uri_default == $uri_port)) {
      $uri->setPort(null);
    }

    $user = $this->getForcedUser();
    if ($user) {
      $uri->setUser($user);
    }

    $host = $this->getForcedHost();
    if ($host) {
      $uri->setDomain($host);
    }

    $path = $this->getForcedPath();
    if ($path) {
      $uri->setPath($path);
    }

    return $uri;
  }


  private function getForcedProtocol() {
    $repository = $this->getRepository();

    switch ($this->getBuiltinProtocol()) {
      case self::BUILTIN_PROTOCOL_SSH:
        if ($repository->isSVN()) {
          return 'svn+ssh';
        } else {
          return 'ssh';
        }
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
    $protocol = $this->getBuiltinProtocol();

    if ($protocol == self::BUILTIN_PROTOCOL_SSH) {
      return PhabricatorEnv::getEnvConfig('diffusion.ssh-port');
    }

    // If Phabricator is running on a nonstandard port, use that as the defualt
    // port for URIs with the same protocol.

    $is_http = ($protocol == self::BUILTIN_PROTOCOL_HTTP);
    $is_https = ($protocol == self::BUILTIN_PROTOCOL_HTTPS);

    if ($is_http || $is_https) {
      $uri = PhabricatorEnv::getURI('/');
      $uri = new PhutilURI($uri);

      $port = $uri->getPort();
      if (!$port) {
        return null;
      }

      $uri_protocol = $uri->getProtocol();
      $use_port =
        ($is_http && ($uri_protocol == 'http')) ||
        ($is_https && ($uri_protocol == 'https'));

      if (!$use_port) {
        return null;
      }

      return $port;
    }

    return null;
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
      $clone_name = '';
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

  public function getViewURI() {
    $id = $this->getID();
    return $this->getRepository()->getPathURI("uri/view/{$id}/");
  }

  public function getEditURI() {
    $id = $this->getID();
    return $this->getRepository()->getPathURI("uri/edit/{$id}/");
  }

  public function getAvailableIOTypeOptions() {
    $options = array(
      self::IO_DEFAULT,
      self::IO_NONE,
    );

    if ($this->isBuiltin()) {
      $options[] = self::IO_READ;
      $options[] = self::IO_READWRITE;
    } else {
      $options[] = self::IO_OBSERVE;
      $options[] = self::IO_MIRROR;
    }

    $map = array();
    $io_map = self::getIOTypeMap();
    foreach ($options as $option) {
      $spec = idx($io_map, $option, array());

      $label = idx($spec, 'label', $option);
      $short = idx($spec, 'short');

      $name = pht('%s: %s', $label, $short);
      $map[$option] = $name;
    }

    return $map;
  }

  public function getAvailableDisplayTypeOptions() {
    $options = array(
      self::DISPLAY_DEFAULT,
      self::DISPLAY_ALWAYS,
      self::DISPLAY_NEVER,
    );

    $map = array();
    $display_map = self::getDisplayTypeMap();
    foreach ($options as $option) {
      $spec = idx($display_map, $option, array());

      $label = idx($spec, 'label', $option);
      $short = idx($spec, 'short');

      $name = pht('%s: %s', $label, $short);
      $map[$option] = $name;
    }

    return $map;
  }

  public static function getIOTypeMap() {
    return array(
      self::IO_DEFAULT => array(
        'label' => pht('Default'),
        'short' => pht('Use default behavior.'),
      ),
      self::IO_OBSERVE => array(
        'icon' => 'fa-download',
        'color' => 'green',
        'label' => pht('Observe'),
        'note' => pht(
          'Phabricator will observe changes to this URI and copy them.'),
        'short' => pht('Copy from a remote.'),
      ),
      self::IO_MIRROR => array(
        'icon' => 'fa-upload',
        'color' => 'green',
        'label' => pht('Mirror'),
        'note' => pht(
          'Phabricator will push a copy of any changes to this URI.'),
        'short' => pht('Push a copy to a remote.'),
      ),
      self::IO_NONE => array(
        'icon' => 'fa-times',
        'color' => 'grey',
        'label' => pht('No I/O'),
        'note' => pht(
          'Phabricator will not push or pull any changes to this URI.'),
        'short' => pht('Do not perform any I/O.'),
      ),
      self::IO_READ => array(
        'icon' => 'fa-folder',
        'color' => 'blue',
        'label' => pht('Read Only'),
        'note' => pht(
          'Phabricator will serve a read-only copy of the repository from '.
          'this URI.'),
        'short' => pht('Serve repository in read-only mode.'),
      ),
      self::IO_READWRITE => array(
        'icon' => 'fa-folder-open',
        'color' => 'blue',
        'label' => pht('Read/Write'),
        'note' => pht(
          'Phabricator will serve a read/write copy of the repository from '.
          'this URI.'),
        'short' => pht('Serve repository in read/write mode.'),
      ),
    );
  }

  public static function getDisplayTypeMap() {
    return array(
      self::DISPLAY_DEFAULT => array(
        'label' => pht('Default'),
        'short' => pht('Use default behavior.'),
      ),
      self::DISPLAY_ALWAYS => array(
        'icon' => 'fa-eye',
        'color' => 'green',
        'label' => pht('Visible'),
        'note' => pht('This URI will be shown to users as a clone URI.'),
        'short' => pht('Show as a clone URI.'),
      ),
      self::DISPLAY_NEVER => array(
        'icon' => 'fa-eye-slash',
        'color' => 'grey',
        'label' => pht('Hidden'),
        'note' => pht(
          'This URI will be hidden from users.'),
        'short' => pht('Do not show as a clone URI.'),
      ),
    );
  }

  public function newCommandEngine() {
    $repository = $this->getRepository();

    return DiffusionCommandEngine::newCommandEngine($repository)
      ->setCredentialPHID($this->getCredentialPHID())
      ->setURI($this->getEffectiveURI());
  }

  public function getURIScore() {
    $score = 0;

    $io_points = array(
      self::IO_READWRITE => 200,
      self::IO_READ => 100,
    );
    $score += idx($io_points, $this->getEffectiveIoType(), 0);

    $protocol_points = array(
      self::BUILTIN_PROTOCOL_SSH => 30,
      self::BUILTIN_PROTOCOL_HTTPS => 20,
      self::BUILTIN_PROTOCOL_HTTP => 10,
    );
    $score += idx($protocol_points, $this->getBuiltinProtocol(), 0);

    $identifier_points = array(
      self::BUILTIN_IDENTIFIER_SHORTNAME => 3,
      self::BUILTIN_IDENTIFIER_CALLSIGN => 2,
      self::BUILTIN_IDENTIFIER_ID => 1,
    );
    $score += idx($identifier_points, $this->getBuiltinIdentifier(), 0);

    return $score;
  }



/* -(  PhabricatorApplicationTransactionInterface  )------------------------- */


  public function getApplicationTransactionEditor() {
    return new DiffusionURIEditor();
  }

  public function getApplicationTransactionObject() {
    return $this;
  }

  public function getApplicationTransactionTemplate() {
    return new PhabricatorRepositoryURITransaction();
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
    );
  }

  public function getPolicy($capability) {
    switch ($capability) {
      case PhabricatorPolicyCapability::CAN_VIEW:
      case PhabricatorPolicyCapability::CAN_EDIT:
        return PhabricatorPolicies::getMostOpenPolicy();
    }
  }

  public function hasAutomaticCapability($capability, PhabricatorUser $viewer) {
    return false;
  }


/* -(  PhabricatorExtendedPolicyInterface  )--------------------------------- */


  public function getExtendedPolicy($capability, PhabricatorUser $viewer) {
    $extended = array();

    switch ($capability) {
      case PhabricatorPolicyCapability::CAN_EDIT:
        // To edit a repository URI, you must be able to edit the
        // corresponding repository.
        $extended[] = array($this->getRepository(), $capability);
        break;
    }

    return $extended;
  }


/* -(  PhabricatorConduitResultInterface  )---------------------------------- */


  public function getFieldSpecificationsForConduit() {
    return array(
      id(new PhabricatorConduitSearchFieldSpecification())
        ->setKey('repositoryPHID')
        ->setType('phid')
        ->setDescription(pht('The associated repository PHID.')),
      id(new PhabricatorConduitSearchFieldSpecification())
        ->setKey('uri')
        ->setType('map<string, string>')
        ->setDescription(pht('The raw and effective URI.')),
      id(new PhabricatorConduitSearchFieldSpecification())
        ->setKey('io')
        ->setType('map<string, const>')
        ->setDescription(
          pht('The raw, default, and effective I/O Type settings.')),
      id(new PhabricatorConduitSearchFieldSpecification())
        ->setKey('display')
        ->setType('map<string, const>')
        ->setDescription(
          pht('The raw, default, and effective Display Type settings.')),
      id(new PhabricatorConduitSearchFieldSpecification())
        ->setKey('credentialPHID')
        ->setType('phid?')
        ->setDescription(
          pht('The associated credential PHID, if one exists.')),
      id(new PhabricatorConduitSearchFieldSpecification())
        ->setKey('disabled')
        ->setType('bool')
        ->setDescription(pht('True if the URI is disabled.')),
      id(new PhabricatorConduitSearchFieldSpecification())
        ->setKey('builtin')
        ->setType('map<string, string>')
        ->setDescription(
          pht('Information about builtin URIs.')),
      id(new PhabricatorConduitSearchFieldSpecification())
        ->setKey('dateCreated')
        ->setType('int')
        ->setDescription(
          pht('Epoch timestamp when the object was created.')),
      id(new PhabricatorConduitSearchFieldSpecification())
        ->setKey('dateModified')
        ->setType('int')
        ->setDescription(
          pht('Epoch timestamp when the object was last updated.')),
    );
  }

  public function getFieldValuesForConduit() {
    return array(
      'repositoryPHID' => $this->getRepositoryPHID(),
      'uri' => array(
        'raw' => $this->getURI(),
        'display' => (string)$this->getDisplayURI(),
        'effective' => (string)$this->getEffectiveURI(),
        'normalized' => (string)$this->getNormalizedURI(),
      ),
      'io' => array(
        'raw' => $this->getIOType(),
        'default' => $this->getDefaultIOType(),
        'effective' => $this->getEffectiveIOType(),
      ),
      'display' => array(
        'raw' => $this->getDisplayType(),
        'default' => $this->getDefaultDisplayType(),
        'effective' => $this->getEffectiveDisplayType(),
      ),
      'credentialPHID' => $this->getCredentialPHID(),
      'disabled' => (bool)$this->getIsDisabled(),
      'builtin' => array(
        'protocol' => $this->getBuiltinProtocol(),
        'identifier' => $this->getBuiltinIdentifier(),
      ),
      'dateCreated' => $this->getDateCreated(),
      'dateModified' => $this->getDateModified(),
    );
  }

  public function getConduitSearchAttachments() {
    return array();
  }

}
