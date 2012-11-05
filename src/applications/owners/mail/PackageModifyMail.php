<?php

final class PackageModifyMail extends PackageMail {

  protected $addOwners;
  protected $removeOwners;
  protected $allOwners;
  protected $touchedRepos;
  protected $addPaths;
  protected $removePaths;

  public function __construct(
    PhabricatorOwnersPackage $package,
    $add_owners,
    $remove_owners,
    $all_owners,
    $touched_repos,
    $add_paths,
    $remove_paths) {

    $this->package = $package;

    $this->addOwners = $add_owners;
    $this->removeOwners = $remove_owners;
    $this->allOwners = $all_owners;
    $this->touchedRepos = $touched_repos;
    $this->addPaths = $add_paths;
    $this->removePaths = $remove_paths;
  }

  protected function getVerb() {
    return 'Modified';
  }

  protected function isNewThread() {
    return false;
  }

  protected function needSend() {
    $package = $this->getPackage();
    if ($package->getOldPrimaryOwnerPHID() !== $package->getPrimaryOwnerPHID()
        || $package->getOldAuditingEnabled() != $package->getAuditingEnabled()
        || $this->addOwners
        || $this->removeOwners
        || $this->addPaths
        || $this->removePaths) {
      return true;
    } else {
      return false;
    }
  }

  protected function loadData() {
    $this->mailTo = $this->allOwners;

    $phids = array_merge(
      $this->allOwners,
      $this->touchedRepos,
      array(
        $this->getPackage()->getActorPHID(),
      ));
    $this->handles = id(new PhabricatorObjectHandleData($phids))->loadHandles();
  }

  protected function renderDescriptionSection() {
    return null;
  }

  protected function renderPrimaryOwnerSection() {
    $package = $this->getPackage();
    $handles = $this->getHandles();

    $old_primary_owner_phid = $package->getOldPrimaryOwnerPHID();
    $primary_owner_phid = $package->getPrimaryOwnerPHID();
    if ($old_primary_owner_phid == $primary_owner_phid) {
      return null;
    }

    $section = array();
    $section[] = 'PRIMARY OWNER CHANGE';
    $section[] = '  Old owner: ' .
      $handles[$old_primary_owner_phid]->getName();
    $section[] = '  New owner: ' .
      $handles[$primary_owner_phid]->getName();

    return implode("\n", $section);
  }

  protected function renderOwnersSection() {
    $section = array();
    $add_owners = $this->addOwners;
    $remove_owners = $this->removeOwners;
    $handles = $this->getHandles();

    if ($add_owners) {
      $add_owners = array_select_keys($handles, $add_owners);
      $add_owners = mpull($add_owners, 'getName');
      $section[] = 'ADDED OWNERS';
      $section[] = '  '.implode(', ', $add_owners);
    }

    if ($remove_owners) {
      if ($add_owners) {
        $section[] = '';
      }
      $remove_owners = array_select_keys($handles, $remove_owners);
      $remove_owners = mpull($remove_owners, 'getName');
      $section[] = 'REMOVED OWNERS';
      $section[] = '  '.implode(', ', $remove_owners);
    }

    if ($section) {
      return implode("\n", $section);
    } else {
      return null;
    }
  }

  protected function renderAuditingEnabledSection() {
    $package = $this->getPackage();
    $old_auditing_enabled = $package->getOldAuditingEnabled();
    $auditing_enabled = $package->getAuditingEnabled();
    if ($old_auditing_enabled == $auditing_enabled) {
      return null;
    }

    $section = array();
    $section[] = 'AUDITING ENABLED STATUS CHANGE';
    $section[] = '  Old value: '.
      ($old_auditing_enabled ? 'Enabled' : 'Disabled');
    $section[] = '  New value: '.
      ($auditing_enabled ? 'Enabled' : 'Disabled');
    return implode("\n", $section);
  }

  protected function renderPathsSection() {
    $section = array();
    if ($this->addPaths) {
      $section[] = 'ADDED PATHS';
      foreach ($this->addPaths as $repository_phid => $paths) {
        $section[] = $this->renderRepoSubSection($repository_phid, $paths);
      }
    }

    if ($this->removePaths) {
      if ($this->addPaths) {
        $section[] = '';
      }
      $section[] = 'REMOVED PATHS';
      foreach ($this->removePaths as $repository_phid => $paths) {
        $section[] = $this->renderRepoSubSection($repository_phid, $paths);
      }
    }
    return implode("\n", $section);
  }

}
