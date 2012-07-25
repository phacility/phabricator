<?php

/*
 * Copyright 2012 Facebook, Inc.
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

final class PhabricatorOwnersPackage extends PhabricatorOwnersDAO {

  protected $phid;
  protected $name;
  protected $originalName;
  protected $auditingEnabled;
  protected $description;
  protected $primaryOwnerPHID;

  private $unsavedOwners;
  private $unsavedPaths;
  private $actorPHID;
  private $oldPrimaryOwnerPHID;
  private $oldAuditingEnabled;

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

  public function attachActorPHID($actor_phid) {
    $this->actorPHID = $actor_phid;
    return $this;
  }

  public function getActorPHID() {
    return $this->actorPHID;
  }

  public function attachOldPrimaryOwnerPHID($old_primary) {
    $this->oldPrimaryOwnerPHID = $old_primary;
    return $this;
  }

  public function getOldPrimaryOwnerPHID() {
    return $this->oldPrimaryOwnerPHID;
  }

  public function attachOldAuditingEnabled($auditing_enabled) {
    $this->oldAuditingEnabled = $auditing_enabled;
    return $this;
  }

  public function getOldAuditingEnabled() {
    return $this->oldAuditingEnabled;
  }

  public function setName($name) {
    $this->name = $name;
    if (!$this->getID()) {
      $this->originalName = $name;
    }
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

  public static function loadAffectedPackages(
    PhabricatorRepository $repository,
    array $paths) {

    if (!$paths) {
      return array();
    }

    $fragments = array(
      '/' => true,
    );

    foreach ($paths as $path) {
      $fragments += self::splitPath($path);
    }

    return self::loadPackagesForPaths($repository, array_keys($fragments));
 }

 public static function loadOwningPackages($repository, $path) {
    if (empty($path)) {
      return array();
    }

    $fragments = self::splitPath($path);
    return self::loadPackagesForPaths($repository, array_keys($fragments), 1);
 }

  private static function loadPackagesForPaths(
    PhabricatorRepository $repository,
    array $paths,
    $limit = 0) {
    $package = new PhabricatorOwnersPackage();
    $path = new PhabricatorOwnersPath();
    $conn = $package->establishConnection('r');

    $repository_clause = qsprintf(
      $conn,
      'AND p.repositoryPHID = %s',
      $repository->getPHID());

    // NOTE: The list of $paths may be very large if we're coming from
    // the OwnersWorker and processing, e.g., an SVN commit which created a new
    // branch. Break it apart so that it will fit within 'max_allowed_packet',
    // and then merge results in PHP.

    $ids = array();
    foreach (array_chunk($paths, 128) as $chunk) {
      $rows = queryfx_all(
        $conn,
        'SELECT pkg.id id, LENGTH(p.path) len
          FROM %T pkg JOIN %T p ON p.packageID = pkg.id
          WHERE p.path IN (%Ls) %Q',
        $package->getTableName(),
        $path->getTableName(),
        $chunk,
        $repository_clause);

      foreach ($rows as $row) {
        $id = (int)$row['id'];
        $len = (int)$row['len'];
        if (isset($ids[$id])) {
          $ids[$id] = max($len, $ids[$id]);
        } else {
          $ids[$id] = $len;
        }
      }
    }

    if (!$ids) {
      return array();
    }

    arsort($ids);
    if ($limit) {
      $ids = array_slice($ids, 0, $limit, $preserve_keys = true);
    }
    $ids = array_keys($ids);

    $packages = $package->loadAllWhere('id in (%Ld)', $ids);
    $packages = array_select_keys($packages, $ids);

    return $packages;
  }

  public function save() {

    if ($this->getID()) {
      $is_new = false;
    } else {
      $is_new = true;
    }

    $this->openTransaction();

    $ret = parent::save();

    $add_owners = array();
    $remove_owners = array();
    $all_owners = array();
    if ($this->unsavedOwners) {
      $new_owners = array_fill_keys($this->unsavedOwners, true);
      $cur_owners = array();
      foreach ($this->loadOwners() as $owner) {
        if (empty($new_owners[$owner->getUserPHID()])) {
          $remove_owners[$owner->getUserPHID()] = true;
          $owner->delete();
          continue;
        }
        $cur_owners[$owner->getUserPHID()] = true;
      }

      $add_owners = array_diff_key($new_owners, $cur_owners);
      $all_owners = array_merge(
        array($this->getPrimaryOwnerPHID() => true),
        $new_owners,
        $remove_owners);
      foreach ($add_owners as $phid => $ignored) {
        $owner = new PhabricatorOwnersOwner();
        $owner->setPackageID($this->getID());
        $owner->setUserPHID($phid);
        $owner->save();
      }
      unset($this->unsavedOwners);
    }

    $add_paths = array();
    $remove_paths = array();
    $touched_repos = array();
    if ($this->unsavedPaths) {
      $new_paths = igroup($this->unsavedPaths, 'repositoryPHID', 'path');
      $cur_paths = $this->loadPaths();
      foreach ($cur_paths as $key => $path) {
        if (empty($new_paths[$path->getRepositoryPHID()][$path->getPath()])) {
          $touched_repos[$path->getRepositoryPHID()] = true;
          $remove_paths[$path->getRepositoryPHID()][$path->getPath()] = true;
          $path->delete();
          unset($cur_paths[$key]);
        }
      }

      $cur_paths = mgroup($cur_paths, 'getRepositoryPHID', 'getPath');
      foreach ($new_paths as $repository_phid => $paths) {
        // get repository object for path validation
        $repository = id(new PhabricatorRepository())->loadOneWhere(
          'phid = %s',
          $repository_phid);
        if (!$repository) {
          continue;
        }
        foreach ($paths as $path => $ignored) {
          $path = ltrim($path, '/');
          // build query to validate path
          $drequest = DiffusionRequest::newFromDictionary(
            array(
              'repository'  => $repository,
              'path'        => $path,
            ));
          $query = DiffusionBrowseQuery::newFromDiffusionRequest($drequest);
          $query->needValidityOnly(true);
          $valid = $query->loadPaths();
          $is_directory = true;
          if (!$valid) {
            switch ($query->getReasonForEmptyResultSet()) {
              case DiffusionBrowseQuery::REASON_IS_FILE:
                $valid = true;
                $is_directory = false;
                break;
              case DiffusionBrowseQuery::REASON_IS_EMPTY:
                $valid = true;
                break;
            }
          }
          if ($is_directory && substr($path, -1) != '/') {
            $path .= '/';
          }
          if (substr($path, 0, 1) != '/') {
            $path = '/'.$path;
          }
          if (empty($cur_paths[$repository_phid][$path]) && $valid) {
            $touched_repos[$repository_phid] = true;
            $add_paths[$repository_phid][$path] = true;
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

    $this->saveTransaction();

    if ($is_new) {
      $mail = new PackageCreateMail($this);
    } else {
      $mail = new PackageModifyMail(
        $this,
        array_keys($add_owners),
        array_keys($remove_owners),
        array_keys($all_owners),
        array_keys($touched_repos),
        $add_paths,
        $remove_paths);
    }
    $mail->send();

    return $ret;
  }

  public function delete() {
    $mails = id(new PackageDeleteMail($this))->prepareMails();

    $this->openTransaction();
    foreach ($this->loadOwners() as $owner) {
      $owner->delete();
    }
    foreach ($this->loadPaths() as $path) {
      $path->delete();
    }

    $ret = parent::delete();

    $this->saveTransaction();

    foreach ($mails as $mail) {
      $mail->saveAndSend();
    }

    return $ret;
  }

  private static function splitPath($path) {
    $result = array();
    $trailing_slash = preg_match('@/$@', $path) ? '/' : '';
    $path = trim($path, '/');
    $parts = explode('/', $path);
    while (count($parts)) {
      $result['/'.implode('/', $parts).$trailing_slash] = true;
      $trailing_slash = '/';
      array_pop($parts);
    }
    return $result;
  }
}
