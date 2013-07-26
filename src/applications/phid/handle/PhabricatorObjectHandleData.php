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

      case PhabricatorPHIDConstants::PHID_TYPE_APRJ:
        $project_dao = new PhabricatorRepositoryArcanistProject();
        $projects = $project_dao->loadAllWhere(
          'phid IN (%Ls)',
          $phids);
        return mpull($projects, null, 'getPHID');

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
            case PhabricatorMacroPHIDTypeMacro::TYPECONST:
              $results = id(new PhabricatorMacroTransactionQuery())
                ->setViewer($this->viewer)
                ->withPHIDs($subtype_phids)
                ->execute();
              $xactions += mpull($results, null, 'getPHID');
              break;
          }
        }
        return mpull($xactions, null, 'getPHID');

      case PhabricatorPHIDConstants::PHID_TYPE_POST:
        $posts = id(new PhamePostQuery())
          ->withPHIDs($phids)
          ->setViewer($this->viewer)
          ->execute();
        return mpull($posts, null, 'getPHID');

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
