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
  protected $auditingEnabled;
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

    $repository_clause = qsprintf($conn, 'AND p.repositoryPHID = %s',
      $repository->getPHID());

    $limit_clause = '';
    if (!empty($limit)) {
      $limit_clause = qsprintf($conn, 'LIMIT %d', $limit);
    }

    $data = queryfx_all(
      $conn,
      'SELECT pkg.id FROM %T pkg JOIN %T p ON p.packageID = pkg.id
        WHERE p.path IN (%Ls) %Q ORDER BY LENGTH(p.path) DESC %Q',
      $package->getTableName(),
      $path->getTableName(),
      $paths,
      $repository_clause,
      $limit_clause);

    $ids = ipull($data, 'id');

    if (empty($ids)) {
      return array();
    }

    $order = array();
    foreach ($ids as $id) {
      if (empty($order[$id])) {
        $order[$id] = true;
      }
    }

    $packages = $package->loadAllWhere('id in (%Ld)', array_keys($order));

    $packages = array_select_keys($packages, array_keys($order));

    return $packages;
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
          $drequest = DiffusionRequest::newFromAphrontRequestDictionary(
            array(
              'callsign' => $repository->getCallsign(),
              'path'     => ':/'.$path,
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
