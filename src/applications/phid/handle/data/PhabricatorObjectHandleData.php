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
    $this->phids = array_unique($phids);
  }

  public function loadObjects() {
    $types = array();
    foreach ($this->phids as $phid) {
      $type = $this->lookupType($phid);
      $types[$type][] = $phid;
    }

    $objects = array_fill_keys($this->phids, null);
    foreach ($types as $type => $phids) {
      switch ($type) {
        case PhabricatorPHIDConstants::PHID_TYPE_USER:
          $user_dao = newv('PhabricatorUser', array());
          $users = $user_dao->loadAllWhere(
            'phid in (%Ls)',
            $phids);
          foreach ($users as $user) {
            $objects[$user->getPHID()] = $user;
          }
          break;
        case PhabricatorPHIDConstants::PHID_TYPE_CMIT:
          $commit_dao = newv('PhabricatorRepositoryCommit', array());
          $commits = $commit_dao->loadAllWhere(
            'phid IN (%Ls)',
            $phids);
          $commit_data = array();
          if ($commits) {
            $data_dao = newv('PhabricatorRepositoryCommitData', array());
            $commit_data = $data_dao->loadAllWhere(
              'commitID IN (%Ld)',
              mpull($commits, 'getID'));
            $commit_data = mpull($commit_data, null, 'getCommitID');
          }
          foreach ($commits as $commit) {
            $data = idx($commit_data, $commit->getID());
            if ($data) {
              $commit->attachCommitData($data);
              $objects[$commit->getPHID()] = $commit;
            } else {
             // If we couldn't load the commit data, just act as though we
             // couldn't load the object at all so we don't load half an object.
            }
          }
          break;
        case PhabricatorPHIDConstants::PHID_TYPE_TASK:
          $task_dao = newv('ManiphestTask', array());
          $tasks = $task_dao->loadAllWhere(
            'phid IN (%Ls)',
            $phids);
          foreach ($tasks as $task) {
            $objects[$task->getPHID()] = $task;
          }
          break;
        case PhabricatorPHIDConstants::PHID_TYPE_DREV:
          $revision_dao = newv('DifferentialRevision', array());
          $revisions = $revision_dao->loadAllWhere(
            'phid IN (%Ls)',
            $phids);
          foreach ($revisions as $revision) {
            $objects[$revision->getPHID()] = $revision;
          }
          break;
      }
    }

    return $objects;
  }

  public function loadHandles() {

    $types = array();
    foreach ($this->phids as $phid) {
      $type = $this->lookupType($phid);
      $types[$type][] = $phid;
    }

    $handles = array();

    $external_loaders = PhabricatorEnv::getEnvConfig('phid.external-loaders');

    foreach ($types as $type => $phids) {
      switch ($type) {
        case PhabricatorPHIDConstants::PHID_TYPE_MAGIC:
          // Black magic!
          foreach ($phids as $phid) {
            $handle = new PhabricatorObjectHandle();
            $handle->setPHID($phid);
            $handle->setType($type);
            switch ($phid) {
              case ManiphestTaskOwner::OWNER_UP_FOR_GRABS:
                $handle->setName('Up For Grabs');
                $handle->setFullName('upforgrabs (Up For Grabs)');
                $handle->setComplete(true);
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

          $image_phids = mpull($users, 'getProfileImagePHID');
          $image_phids = array_unique(array_filter($image_phids));

          $images = array();
          if ($image_phids) {
            $images = id(new PhabricatorFile())->loadAllWhere(
              'phid IN (%Ls)',
              $image_phids);
            $images = mpull($images, 'getViewURI', 'getPHID');
          }

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
              $handle->setAlternateID($user->getID());
              $handle->setComplete(true);

              $img_uri = idx($images, $user->getProfileImagePHID());
              if ($img_uri) {
                $handle->setImageURI($img_uri);
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
              $handle->setComplete(true);
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
              $handle->setComplete(true);

              $status = $rev->getStatus();
              if (($status == DifferentialRevisionStatus::COMMITTED) ||
                  ($status == DifferentialRevisionStatus::ABANDONED)) {
                $closed = PhabricatorObjectHandleStatus::STATUS_CLOSED;
                $handle->setStatus($closed);
              }

            }
            $handles[$phid] = $handle;
          }
          break;
        case PhabricatorPHIDConstants::PHID_TYPE_CMIT:
          $class = 'PhabricatorRepositoryCommit';
          PhutilSymbolLoader::loadClass($class);
          $object = newv($class, array());

          $commits = $object->loadAllWhere('phid in (%Ls)', $phids);
          $commits = mpull($commits, null, 'getPHID');

          $repository_ids = mpull($commits, 'getRepositoryID');
          $repositories = id(new PhabricatorRepository())->loadAllWhere(
            'id in (%Ld)', array_unique($repository_ids));
          $callsigns = mpull($repositories, 'getCallsign');

          foreach ($phids as $phid) {
            $handle = new PhabricatorObjectHandle();
            $handle->setPHID($phid);
            $handle->setType($type);
            if (empty($commits[$phid]) ||
                !isset($callsigns[$repository_ids[$phid]])) {
              $handle->setName('Unknown Commit');
            } else {
              $commit = $commits[$phid];
              $callsign = $callsigns[$repository_ids[$phid]];
              $repository = $repositories[$repository_ids[$phid]];
              $commit_identifier = $commit->getCommitIdentifier();

              // In case where the repository for the commit was deleted,
              // we don't have have info about the repository anymore.
              if ($repository) {
                $vcs = $repository->getVersionControlSystem();
                if ($vcs == PhabricatorRepositoryType::REPOSITORY_TYPE_GIT) {
                  $short_identifier = substr($commit_identifier, 0, 16);
                } else {
                  $short_identifier = $commit_identifier;
                }

                $handle->setName('r'.$callsign.$short_identifier);
              } else {

                $handle->setName('Commit '.'r'.$callsign.$commit_identifier);
              }

              $handle->setURI('/r'.$callsign.$commit_identifier);
              $handle->setFullName('r'.$callsign.$commit_identifier);
              $handle->setTimestamp($commit->getEpoch());
              $handle->setComplete(true);
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
              $handle->setComplete(true);
              if ($task->getStatus() != ManiphestTaskStatus::STATUS_OPEN) {
                $closed = PhabricatorObjectHandleStatus::STATUS_CLOSED;
                $handle->setStatus($closed);
              }
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
              $handle->setComplete(true);
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
              $handle->setComplete(true);
            }
            $handles[$phid] = $handle;
          }
          break;
        case PhabricatorPHIDConstants::PHID_TYPE_REPO:
          $class = 'PhabricatorRepository';
          PhutilSymbolLoader::loadClass($class);
          $object = newv($class, array());

          $repositories = $object->loadAllWhere('phid in (%Ls)', $phids);
          $repositories = mpull($repositories, null, 'getPHID');

          foreach ($phids as $phid) {
            $handle = new PhabricatorObjectHandle();
            $handle->setPHID($phid);
            $handle->setType($type);
            if (empty($repositories[$phid])) {
              $handle->setName('Unknown Repository');
            } else {
              $repository = $repositories[$phid];
              $handle->setName($repository->getCallsign());
              $handle->setURI('/diffusion/'.$repository->getCallsign().'/');
              $handle->setComplete(true);
            }
            $handles[$phid] = $handle;
          }
          break;
        case PhabricatorPHIDConstants::PHID_TYPE_OPKG:
          $class = 'PhabricatorOwnersPackage';
          PhutilSymbolLoader::loadClass($class);
          $object = newv($class, array());

          $packages = $object->loadAllWhere('phid in (%Ls)', $phids);
          $packages = mpull($packages, null, 'getPHID');

          foreach ($phids as $phid) {
            $handle = new PhabricatorObjectHandle();
            $handle->setPHID($phid);
            $handle->setType($type);
            if (empty($packages[$phid])) {
              $handle->setName('Unknown Package');
            } else {
              $package = $packages[$phid];
              $handle->setName($package->getName());
              $handle->setURI('/owners/package/'.$package->getID().'/');
              $handle->setComplete(true);
            }
            $handles[$phid] = $handle;
          }
          break;
        case PhabricatorPHIDConstants::PHID_TYPE_APRJ:
          $project_dao = newv('PhabricatorRepositoryArcanistProject', array());

          $projects = $project_dao->loadAllWhere(
            'phid IN (%Ls)',
            $phids);
          $projects = mpull($projects, null, 'getPHID');
          foreach ($phids as $phid) {
            $handle = new PhabricatorObjectHandle();
            $handle->setPHID($phid);
            $handle->setType($type);
            if (empty($projects[$phid])) {
              $handle->setName('Unknown Arcanist Project');
            } else {
              $project = $projects[$phid];
              $handle->setName($project->getName());
              $handle->setComplete(true);
            }
            $handles[$phid] = $handle;
          }
          break;
        case PhabricatorPHIDConstants::PHID_TYPE_WIKI:
          $document_dao = newv('PhrictionDocument', array());
          $content_dao  = newv('PhrictionContent', array());

          $conn = $document_dao->establishConnection('r');
          $documents = queryfx_all(
            $conn,
            'SELECT * FROM %T document JOIN %T content
              ON document.contentID = content.id
              WHERE document.phid IN (%Ls)',
              $document_dao->getTableName(),
              $content_dao->getTableName(),
              $phids);
          $documents = ipull($documents, null, 'phid');

          foreach ($phids as $phid) {
            $handle = new PhabricatorObjectHandle();
            $handle->setPHID($phid);
            $handle->setType($type);
            if (empty($documents[$phid])) {
              $handle->setName('Unknown Document');
            } else {
              $info = $documents[$phid];
              $handle->setName($info['title']);
              $handle->setURI(PhrictionDocument::getSlugURI($info['slug']));
              $handle->setComplete(true);
            }
            $handles[$phid] = $handle;
          }
          break;
        default:
          $loader = null;
          if (isset($external_loaders[$type])) {
            $loader = $external_loaders[$type];
          } else if (isset($external_loaders['*'])) {
            $loader = $external_loaders['*'];
          }

          if ($loader) {
            PhutilSymbolLoader::loadClass($loader);
            $object = newv($loader, array());
            $handles += $object->loadHandles($phids);
            break;
          }

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
