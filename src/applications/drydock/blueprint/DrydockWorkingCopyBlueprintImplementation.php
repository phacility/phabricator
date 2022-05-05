<?php

final class DrydockWorkingCopyBlueprintImplementation
  extends DrydockBlueprintImplementation {

  const PHASE_SQUASHMERGE = 'squashmerge';
  const PHASE_REMOTEFETCH = 'blueprint.workingcopy.fetch.remote';
  const PHASE_MERGEFETCH = 'blueprint.workingcopy.fetch.staging';

  public function isEnabled() {
    return true;
  }

  public function getBlueprintName() {
    return pht('Working Copy');
  }

  public function getBlueprintIcon() {
    return 'fa-folder-open';
  }

  public function getDescription() {
    return pht('Allows Drydock to check out working copies of repositories.');
  }

  public function canAnyBlueprintEverAllocateResourceForLease(
    DrydockLease $lease) {
    return true;
  }

  public function canEverAllocateResourceForLease(
    DrydockBlueprint $blueprint,
    DrydockLease $lease) {
    return true;
  }

  public function canAllocateResourceForLease(
    DrydockBlueprint $blueprint,
    DrydockLease $lease) {
    $viewer = $this->getViewer();

    if ($this->shouldLimitAllocatingPoolSize($blueprint)) {
      return false;
    }

    return true;
  }

  public function canAcquireLeaseOnResource(
    DrydockBlueprint $blueprint,
    DrydockResource $resource,
    DrydockLease $lease) {

    // Don't hand out leases on working copies which have not activated, since
    // it may take an arbitrarily long time for them to acquire a host.
    if (!$resource->isActive()) {
      return false;
    }

    $need_map = $lease->getAttribute('repositories.map');
    if (!is_array($need_map)) {
      return false;
    }

    $have_map = $resource->getAttribute('repositories.map');
    if (!is_array($have_map)) {
      return false;
    }

    $have_as = ipull($have_map, 'phid');
    $need_as = ipull($need_map, 'phid');

    foreach ($need_as as $need_directory => $need_phid) {
      if (empty($have_as[$need_directory])) {
        // This resource is missing a required working copy.
        return false;
      }

      if ($have_as[$need_directory] != $need_phid) {
        // This resource has a required working copy, but it contains
        // the wrong repository.
        return false;
      }

      unset($have_as[$need_directory]);
    }

    if ($have_as && $lease->getAttribute('repositories.strict')) {
      // This resource has extra repositories, but the lease is strict about
      // which repositories are allowed to exist.
      return false;
    }

    if (!DrydockSlotLock::isLockFree($this->getLeaseSlotLock($resource))) {
      return false;
    }

    return true;
  }

  public function acquireLease(
    DrydockBlueprint $blueprint,
    DrydockResource $resource,
    DrydockLease $lease) {

    $lease
      ->needSlotLock($this->getLeaseSlotLock($resource))
      ->acquireOnResource($resource);
  }

  private function getLeaseSlotLock(DrydockResource $resource) {
    $resource_phid = $resource->getPHID();
    return "workingcopy.lease({$resource_phid})";
  }

  public function allocateResource(
    DrydockBlueprint $blueprint,
    DrydockLease $lease) {

    $resource = $this->newResourceTemplate($blueprint);

    $resource_phid = $resource->getPHID();

    $blueprint_phids = $blueprint->getFieldValue('blueprintPHIDs');

    $host_lease = $this->newLease($blueprint)
      ->setResourceType('host')
      ->setOwnerPHID($resource_phid)
      ->setAttribute('workingcopy.resourcePHID', $resource_phid)
      ->setAllowedBlueprintPHIDs($blueprint_phids);
    $resource->setAttribute('host.leasePHID', $host_lease->getPHID());

    $map = $this->getWorkingCopyRepositoryMap($lease);
    $resource->setAttribute('repositories.map', $map);

    $slot_lock = $this->getConcurrentResourceLimitSlotLock($blueprint);
    if ($slot_lock !== null) {
      $resource->needSlotLock($slot_lock);
    }

    $resource->allocateResource();

    $host_lease->queueForActivation();

    return $resource;
  }

  private function getWorkingCopyRepositoryMap(DrydockLease $lease) {
    $attribute = 'repositories.map';
    $map = $lease->getAttribute($attribute);

    // TODO: Leases should validate their attributes more formally.

    if (!is_array($map) || !$map) {
      $message = array();
      if ($map === null) {
        $message[] = pht(
          'Working copy lease is missing required attribute "%s".',
          $attribute);
      } else {
        $message[] = pht(
          'Working copy lease has invalid attribute "%s".',
          $attribute);
      }

      $message[] = pht(
        'Attribute "repositories.map" should be a map of repository '.
        'specifications.');

      $message = implode("\n\n", $message);

      throw new Exception($message);
    }

    foreach ($map as $key => $value) {
      $map[$key] = array_select_keys(
        $value,
        array(
          'phid',
        ));
    }

    return $map;
  }

  public function activateResource(
    DrydockBlueprint $blueprint,
    DrydockResource $resource) {

    $lease = $this->loadHostLease($resource);
    $this->requireActiveLease($lease);

    $command_type = DrydockCommandInterface::INTERFACE_TYPE;
    $interface = $lease->getInterface($command_type);

    // TODO: Make this configurable.
    $resource_id = $resource->getID();
    $root = "/var/drydock/workingcopy-{$resource_id}";

    $map = $resource->getAttribute('repositories.map');

    $futures = array();
    $repositories = $this->loadRepositories(ipull($map, 'phid'));
    foreach ($map as $directory => $spec) {
      // TODO: Validate directory isn't goofy like "/etc" or "../../lol"
      // somewhere?

      $repository = $repositories[$spec['phid']];
      $path = "{$root}/repo/{$directory}/";

      $future = $interface->getExecFuture(
        'git clone -- %s %s',
        (string)$repository->getCloneURIObject(),
        $path);

      $future->setTimeout($repository->getEffectiveCopyTimeLimit());

      $futures[$directory] = $future;
    }

    foreach (new FutureIterator($futures) as $key => $future) {
      $future->resolvex();
    }

    $resource
      ->setAttribute('workingcopy.root', $root)
      ->activateResource();
  }

  public function destroyResource(
    DrydockBlueprint $blueprint,
    DrydockResource $resource) {

    try {
      $lease = $this->loadHostLease($resource);
    } catch (Exception $ex) {
      // If we can't load the lease, assume we don't need to take any actions
      // to destroy it.
      return;
    }

    // Destroy the lease on the host.
    $lease->setReleaseOnDestruction(true);

    if ($lease->isActive()) {
      // Destroy the working copy on disk.
      $command_type = DrydockCommandInterface::INTERFACE_TYPE;
      $interface = $lease->getInterface($command_type);

      $root_key = 'workingcopy.root';
      $root = $resource->getAttribute($root_key);
      if (strlen($root)) {
        $interface->execx('rm -rf -- %s', $root);
      }
    }
  }

  public function getResourceName(
    DrydockBlueprint $blueprint,
    DrydockResource $resource) {
    return pht('Working Copy');
  }


  public function activateLease(
    DrydockBlueprint $blueprint,
    DrydockResource $resource,
    DrydockLease $lease) {

    $host_lease = $this->loadHostLease($resource);
    $command_type = DrydockCommandInterface::INTERFACE_TYPE;
    $interface = $host_lease->getInterface($command_type);

    $map = $lease->getAttribute('repositories.map');
    $root = $resource->getAttribute('workingcopy.root');

    $repositories = $this->loadRepositories(ipull($map, 'phid'));

    $default = null;
    foreach ($map as $directory => $spec) {
      $repository = $repositories[$spec['phid']];

      $interface->pushWorkingDirectory("{$root}/repo/{$directory}/");

      $cmd = array();
      $arg = array();

      $cmd[] = 'git clean -d --force';
      $cmd[] = 'git fetch';

      $commit = idx($spec, 'commit');
      $branch = idx($spec, 'branch');

      $ref = idx($spec, 'ref');

      // Reset things first, in case previous builds left anything staged or
      // dirty. Note that we don't reset to "HEAD" because that does not work
      // in empty repositories.
      $cmd[] = 'git reset --hard';

      if ($commit !== null) {
        $cmd[] = 'git checkout %s --';
        $arg[] = $commit;
      } else if ($branch !== null) {
        $cmd[] = 'git checkout %s --';
        $arg[] = $branch;

        $cmd[] = 'git reset --hard origin/%s';
        $arg[] = $branch;
      }

      $this->newExecvFuture($interface, $cmd, $arg)
        ->setTimeout($repository->getEffectiveCopyTimeLimit())
        ->resolvex();

      if (idx($spec, 'default')) {
        $default = $directory;
      }

      // If we're fetching a ref from a remote, do that separately so we can
      // raise a more tailored error.
      if ($ref) {
        $cmd = array();
        $arg = array();

        $ref_uri = $ref['uri'];
        $ref_ref = $ref['ref'];

        $cmd[] = 'git fetch --no-tags -- %s +%s:%s';
        $arg[] = $ref_uri;
        $arg[] = $ref_ref;
        $arg[] = $ref_ref;

        $cmd[] = 'git checkout %s --';
        $arg[] = $ref_ref;

        try {
          $this->newExecvFuture($interface, $cmd, $arg)
            ->setTimeout($repository->getEffectiveCopyTimeLimit())
            ->resolvex();
        } catch (CommandException $ex) {
          $display_command = csprintf(
            'git fetch %R %R',
            $ref_uri,
            $ref_ref);

          $error = DrydockCommandError::newFromCommandException($ex)
            ->setPhase(self::PHASE_REMOTEFETCH)
            ->setDisplayCommand($display_command);

          $lease->setAttribute(
            'workingcopy.vcs.error',
            $error->toDictionary());

          throw $ex;
        }
      }

      $merges = idx($spec, 'merges');
      if ($merges) {
        foreach ($merges as $merge) {
          $this->applyMerge($lease, $interface, $merge);
        }
      }

      $interface->popWorkingDirectory();
    }

    if ($default === null) {
      $default = head_key($map);
    }

    // TODO: Use working storage?
    $lease->setAttribute('workingcopy.default', "{$root}/repo/{$default}/");

    $lease->activateOnResource($resource);
  }

  public function didReleaseLease(
    DrydockBlueprint $blueprint,
    DrydockResource $resource,
    DrydockLease $lease) {
    // We leave working copies around even if there are no leases on them,
    // since the cost to maintain them is nearly zero but rebuilding them is
    // moderately expensive and it's likely that they'll be reused.
    return;
  }

  public function destroyLease(
    DrydockBlueprint $blueprint,
    DrydockResource $resource,
    DrydockLease $lease) {
    // When we activate a lease we just reset the working copy state and do
    // not create any new state, so we don't need to do anything special when
    // destroying a lease.
    return;
  }

  public function getType() {
    return 'working-copy';
  }

  public function getInterface(
    DrydockBlueprint $blueprint,
    DrydockResource $resource,
    DrydockLease $lease,
    $type) {

    switch ($type) {
      case DrydockCommandInterface::INTERFACE_TYPE:
        $host_lease = $this->loadHostLease($resource);
        $command_interface = $host_lease->getInterface($type);

        $path = $lease->getAttribute('workingcopy.default');
        $command_interface->pushWorkingDirectory($path);

        return $command_interface;
    }
  }

  private function loadRepositories(array $phids) {
    $viewer = $this->getViewer();

    $repositories = id(new PhabricatorRepositoryQuery())
      ->setViewer($viewer)
      ->withPHIDs($phids)
      ->execute();
    $repositories = mpull($repositories, null, 'getPHID');

    foreach ($phids as $phid) {
      if (empty($repositories[$phid])) {
        throw new Exception(
          pht(
            'Repository PHID "%s" does not exist.',
            $phid));
      }
    }

    foreach ($repositories as $repository) {
      $repository_vcs = $repository->getVersionControlSystem();
      switch ($repository_vcs) {
        case PhabricatorRepositoryType::REPOSITORY_TYPE_GIT:
          break;
        default:
          throw new Exception(
            pht(
              'Repository ("%s") has unsupported VCS ("%s").',
              $repository->getPHID(),
              $repository_vcs));
      }
    }

    return $repositories;
  }

  private function loadHostLease(DrydockResource $resource) {
    $viewer = $this->getViewer();

    $lease_phid = $resource->getAttribute('host.leasePHID');

    $lease = id(new DrydockLeaseQuery())
      ->setViewer($viewer)
      ->withPHIDs(array($lease_phid))
      ->executeOne();
    if (!$lease) {
      throw new Exception(
        pht(
          'Unable to load lease ("%s").',
          $lease_phid));
    }

    return $lease;
  }

  protected function getCustomFieldSpecifications() {
    return array(
      'blueprintPHIDs' => array(
        'name' => pht('Use Blueprints'),
        'type' => 'blueprints',
        'required' => true,
      ),
    );
  }

  protected function shouldUseConcurrentResourceLimit() {
    return true;
  }

  private function applyMerge(
    DrydockLease $lease,
    DrydockCommandInterface $interface,
    array $merge) {

    $src_uri = $merge['src.uri'];
    $src_ref = $merge['src.ref'];


    try {
      $interface->execx(
        'git fetch --no-tags -- %s +%s:%s',
        $src_uri,
        $src_ref,
        $src_ref);
    } catch (CommandException $ex) {
      $display_command = csprintf(
        'git fetch %R +%R:%R',
        $src_uri,
        $src_ref,
        $src_ref);

      $error = DrydockCommandError::newFromCommandException($ex)
        ->setPhase(self::PHASE_MERGEFETCH)
        ->setDisplayCommand($display_command);

      $lease->setAttribute('workingcopy.vcs.error', $error->toDictionary());

      throw $ex;
    }


    // NOTE: This can never actually generate a commit because we pass
    // "--squash", but git sometimes runs code to check that a username and
    // email are configured anyway.
    $real_command = csprintf(
      'git -c user.name=%s -c user.email=%s merge --no-stat --squash -- %R',
      'drydock',
      'drydock@phabricator',
      $src_ref);

    try {
      $interface->execx('%C', $real_command);
    } catch (CommandException $ex) {
      $display_command = csprintf(
        'git merge --squash %R',
        $src_ref);

      $error = DrydockCommandError::newFromCommandException($ex)
        ->setPhase(self::PHASE_SQUASHMERGE)
        ->setDisplayCommand($display_command);

      $lease->setAttribute('workingcopy.vcs.error', $error->toDictionary());
      throw $ex;
    }
  }

  public function getCommandError(DrydockLease $lease) {
    return $lease->getAttribute('workingcopy.vcs.error');
  }

  private function execxv(
    DrydockCommandInterface $interface,
    array $commands,
    array $arguments) {
    return $this->newExecvFuture($interface, $commands, $arguments)->resolvex();
  }

  private function newExecvFuture(
    DrydockCommandInterface $interface,
    array $commands,
    array $arguments) {

    $commands = implode(' && ', $commands);
    $argv = array_merge(array($commands), $arguments);

    return call_user_func_array(array($interface, 'getExecFuture'), $argv);
  }

}
