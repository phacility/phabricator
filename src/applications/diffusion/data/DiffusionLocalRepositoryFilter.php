<?php

/**
 * Filter a list of repositories, removing repositories not local to the
 * current device.
 */
final class DiffusionLocalRepositoryFilter extends Phobject {

  private $viewer;
  private $device;
  private $repositories;
  private $rejectionReasons;

  public function setViewer(PhabricatorUser $viewer) {
    $this->viewer = $viewer;
    return $this;
  }

  public function getViewer() {
    return $this->viewer;
  }

  public function setDevice(AlmanacDevice $device = null) {
    $this->device = $device;
    return $this;
  }

  public function getDevice() {
    return $this->device;
  }

  public function setRepositories(array $repositories) {
    $this->repositories = $repositories;
    return $this;
  }

  public function getRepositories() {
    return $this->repositories;
  }

  public function setRejectionReasons($rejection_reasons) {
    $this->rejectionReasons = $rejection_reasons;
    return $this;
  }

  public function getRejectionReasons() {
    return $this->rejectionReasons;
  }

  public function execute() {
    $repositories = $this->getRepositories();
    $device = $this->getDevice();
    $viewer = $this->getViewer();

    $reasons = array();

    $service_phids = array();
    foreach ($repositories as $key => $repository) {
      $service_phid = $repository->getAlmanacServicePHID();

      // If the repository is bound to a service but this host is not a
      // recognized device, or vice versa, don't pull the repository unless
      // we're sure it's safe because the repository has no local working copy
      // or the working copy already exists on disk.
      $is_cluster_repo = (bool)$service_phid;
      $is_cluster_device = (bool)$device;
      if ($is_cluster_repo != $is_cluster_device) {
        $has_working_copy = $repository->hasLocalWorkingCopy();
        if ($is_cluster_device) {
          if (!$has_working_copy) {
            $reasons[$key] = pht(
              'Repository "%s" is not a cluster repository, but the current '.
              'host is a cluster device ("%s") and updating this repository '.
              'would create a new local working copy. This is dangerous, so '.
              'the repository will not be updated on this host.',
              $repository->getDisplayName(),
              $device->getName());
            unset($repositories[$key]);
            continue;
          }
        } else {
          $reasons[$key] = pht(
            'Repository "%s" is a cluster repository, but the current host '.
            'is not a cluster device (it has no device ID), so the '.
            'repository will not be updated on this host.',
            $repository->getDisplayName());
          unset($repositories[$key]);
          continue;
        }
      }

      if ($service_phid) {
        $service_phids[] = $service_phid;
      }
    }

    if (!$device) {
      $this->rejectionReasons = $reasons;
      return $repositories;
    }

    $device_phid = $device->getPHID();

    if ($service_phids) {
      // We could include `withDevicePHIDs()` here to pull a smaller result
      // set, but we can provide more helpful diagnostic messages below if
      // we fetch a little more data.
      $services = id(new AlmanacServiceQuery())
        ->setViewer($viewer)
        ->withPHIDs($service_phids)
        ->withServiceTypes(
          array(
            AlmanacClusterRepositoryServiceType::SERVICETYPE,
          ))
        ->needBindings(true)
        ->execute();
      $services = mpull($services, null, 'getPHID');
    } else {
      $services = array();
    }

    foreach ($repositories as $key => $repository) {
      $service_phid = $repository->getAlmanacServicePHID();

      if (!$service_phid) {
        continue;
      }

      $service = idx($services, $service_phid);
      if (!$service) {
        $reasons[$key] = pht(
          'Repository "%s" is on cluster service "%s", but that service '.
          'could not be loaded, so the repository will not be updated on '.
          'this host.',
          $repository->getDisplayName(),
          $service_phid);
        unset($repositories[$key]);
        continue;
      }

      $bindings = $service->getBindings();
      $bindings = mgroup($bindings, 'getDevicePHID');
      $bindings = idx($bindings, $device_phid);
      if (!$bindings) {
        $reasons[$key] = pht(
          'Repository "%s" is on cluster service "%s", but that service is '.
          'not bound to this device ("%s"), so the repository will not be '.
          'updated on this host.',
          $repository->getDisplayName(),
          $service->getName(),
          $device->getName());
        unset($repositories[$key]);
        continue;
      }

      $all_disabled = true;
      foreach ($bindings as $binding) {
        if (!$binding->getIsDisabled()) {
          $all_disabled = false;
          break;
        }
      }

      if ($all_disabled) {
        $reasons[$key] = pht(
          'Repository "%s" is on cluster service "%s", but the binding '.
          'between that service and this device ("%s") is disabled, so it '.
          'can not be updated on this host.',
          $repository->getDisplayName(),
          $service->getName(),
          $device->getName());
        unset($repositories[$key]);
        continue;
      }

      // We have a valid service that is actively bound to the current host
      // device, so we're good to go.
    }

    $this->rejectionReasons = $reasons;
    return $repositories;
  }

}
