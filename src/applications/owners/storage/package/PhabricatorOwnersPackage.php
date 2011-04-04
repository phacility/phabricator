<?php

/*
 * Copyright 2011 Facebook, Inc.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

class PhabricatorOwnersPackage extends PhabricatorOwnersDAO {

  protected $phid;
  protected $name;
  protected $description;
  protected $primaryOwnerPHID;

  private $unsavedOwners;
  private $unsavedPaths;

  public function getConfiguration() {
    return array(
      // This information is better available from the history table.
      self::CONFIG_TIMESTAMPS => false,
      self::CONFIG_AUX_PHID   => true,
    ) + parent::getConfiguration();
  }

  public function generatePHID() {
    return PhabricatorPHID::generateNewPHID('OPKG');
  }

  public function attachUnsavedOwners(array $owners) {
    $this->unsavedOwners = $owners;
    return $this;
  }

  public function attachUnsavedPaths(array $paths) {
    $this->unsavedPaths = $paths;
    return $this;
  }

  public function loadOwners() {
    if (!$this->getID()) {
      return array();
    }
    return id(new PhabricatorOwnersOwner())->loadAllWhere(
      'packageID = %d',
      $this->getID());
  }

  public function loadPaths() {
    if (!$this->getID()) {
      return array();
    }
    return id(new PhabricatorOwnersPath())->loadAllWhere(
      'packageID = %d',
      $this->getID());
  }

  public function save() {

    // TODO: Transactions!

    $ret = parent::save();

    if ($this->unsavedOwners) {
      $new_owners = array_fill_keys($this->unsavedOwners, true);
      $cur_owners = array();
      foreach ($this->loadOwners() as $owner) {
        if (empty($new_owners[$owner->getUserPHID()])) {
          $owner->delete();
          continue;
        }
        $cur_owners[$owner->getUserPHID()] = true;
      }
      $add_owners = array_diff_key($new_owners, $cur_owners);
      foreach ($add_owners as $phid => $ignored) {
        $owner = new PhabricatorOwnersOwner();
        $owner->setPackageID($this->getID());
        $owner->setUserPHID($phid);
        $owner->save();
      }
      unset($this->unsavedOwners);
    }

    if ($this->unsavedPaths) {
      $new_paths = igroup($this->unsavedPaths, 'repositoryPHID', 'path');
      $cur_paths = $this->loadPaths();
      foreach ($cur_paths as $key => $path) {
        if (empty($new_paths[$path->getRepositoryPHID()][$path->getPath()])) {
          $path->delete();
          unset($cur_paths[$key]);
        }
      }
      $cur_paths = mgroup($cur_paths, 'getRepositoryPHID', 'getPath');
      foreach ($new_paths as $repository_phid => $paths) {
        foreach ($paths as $path => $ignored) {
          if (empty($cur_paths[$repository_phid][$path])) {
            $obj = new PhabricatorOwnersPath();
            $obj->setPackageID($this->getID());
            $obj->setRepositoryPHID($repository_phid);
            $obj->setPath($path);
            $obj->save();
          }
        }
      }
      unset($this->unsavedPaths);
    }

    return $ret;
  }

  public function delete() {
    foreach ($this->loadOwners() as $owner) {
      $owner->delete();
    }
    foreach ($this->loadPaths() as $path) {
      $path->delete();
    }
    return parent::delete();
  }

}
