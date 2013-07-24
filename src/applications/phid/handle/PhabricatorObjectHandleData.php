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
    $phids = array_fuse($this->phids);

    $objects = id(new PhabricatorObjectQuery())
      ->setViewer($this->viewer)
      ->withPHIDs($phids)
      ->execute();

    // For objects which don't support PhabricatorPHIDType yet, load them the
    // old way.
    $phids = array_diff_key($phids, array_keys($objects));
    $types = phid_group_by_type($phids);
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

      case PhabricatorPHIDConstants::PHID_TYPE_QUES:
        $questions = id(new PonderQuestionQuery())
          ->setViewer($this->viewer)
          ->withPHIDs($phids)
          ->execute();
        return mpull($questions, null, 'getPHID');

      case PhabricatorPHIDConstants::PHID_TYPE_PIMG:
        $images = id(new PholioImage())
          ->loadAllWhere('phid IN (%Ls)', $phids);
        return mpull($images, null, 'getPHID');

      case PhabricatorPHIDConstants::PHID_TYPE_XACT:
        $subtypes = array();
        foreach ($phids as $phid) {
          $subtypes[phid_get_subtype($phid)][] = $phid;
        }
        $xactions = array();
        foreach ($subtypes as $subtype => $subtype_phids) {
          // TODO: Do this magically.
          switch ($subtype) {
            case PholioPHIDTypeMock::TYPECONST:
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

      case PhabricatorPHIDConstants::PHID_TYPE_XUSR:
        $xusr_dao = new PhabricatorExternalAccount();
        $xusrs = $xusr_dao->loadAllWhere(
          'phid in (%Ls)',
          $phids);
        return mpull($xusrs, null, 'getPHID');

      case PhabricatorPHIDConstants::PHID_TYPE_LEGD:
        $legds = id(new LegalpadDocumentQuery())
          ->needDocumentBodies(true)
          ->withPHIDs($phids)
          ->setViewer($this->viewer)
          ->execute();
        return mpull($legds, null, 'getPHID');

      case PhabricatorPHIDConstants::PHID_TYPE_CONP:
        $confs = id(new ConpherenceThreadQuery())
          ->withPHIDs($phids)
          ->setViewer($this->viewer)
          ->execute();
        return mpull($confs, null, 'getPHID');


    }

    return array();
  }

  public function loadHandles() {

    $application_handles = id(new PhabricatorHandleQuery())
      ->setViewer($this->viewer)
      ->withPHIDs($this->phids)
      ->execute();

    // TODO: Move the rest of this into Applications.

    $phid_map = array_fuse($this->phids);
    foreach ($application_handles as $handle) {
      if ($handle->isComplete()) {
        unset($phid_map[$handle->getPHID()]);
      }
    }

    $all_objects = $this->loadObjects();
    $types = phid_group_by_type($phid_map);

    $handles = array();

    foreach ($types as $type => $phids) {
      $objects = array_select_keys($all_objects, $phids);
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
                $handle->setTitle(
                  $statuses[$phid]->getTerseSummary($this->viewer));
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

        case PhabricatorPHIDConstants::PHID_TYPE_PIMG:
          foreach ($phids as $phid) {
            $handle = new PhabricatorObjectHandle();
            $handle->setPHID($phid);
            $handle->setType($type);
            if (empty($objects[$phid])) {
              $handle->setName('Unknown Image');
            } else {
              $image = $objects[$phid];
              $handle->setName($image->getName());
              $handle->setFullName($image->getName());
              $handle->setURI(
                '/M'.$image->getMockID().'/'.$image->getID().'/');
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

        case PhabricatorPHIDConstants::PHID_TYPE_XUSR:
          foreach ($phids as $phid) {
            $handle = new PhabricatorObjectHandle();
            $handle->setPHID($phid);
            $handle->setType($type);
            if (empty($objects[$phid])) {
              $handle->setName('Unknown Display Name');
            } else {
              $xusr = $objects[$phid];
              $display_name = $xusr->getDisplayName();
              $handle->setName($display_name);
              $handle->setFullName($display_name.' (External User)');
            }
            $handles[$phid] = $handle;
          }
          break;

        case PhabricatorPHIDConstants::PHID_TYPE_LEGD:
          foreach ($phids as $phid) {
            $handle = new PhabricatorObjectHandle();
            $handle->setPHID($phid);
            $handle->setType($type);
            if (empty($objects[$phid])) {
              $handle->setName(pht('Unknown Legalpad Document'));
            } else {
              $document = $objects[$phid];
              $handle->setName($document->getDocumentBody()->getTitle());
              $handle->setFullName($document->getDocumentBody()->getTitle());
              $handle->setURI('/legalpad/view/'.$document->getID().'/');
              $handle->setComplete(true);
            }
            $handles[$phid] = $handle;
          }
          break;

        case PhabricatorPHIDConstants::PHID_TYPE_CONP:
          foreach ($phids as $phid) {
            $handle = new PhabricatorObjectHandle();
            $handle->setPHID($phid);
            $handle->setType($type);
            if (empty($objects[$phid])) {
              $handle->setName(pht('Unknown Conpherence Thread'));
            } else {
              $thread = $objects[$phid];
              $name = $thread->getTitle();
              if (!strlen($name)) {
                $name = pht('[No Title]');
              }
              $handle->setName($name);
              $handle->setFullName($name);
              $handle->setURI('/conpherence/'.$thread->getID().'/');
              $handle->setComplete(true);
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

    return $handles + $application_handles;
  }
}
