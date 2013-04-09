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

  public static function loadOneHandle($phid, PhabricatorUser $viewer) {
    $query = new PhabricatorObjectHandleData(array($phid));
    $query->setViewer($viewer);
    $handles = $query->loadHandles();
    return $handles[$phid];
  }

  public function loadObjects() {
    $types = phid_group_by_type($this->phids);

    $objects = array();
    foreach ($types as $type => $phids) {
      $objects += $this->loadObjectsOfType($type, $phids);
    }

    return $objects;
  }

  private function loadObjectsOfType($type, array $phids) {
    if (!$this->viewer) {
      throw new Exception(
        "You must provide a viewer to load handles or objects.");
    }

    switch ($type) {

      case PhabricatorPHIDConstants::PHID_TYPE_USER:
        // TODO: Update query + Batch User Images
        $user_dao = new PhabricatorUser();
        $users = $user_dao->loadAllWhere(
          'phid in (%Ls)',
          $phids);
        return mpull($users, null, 'getPHID');

      case PhabricatorPHIDConstants::PHID_TYPE_CMIT:
        $commits = id(new DiffusionCommitQuery())
          ->setViewer($this->viewer)
          ->withPHIDs($phids)
          ->execute();
        return mpull($commits, null, 'getPHID');

      case PhabricatorPHIDConstants::PHID_TYPE_TASK:
        // TODO: Update this to ManiphestTaskQuery, //especially// after we have
        // policy-awareness
        $task_dao = new ManiphestTask();
        $tasks = $task_dao->loadAllWhere(
          'phid IN (%Ls)',
          $phids);
        return mpull($tasks, null, 'getPHID');

      case PhabricatorPHIDConstants::PHID_TYPE_CONF:
        $config_dao = new PhabricatorConfigEntry();
        $entries = $config_dao->loadAllWhere(
          'phid IN (%Ls)',
          $phids);
        return mpull($entries, null, 'getPHID');

      case PhabricatorPHIDConstants::PHID_TYPE_FILE:
        // TODO: Update this to PhabricatorFileQuery
        $object = new PhabricatorFile();
        $files = $object->loadAllWhere('phid IN (%Ls)', $phids);
        return mpull($files, null, 'getPHID');

      case PhabricatorPHIDConstants::PHID_TYPE_PROJ:
        $projects = id(new PhabricatorProjectQuery())
          ->setViewer($this->viewer)
          ->withPHIDs($phids)
          ->execute();
        return mpull($projects, null, 'getPHID');

      case PhabricatorPHIDConstants::PHID_TYPE_REPO:
        // TODO: Update this to PhabricatorRepositoryQuery
        $object = new PhabricatorRepository();
        $repositories = $object->loadAllWhere('phid in (%Ls)', $phids);
        return mpull($repositories, null, 'getPHID');

      case PhabricatorPHIDConstants::PHID_TYPE_OPKG:
        $object = new PhabricatorOwnersPackage();
        $packages = $object->loadAllWhere('phid in (%Ls)', $phids);
        return mpull($packages, null, 'getPHID');

      case PhabricatorPHIDConstants::PHID_TYPE_APRJ:
        $project_dao = new PhabricatorRepositoryArcanistProject();
        $projects = $project_dao->loadAllWhere(
          'phid IN (%Ls)',
          $phids);
        return mpull($projects, null, 'getPHID');

      case PhabricatorPHIDConstants::PHID_TYPE_MLST:
        $object = new PhabricatorMetaMTAMailingList();
        $lists = $object->loadAllWhere('phid IN (%Ls)', $phids);
        return mpull($lists, null, 'getPHID');

      case PhabricatorPHIDConstants::PHID_TYPE_DREV:
        // TODO: Update this to DifferentialRevisionQuery
        $revision_dao = new DifferentialRevision();
        $revisions = $revision_dao->loadAllWhere(
          'phid IN (%Ls)',
          $phids);
        return mpull($revisions, null, 'getPHID');

      case PhabricatorPHIDConstants::PHID_TYPE_WIKI:
        // TODO: Update this to PhrictionDocumentQuery, already pre-package
        // content DAO
        $document_dao = new PhrictionDocument();
        $documents = $document_dao->loadAllWhere(
          'phid IN (%Ls)',
          $phids);
        return mpull($documents, null, 'getPHID');

      case PhabricatorPHIDConstants::PHID_TYPE_QUES:
        $questions = id(new PonderQuestionQuery())
          ->setViewer($this->viewer)
          ->withPHIDs($phids)
          ->execute();
        return mpull($questions, null, 'getPHID');

      case PhabricatorPHIDConstants::PHID_TYPE_MOCK:
        $mocks = id(new PholioMockQuery())
          ->setViewer($this->viewer)
          ->withPHIDs($phids)
          ->execute();
        return mpull($mocks, null, 'getPHID');

      case PhabricatorPHIDConstants::PHID_TYPE_XACT:
        $subtypes = array();
        foreach ($phids as $phid) {
          $subtypes[phid_get_subtype($phid)][] = $phid;
        }
        $xactions = array();
        foreach ($subtypes as $subtype => $subtype_phids) {
          // TODO: Do this magically.
          switch ($subtype) {
            case PhabricatorPHIDConstants::PHID_TYPE_MOCK:
              $results = id(new PholioTransactionQuery())
                ->setViewer($this->viewer)
                ->withPHIDs($subtype_phids)
                ->execute();
              $xactions += mpull($results, null, 'getPHID');
              break;
            case PhabricatorPHIDConstants::PHID_TYPE_MCRO:
              $results = id(new PhabricatorMacroTransactionQuery())
                ->setViewer($this->viewer)
                ->withPHIDs($subtype_phids)
                ->execute();
              $xactions += mpull($results, null, 'getPHID');
              break;
          }
        }
        return mpull($xactions, null, 'getPHID');

      case PhabricatorPHIDConstants::PHID_TYPE_MCRO:
        $macros = id(new PhabricatorMacroQuery())
          ->setViewer($this->viewer)
          ->withPHIDs($phids)
          ->execute();
        return mpull($macros, null, 'getPHID');

      case PhabricatorPHIDConstants::PHID_TYPE_PSTE:
        $pastes = id(new PhabricatorPasteQuery())
          ->withPHIDs($phids)
          ->setViewer($this->viewer)
          ->execute();
        return mpull($pastes, null, 'getPHID');

      case PhabricatorPHIDConstants::PHID_TYPE_BLOG:
        $blogs = id(new PhameBlogQuery())
          ->withPHIDs($phids)
          ->setViewer($this->viewer)
          ->execute();
        return mpull($blogs, null, 'getPHID');

      case PhabricatorPHIDConstants::PHID_TYPE_POST:
        $posts = id(new PhamePostQuery())
          ->withPHIDs($phids)
          ->setViewer($this->viewer)
          ->execute();
        return mpull($posts, null, 'getPHID');

      case PhabricatorPHIDConstants::PHID_TYPE_PVAR:
        $vars = id(new PhluxVariableQuery())
          ->withPHIDs($phids)
          ->setViewer($this->viewer)
          ->execute();
        return mpull($vars, null, 'getPHID');

    }
  }

  public function loadHandles() {

    $types = phid_group_by_type($this->phids);

    $handles = array();

    $external_loaders = PhabricatorEnv::getEnvConfig('phid.external-loaders');

    foreach ($types as $type => $phids) {
      $objects = $this->loadObjectsOfType($type, $phids);

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
          $image_phids = mpull($objects, 'getProfileImagePHID');
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
            if (empty($objects[$phid])) {
              $handle->setName('Unknown User');
            } else {
              $user = $objects[$phid];
              $handle->setName($user->getUsername());
              $handle->setURI('/p/'.$user->getUsername().'/');
              $handle->setFullName(
                $user->getUsername().' ('.$user->getRealName().')');
              $handle->setComplete(true);
              if (isset($statuses[$phid])) {
                $handle->setStatus($statuses[$phid]->getTextStatus());
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
          foreach ($phids as $phid) {
            $handle = new PhabricatorObjectHandle();
            $handle->setPHID($phid);
            $handle->setType($type);
            if (empty($objects[$phid])) {
              $handle->setName('Unknown Mailing List');
            } else {
              $list = $objects[$phid];
              $handle->setName($list->getName());
              $handle->setURI($list->getURI());
              $handle->setFullName($list->getName());
              $handle->setComplete(true);
            }
            $handles[$phid] = $handle;
          }
          break;

        case PhabricatorPHIDConstants::PHID_TYPE_DREV:
          foreach ($phids as $phid) {
            $handle = new PhabricatorObjectHandle();
            $handle->setPHID($phid);
            $handle->setType($type);
            if (empty($objects[$phid])) {
              $handle->setName('Unknown Revision');
            } else {
              $rev = $objects[$phid];
              $handle->setName('D'.$rev->getID());
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
          foreach ($phids as $phid) {
            $handle = new PhabricatorObjectHandle();
            $handle->setPHID($phid);
            $handle->setType($type);

            if (empty($objects[$phid])) {
              $handle->setName('Unknown Commit');
            } else {
              $repository = $objects[$phid]->getRepository();
              $commit = $objects[$phid];
              $callsign = $repository->getCallsign();
              $commit_identifier = $commit->getCommitIdentifier();

              $name = $repository->formatCommitName($commit_identifier);
              $handle->setName($name);

              $summary = $commit->getSummary();
              if (strlen($summary)) {
                $handle->setFullName($name.': '.$summary);
              } else {
                $handle->setFullName($name);
              }

              $handle->setURI('/r'.$callsign.$commit_identifier);
              $handle->setTimestamp($commit->getEpoch());
              $handle->setComplete(true);
            }

            $handles[$phid] = $handle;
          }
          break;

        case PhabricatorPHIDConstants::PHID_TYPE_TASK:
          foreach ($phids as $phid) {
            $handle = new PhabricatorObjectHandle();
            $handle->setPHID($phid);
            $handle->setType($type);
            if (empty($objects[$phid])) {
              $handle->setName('Unknown Task');
            } else {
              $task = $objects[$phid];
              $handle->setName('T'.$task->getID());
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

        case PhabricatorPHIDConstants::PHID_TYPE_CONF:
          foreach ($phids as $phid) {
            $handle = new PhabricatorObjectHandle();
            $handle->setPHID($phid);
            $handle->setType($type);
            if (empty($objects[$phid])) {
              $handle->setName('Unknown Config Entry');
            } else {
              $entry = $objects[$phid];
              $handle->setName($entry->getKey());
              $handle->setURI('/config/edit/'.$entry->getKey());
              $handle->setFullName($entry->getKey());
              $handle->setComplete(true);
            }
            $handles[$phid] = $handle;
          }
          break;

        case PhabricatorPHIDConstants::PHID_TYPE_FILE:
          foreach ($phids as $phid) {
            $handle = new PhabricatorObjectHandle();
            $handle->setPHID($phid);
            $handle->setType($type);
            if (empty($objects[$phid])) {
              $handle->setName('Unknown File');
            } else {
              $file = $objects[$phid];
              $handle->setName('F'.$file->getID());
              $handle->setFullName('F'.$file->getID().' '.$file->getName());
              $handle->setURI($file->getBestURI());
              $handle->setComplete(true);
            }
            $handles[$phid] = $handle;
          }
          break;

        case PhabricatorPHIDConstants::PHID_TYPE_PROJ:
          foreach ($phids as $phid) {
            $handle = new PhabricatorObjectHandle();
            $handle->setPHID($phid);
            $handle->setType($type);
            if (empty($objects[$phid])) {
              $handle->setName('Unknown Project');
            } else {
              $project = $objects[$phid];
              $handle->setName($project->getName());
              $handle->setURI('/project/view/'.$project->getID().'/');
              $handle->setComplete(true);
            }
            $handles[$phid] = $handle;
          }
          break;

        case PhabricatorPHIDConstants::PHID_TYPE_REPO:
          foreach ($phids as $phid) {
            $handle = new PhabricatorObjectHandle();
            $handle->setPHID($phid);
            $handle->setType($type);
            if (empty($objects[$phid])) {
              $handle->setName('Unknown Repository');
            } else {
              $repository = $objects[$phid];
              $handle->setName('r'.$repository->getCallsign());
              $handle->setFullName("r" . $repository->getCallsign() .
                " (" . $repository->getName() . ")");
              $handle->setURI('/diffusion/'.$repository->getCallsign().'/');
              $handle->setComplete(true);
            }
            $handles[$phid] = $handle;
          }
          break;

        case PhabricatorPHIDConstants::PHID_TYPE_OPKG:
          foreach ($phids as $phid) {
            $handle = new PhabricatorObjectHandle();
            $handle->setPHID($phid);
            $handle->setType($type);
            if (empty($objects[$phid])) {
              $handle->setName('Unknown Package');
            } else {
              $package = $objects[$phid];
              $handle->setName($package->getName());
              $handle->setURI('/owners/package/'.$package->getID().'/');
              $handle->setComplete(true);
            }
            $handles[$phid] = $handle;
          }
          break;

        case PhabricatorPHIDConstants::PHID_TYPE_APRJ:
          foreach ($phids as $phid) {
            $handle = new PhabricatorObjectHandle();
            $handle->setPHID($phid);
            $handle->setType($type);
            if (empty($objects[$phid])) {
              $handle->setName('Unknown Arcanist Project');
            } else {
              $project = $objects[$phid];
              $handle->setName($project->getName());
              $handle->setComplete(true);
            }
            $handles[$phid] = $handle;
          }
          break;

        case PhabricatorPHIDConstants::PHID_TYPE_WIKI:
          // TODO: Update this
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
              $handle->setFullName($info['title']);
              $handle->setComplete(true);
              if ($info['status'] != PhrictionDocumentStatus::STATUS_EXISTS) {
                $closed = PhabricatorObjectHandleStatus::STATUS_CLOSED;
                $handle->setStatus($closed);
              }
            }
            $handles[$phid] = $handle;
          }
          break;

        case PhabricatorPHIDConstants::PHID_TYPE_QUES:
          foreach ($phids as $phid) {
            $handle = new PhabricatorObjectHandle();
            $handle->setPHID($phid);
            $handle->setType($type);
            if (empty($objects[$phid])) {
              $handle->setName('Unknown Ponder Question');
            } else {
              $question = $objects[$phid];
              $handle->setName('Q' . $question->getID());
              $handle->setFullName(
                phutil_utf8_shorten($question->getTitle(), 60));
              $handle->setURI(new PhutilURI('/Q' . $question->getID()));
              $handle->setComplete(true);
            }
            $handles[$phid] = $handle;
          }
          break;

        case PhabricatorPHIDConstants::PHID_TYPE_PSTE:
          foreach ($phids as $phid) {
            $handle = new PhabricatorObjectHandle();
            $handle->setPHID($phid);
            $handle->setType($type);
            if (empty($objects[$phid])) {
              $handle->setName('Unknown Paste');
            } else {
              $paste = $objects[$phid];
              $handle->setName('P'.$paste->getID());
              $handle->setFullName($paste->getFullName());
              $handle->setURI('/P'.$paste->getID());
              $handle->setComplete(true);
            }
            $handles[$phid] = $handle;
          }
          break;

        case PhabricatorPHIDConstants::PHID_TYPE_BLOG:
          foreach ($phids as $phid) {
            $handle = new PhabricatorObjectHandle();
            $handle->setPHID($phid);
            $handle->setType($type);
            if (empty($objects[$phid])) {
              $handle->setName('Unknown Blog');
            } else {
              $blog = $objects[$phid];
              $handle->setName($blog->getName());
              $handle->setFullName($blog->getName());
              $handle->setURI('/phame/blog/view/'.$blog->getID().'/');
              $handle->setComplete(true);
            }
            $handles[$phid] = $handle;
          }
          break;

        case PhabricatorPHIDConstants::PHID_TYPE_POST:
          foreach ($phids as $phid) {
            $handle = new PhabricatorObjectHandle();
            $handle->setPHID($phid);
            $handle->setType($type);
            if (empty($objects[$phid])) {
              $handle->setName('Unknown Post');
            } else {
              $post = $objects[$phid];
              $handle->setName($post->getTitle());
              $handle->setFullName($post->getTitle());
              $handle->setURI('/phame/post/view/'.$post->getID().'/');
              $handle->setComplete(true);
            }
            $handles[$phid] = $handle;
          }
          break;

        case PhabricatorPHIDConstants::PHID_TYPE_MOCK:
          foreach ($phids as $phid) {
            $handle = new PhabricatorObjectHandle();
            $handle->setPHID($phid);
            $handle->setType($type);
            if (empty($objects[$phid])) {
              $handle->setName('Unknown Mock');
            } else {
              $mock = $objects[$phid];
              $handle->setName('M'.$mock->getID());
              $handle->setFullName('M'.$mock->getID().': '.$mock->getName());
              $handle->setURI('/M'.$mock->getID());
              $handle->setComplete(true);
            }
            $handles[$phid] = $handle;
          }
          break;

        case PhabricatorPHIDConstants::PHID_TYPE_MCRO:
          foreach ($phids as $phid) {
            $handle = new PhabricatorObjectHandle();
            $handle->setPHID($phid);
            $handle->setType($type);
            if (empty($objects[$phid])) {
              $handle->setName('Unknown Macro');
            } else {
              $macro = $objects[$phid];
              $handle->setName($macro->getName());
              $handle->setFullName('Image Macro "'.$macro->getName().'"');
              $handle->setURI('/macro/view/'.$macro->getID().'/');
              $handle->setComplete(true);
            }
            $handles[$phid] = $handle;
          }
          break;

        case PhabricatorPHIDConstants::PHID_TYPE_PVAR:
          foreach ($phids as $phid) {
            $handle = new PhabricatorObjectHandle();
            $handle->setPHID($phid);
            $handle->setType($type);
            if (empty($objects[$phid])) {
              $handle->setName('Unknown Variable');
            } else {
              $var = $objects[$phid];
              $key = $var->getVariableKey();
              $handle->setName($key);
              $handle->setFullName('Phlux Variable "'.$key.'"');
              $handle->setURI('/phlux/view/'.$key.'/');
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
            assert_instances_of(array($type => $object), 'ObjectHandleLoader');
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
