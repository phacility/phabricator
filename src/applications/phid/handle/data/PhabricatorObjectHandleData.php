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

class PhabricatorObjectHandleData {

  private $phids;

  public function __construct(array $phids) {
    $this->phids = $phids;
  }

  public function loadHandles() {

    $types = array();
    foreach ($this->phids as $phid) {
      $type = $this->lookupType($phid);
      $types[$type][] = $phid;
    }

    $handles = array();

    foreach ($types as $type => $phids) {
      switch ($type) {
        case PhabricatorPHIDConstants::PHID_TYPE_MAGIC:
          // Black magic!
          foreach ($phids as $phid) {
            $handle = new PhabricatorObjectHandle();
            $handle->setPHID($phid);
            $handle->setType($type);
            switch ($phid) {
              case 'PHID-!!!!-UP-FOR-GRABS':
                $handle->setName('Up For Grabs');
                break;
              default:
                $handle->setName('Foul Magicks');
                break;
            }
            $handles[$phid] = $handle;
          }
          break;
        case PhabricatorPHIDConstants::PHID_TYPE_USER:
          $class = 'PhabricatorUser';
          PhutilSymbolLoader::loadClass($class);
          $object = newv($class, array());

          $users = $object->loadAllWhere('phid IN (%Ls)', $phids);
          $users = mpull($users, null, 'getPHID');

          foreach ($phids as $phid) {
            $handle = new PhabricatorObjectHandle();
            $handle->setPHID($phid);
            $handle->setType($type);
            if (empty($users[$phid])) {
              $handle->setName('Unknown User');
            } else {
              $user = $users[$phid];
              $handle->setName($user->getUsername());
              $handle->setURI('/p/'.$user->getUsername().'/');
              $handle->setEmail($user->getEmail());
              $handle->setFullName(
                $user->getUsername().' ('.$user->getRealName().')');

              $img_phid = $user->getProfileImagePHID();
              if ($img_phid) {
                $handle->setImageURI(
                  PhabricatorFileURI::getViewURIForPHID($img_phid));
              }
            }
            $handles[$phid] = $handle;
          }
          break;
        case PhabricatorPHIDConstants::PHID_TYPE_MLST:
          $class = 'PhabricatorMetaMTAMailingList';

          PhutilSymbolLoader::loadClass($class);
          $object = newv($class, array());

          $lists = $object->loadAllWhere('phid IN (%Ls)', $phids);
          $lists = mpull($lists, null, 'getPHID');

          foreach ($phids as $phid) {
            $handle = new PhabricatorObjectHandle();
            $handle->setPHID($phid);
            $handle->setType($type);
            if (empty($lists[$phid])) {
              $handle->setName('Unknown Mailing List');
            } else {
              $list = $lists[$phid];
              $handle->setEmail($list->getEmail());
              $handle->setName($list->getName());
              $handle->setURI($list->getURI());
              $handle->setFullName($list->getName());
            }
            $handles[$phid] = $handle;
          }
          break;
        case PhabricatorPHIDConstants::PHID_TYPE_DREV:
          $class = 'DifferentialRevision';
          PhutilSymbolLoader::loadClass($class);
          $object = newv($class, array());

          $revs = $object->loadAllWhere('phid in (%Ls)', $phids);
          $revs = mpull($revs, null, 'getPHID');

          foreach ($phids as $phid) {
            $handle = new PhabricatorObjectHandle();
            $handle->setPHID($phid);
            $handle->setType($type);
            if (empty($revs[$phid])) {
              $handle->setName('Unknown Revision');
            } else {
              $rev = $revs[$phid];
              $handle->setName($rev->getTitle());
              $handle->setURI('/D'.$rev->getID());
              $handle->setFullName('D'.$rev->getID().': '.$rev->getTitle());
            }
            $handles[$phid] = $handle;
          }
          break;
        case PhabricatorPHIDConstants::PHID_TYPE_TASK:
          $class = 'ManiphestTask';
          PhutilSymbolLoader::loadClass($class);
          $object = newv($class, array());

          $tasks = $object->loadAllWhere('phid in (%Ls)', $phids);
          $tasks = mpull($tasks, null, 'getPHID');

          foreach ($phids as $phid) {
            $handle = new PhabricatorObjectHandle();
            $handle->setPHID($phid);
            $handle->setType($type);
            if (empty($tasks[$phid])) {
              $handle->setName('Unknown Revision');
            } else {
              $task = $tasks[$phid];
              $handle->setName($task->getTitle());
              $handle->setURI('/T'.$task->getID());
              $handle->setFullName('T'.$task->getID().': '.$task->getTitle());
            }
            $handles[$phid] = $handle;
          }
          break;
        case PhabricatorPHIDConstants::PHID_TYPE_FILE:
          $class = 'PhabricatorFile';
          PhutilSymbolLoader::loadClass($class);
          $object = newv($class, array());

          $files = $object->loadAllWhere('phid IN (%Ls)', $phids);
          $files = mpull($files, null, 'getPHID');

          foreach ($phids as $phid) {
            $handle = new PhabricatorObjectHandle();
            $handle->setPHID($phid);
            $handle->setType($type);
            if (empty($files[$phid])) {
              $handle->setName('Unknown File');
            } else {
              $file = $files[$phid];
              $handle->setName($file->getName());
              $handle->setURI($file->getViewURI());
            }
            $handles[$phid] = $handle;
          }
          break;
        case PhabricatorPHIDConstants::PHID_TYPE_PROJ:
          $class = 'PhabricatorProject';
          PhutilSymbolLoader::loadClass($class);
          $object = newv($class, array());

          $projects = $object->loadAllWhere('phid IN (%Ls)', $phids);
          $projects = mpull($projects, null, 'getPHID');

          foreach ($phids as $phid) {
            $handle = new PhabricatorObjectHandle();
            $handle->setPHID($phid);
            $handle->setType($type);
            if (empty($projects[$phid])) {
              $handle->setName('Unknown Project');
            } else {
              $project = $projects[$phid];
              $handle->setName($project->getName());
              $handle->setURI('/project/view/'.$project->getID().'/');
            }
            $handles[$phid] = $handle;
          }
          break;
        default:
          foreach ($phids as $phid) {
            $handle = new PhabricatorObjectHandle();
            $handle->setType($type);
            $handle->setPHID($phid);
            $handle->setName('Unknown Object');
            $handle->setFullName('An Unknown Object');
            $handles[$phid] = $handle;
          }
          break;
      }
    }

    return $handles;
  }

  private function lookupType($phid) {
    $matches = null;
    if (preg_match('/^PHID-([^-]{4})-/', $phid, $matches)) {
      return $matches[1];
    }
    return PhabricatorPHIDConstants::PHID_TYPE_UNKNOWN;
  }

}
