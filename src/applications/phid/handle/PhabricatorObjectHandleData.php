<?php

final class PhabricatorObjectHandleData {

  private $phids;
  private $viewer;

  public function __construct(array $phids) {
    $this->phids = array_unique($phids);
  }

  public function setViewer(PhabricatorUser $viewer) {
    $this->viewer = $viewer;
    return $this;
  }

  public static function loadOneHandle($phid, $viewer = null) {
    $query = new PhabricatorObjectHandleData(array($phid));

    if ($viewer) {
      $query->setViewer($viewer);
    }

    $handles = $query->loadHandles();
    return $handles[$phid];
  }

  public function loadObjects() {
    $types = array();
    foreach ($this->phids as $phid) {
      $type = phid_get_type($phid);
      $types[$type][] = $phid;
    }

    $objects = array();
    foreach ($types as $type => $phids) {
      switch ($type) {
        case PhabricatorPHIDConstants::PHID_TYPE_USER:
          $user_dao = new PhabricatorUser();
          $users = $user_dao->loadAllWhere(
            'phid in (%Ls)',
            $phids);
          foreach ($users as $user) {
            $objects[$user->getPHID()] = $user;
          }
          break;
        case PhabricatorPHIDConstants::PHID_TYPE_CMIT:
          $commit_dao = new PhabricatorRepositoryCommit();
          $commits = $commit_dao->loadAllWhere(
            'phid IN (%Ls)',
            $phids);
          $commit_data = array();
          if ($commits) {
            $data_dao = new PhabricatorRepositoryCommitData();
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
          $task_dao = new ManiphestTask();
          $tasks = $task_dao->loadAllWhere(
            'phid IN (%Ls)',
            $phids);
          foreach ($tasks as $task) {
            $objects[$task->getPHID()] = $task;
          }
          break;
        case PhabricatorPHIDConstants::PHID_TYPE_DREV:
          $revision_dao = new DifferentialRevision();
          $revisions = $revision_dao->loadAllWhere(
            'phid IN (%Ls)',
            $phids);
          foreach ($revisions as $revision) {
            $objects[$revision->getPHID()] = $revision;
          }
          break;
        case PhabricatorPHIDConstants::PHID_TYPE_QUES:
          $questions = id(new PonderQuestionQuery())
            ->withPHIDs($phids)
            ->execute();
          foreach ($questions as $question) {
            $objects[$question->getPHID()] = $question;
          }
          break;
      }
    }

    return $objects;
  }

  public function loadHandles() {

    $types = phid_group_by_type($this->phids);

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
              case ManiphestTaskOwner::PROJECT_NO_PROJECT:
                $handle->setName('No Project');
                $handle->setFullName('noproject (No Project)');
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
          $object = new PhabricatorUser();

          $users = $object->loadAllWhere('phid IN (%Ls)', $phids);
          $users = mpull($users, null, 'getPHID');

          $image_phids = mpull($users, 'getProfileImagePHID');
          $image_phids = array_unique(array_filter($image_phids));

          $images = array();
          if ($image_phids) {
            $images = id(new PhabricatorFile())->loadAllWhere(
              'phid IN (%Ls)',
              $image_phids);
            $images = mpull($images, 'getBestURI', 'getPHID');
          }

          $statuses = id(new PhabricatorUserStatus())->loadCurrentStatuses(
            $phids);

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
              $handle->setFullName(
                $user->getUsername().' ('.$user->getRealName().')');
              $handle->setAlternateID($user->getID());
              $handle->setComplete(true);
              if (isset($statuses[$phid])) {
                $status = $statuses[$phid]->getTextStatus();
                if ($this->viewer) {
                  $date = $statuses[$phid]->getDateTo();
                  $status .= ' until '.phabricator_date($date, $this->viewer);
                }
                $handle->setStatus($status);
              }
              $handle->setDisabled($user->getIsDisabled());

              $img_uri = idx($images, $user->getProfileImagePHID());
              if ($img_uri) {
                $handle->setImageURI($img_uri);
              } else {
                $handle->setImageURI(
                  PhabricatorUser::getDefaultProfileImageURI());
              }
            }
            $handles[$phid] = $handle;
          }
          break;
        case PhabricatorPHIDConstants::PHID_TYPE_MLST:
          $object = new PhabricatorMetaMTAMailingList();

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
              $handle->setName($list->getName());
              $handle->setURI($list->getURI());
              $handle->setFullName($list->getName());
              $handle->setComplete(true);
            }
            $handles[$phid] = $handle;
          }
          break;
        case PhabricatorPHIDConstants::PHID_TYPE_DREV:
          $object = new DifferentialRevision();

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
              if (($status == ArcanistDifferentialRevisionStatus::CLOSED) ||
                  ($status == ArcanistDifferentialRevisionStatus::ABANDONED)) {
                $closed = PhabricatorObjectHandleStatus::STATUS_CLOSED;
                $handle->setStatus($closed);
              }

            }
            $handles[$phid] = $handle;
          }
          break;
        case PhabricatorPHIDConstants::PHID_TYPE_CMIT:
          $object = new PhabricatorRepositoryCommit();

          $commits = $object->loadAllWhere('phid in (%Ls)', $phids);
          $commits = mpull($commits, null, 'getPHID');

          $repository_ids = array();
          $callsigns = array();
          if ($commits) {
            $repository_ids = mpull($commits, 'getRepositoryID');
            $repositories = id(new PhabricatorRepository())->loadAllWhere(
              'id in (%Ld)', array_unique($repository_ids));
            $callsigns = mpull($repositories, 'getCallsign');
          }

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
                $name = $repository->formatCommitName($commit_identifier);
                $handle->setName($name);
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
          $object = new ManiphestTask();

          $tasks = $object->loadAllWhere('phid in (%Ls)', $phids);
          $tasks = mpull($tasks, null, 'getPHID');

          foreach ($phids as $phid) {
            $handle = new PhabricatorObjectHandle();
            $handle->setPHID($phid);
            $handle->setType($type);
            if (empty($tasks[$phid])) {
              $handle->setName('Unknown Task');
            } else {
              $task = $tasks[$phid];
              $handle->setName($task->getTitle());
              $handle->setURI('/T'.$task->getID());
              $handle->setFullName('T'.$task->getID().': '.$task->getTitle());
              $handle->setComplete(true);
              $handle->setAlternateID($task->getID());
              if ($task->getStatus() != ManiphestTaskStatus::STATUS_OPEN) {
                $closed = PhabricatorObjectHandleStatus::STATUS_CLOSED;
                $handle->setStatus($closed);
              }
            }
            $handles[$phid] = $handle;
          }
          break;
        case PhabricatorPHIDConstants::PHID_TYPE_FILE:
          $object = new PhabricatorFile();

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
              $handle->setURI($file->getBestURI());
              $handle->setComplete(true);
            }
            $handles[$phid] = $handle;
          }
          break;
        case PhabricatorPHIDConstants::PHID_TYPE_PROJ:
          $object = new PhabricatorProject();

          if ($this->viewer) {
            $projects = id(new PhabricatorProjectQuery())
              ->setViewer($this->viewer)
              ->withPHIDs($phids)
              ->execute();
          } else {
            $projects = $object->loadAllWhere('phid IN (%Ls)', $phids);
          }

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
          $object = new PhabricatorRepository();

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
          $object = new PhabricatorOwnersPackage();

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
          $project_dao = new PhabricatorRepositoryArcanistProject();

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
          $document_dao = new PhrictionDocument();
          $content_dao  = new PhrictionContent();

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
        case PhabricatorPHIDConstants::PHID_TYPE_QUES:
          $questions = id(new PonderQuestionQuery())
            ->withPHIDs($phids)
            ->execute();
          $questions = mpull($questions, null, 'getPHID');

          foreach ($phids as $phid) {
            $handle = new PhabricatorObjectHandle();
            $handle->setPHID($phid);
            $handle->setType($type);
            if (empty($questions[$phid])) {
              $handle->setName('Unknown Ponder Question');
            } else {
              $question = $questions[$phid];
              $handle->setName(phutil_utf8_shorten($question->getTitle(), 60));
              $handle->setURI(new PhutilURI('/Q' . $question->getID()));
              $handle->setComplete(true);
            }
            $handles[$phid] = $handle;
          }
          break;
        case PhabricatorPHIDConstants::PHID_TYPE_PSTE:
          $pastes = id(new PhabricatorPasteQuery())
            ->withPHIDs($phids)
            ->setViewer($this->viewer)
            ->execute();
          $pastes = mpull($pastes, null, 'getPHID');

          foreach ($phids as $phid) {
            $handle = new PhabricatorObjectHandle();
            $handle->setPHID($phid);
            $handle->setType($type);
            if (empty($pastes[$phid])) {
              $handle->setName('Unknown Paste');
            } else {
              $paste = $pastes[$phid];
              $handle->setName($paste->getTitle());
              $handle->setFullName($paste->getFullName());
              $handle->setURI('/P'.$paste->getID());
              $handle->setComplete(true);
            }
            $handles[$phid] = $handle;
          }
          break;
        case PhabricatorPHIDConstants::PHID_TYPE_BLOG:
          $blogs = id(new PhameBlogQuery())
            ->withPHIDs($phids)
            ->setViewer($this->viewer)
            ->execute();
          $blogs = mpull($blogs, null, 'getPHID');

          foreach ($phids as $phid) {
            $handle = new PhabricatorObjectHandle();
            $handle->setPHID($phid);
            $handle->setType($type);
            if (empty($blogs[$phid])) {
              $handle->setName('Unknown Blog');
            } else {
              $blog = $blogs[$phid];
              $handle->setName($blog->getName());
              $handle->setFullName($blog->getName());
              $handle->setURI('/phame/blog/view/'.$blog->getID().'/');
              $handle->setComplete(true);
            }
            $handles[$phid] = $handle;
          }
          break;
        case PhabricatorPHIDConstants::PHID_TYPE_POST:
          $posts = id(new PhamePostQuery())
            ->withPHIDs($phids)
            ->setViewer($this->viewer)
            ->execute();
          $posts = mpull($posts, null, 'getPHID');

          foreach ($phids as $phid) {
            $handle = new PhabricatorObjectHandle();
            $handle->setPHID($phid);
            $handle->setType($type);
            if (empty($posts[$phid])) {
              $handle->setName('Unknown Post');
            } else {
              $post = $posts[$phid];
              $handle->setName($post->getTitle());
              $handle->setFullName($post->getTitle());
              $handle->setURI('/phame/post/view/'.$post->getID().'/');
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
}
