<?php

final class PhabricatorOwnersPackageEditor extends PhabricatorEditor {

  private $package;

  public function setPackage(PhabricatorOwnersPackage $package) {
    $this->package = $package;
    return $this;
  }

  public function getPackage() {
    return $this->package;
  }

  public function save() {
    $actor = $this->getActor();
    $package = $this->getPackage();
    $package->attachActorPHID($actor->getPHID());

    if ($package->getID()) {
      $is_new = false;
    } else {
      $is_new = true;
    }

    $package->openTransaction();

      $ret = $package->save();

      $add_owners = array();
      $remove_owners = array();
      $all_owners = array();
      if ($package->getUnsavedOwners()) {
        $new_owners = array_fill_keys($package->getUnsavedOwners(), true);
        $cur_owners = array();
        foreach ($package->loadOwners() as $owner) {
          if (empty($new_owners[$owner->getUserPHID()])) {
            $remove_owners[$owner->getUserPHID()] = true;
            $owner->delete();
            continue;
          }
          $cur_owners[$owner->getUserPHID()] = true;
        }

        $add_owners = array_diff_key($new_owners, $cur_owners);
        $all_owners = array_merge(
          array($package->getPrimaryOwnerPHID() => true),
          $new_owners,
          $remove_owners);
        foreach ($add_owners as $phid => $ignored) {
          $owner = new PhabricatorOwnersOwner();
          $owner->setPackageID($package->getID());
          $owner->setUserPHID($phid);
          $owner->save();
        }
        $package->attachUnsavedOwners(array());
      }

      $add_paths = array();
      $remove_paths = array();
      $touched_repos = array();
      if ($package->getUnsavedPaths()) {
        $new_paths = igroup(
          $package->getUnsavedPaths(),
          'repositoryPHID',
          'path');
        $cur_paths = $package->loadPaths();
        foreach ($cur_paths as $key => $path) {
          $repository_phid = $path->getRepositoryPHID();
          $new_path = head(idx(
            idx($new_paths, $repository_phid, array()),
            $path->getPath(),
            array()));
          $excluded = $path->getExcluded();
          if ($new_path === false ||
              idx($new_path, 'excluded') != $excluded) {
            $touched_repos[$repository_phid] = true;
            $remove_paths[$repository_phid][$path->getPath()] = $excluded;
            $path->delete();
            unset($cur_paths[$key]);
          }
        }

        $cur_paths = mgroup($cur_paths, 'getRepositoryPHID', 'getPath');
        $repositories = id(new PhabricatorRepositoryQuery())
          ->setViewer($actor)
          ->withPHIDs(array_keys($cur_paths))
          ->execute();
        $repositories = mpull($repositories, null, 'getPHID');
        foreach ($new_paths as $repository_phid => $paths) {
          $repository = idx($repositories, $repository_phid);
          if (!$repository) {
            continue;
          }
          foreach ($paths as $path => $dicts) {
            $path = ltrim($path, '/');
            // build query to validate path
            $drequest = DiffusionRequest::newFromDictionary(
              array(
                'user' => $actor,
                'repository'  => $repository,
                'path'        => $path,
              ));
            $results = DiffusionBrowseResultSet::newFromConduit(
              DiffusionQuery::callConduitWithDiffusionRequest(
                $actor,
                $drequest,
                'diffusion.browsequery',
                array(
                  'commit' => $drequest->getCommit(),
                  'path' => $path,
                  'needValidityOnly' => true,
                )));
            $valid = $results->isValidResults();
            $is_directory = true;
            if (!$valid) {
              switch ($results->getReasonForEmptyResultSet()) {
                case DiffusionBrowseResultSet::REASON_IS_FILE:
                  $valid = true;
                  $is_directory = false;
                  break;
                case DiffusionBrowseResultSet::REASON_IS_EMPTY:
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
              $excluded = idx(reset($dicts), 'excluded', 0);
              $add_paths[$repository_phid][$path] = $excluded;
              $obj = new PhabricatorOwnersPath();
              $obj->setPackageID($package->getID());
              $obj->setRepositoryPHID($repository_phid);
              $obj->setPath($path);
              $obj->setExcluded($excluded);
              $obj->save();
            }
          }
        }
        $package->attachUnsavedPaths(array());
      }

    $package->saveTransaction();

    if ($is_new) {
      $mail = new PackageCreateMail($package);
    } else {
      $mail = new PackageModifyMail(
        $package,
        array_keys($add_owners),
        array_keys($remove_owners),
        array_keys($all_owners),
        array_keys($touched_repos),
        $add_paths,
        $remove_paths);
    }
    $mail->setActor($actor);
    $mail->send();

    return $ret;
  }

  public function delete() {
    $actor = $this->getActor();
    $package = $this->getPackage();
    $package->attachActorPHID($actor->getPHID());

    $mails = id(new PackageDeleteMail($package))
      ->setActor($actor)
      ->prepareMails();

    $package->openTransaction();

      foreach ($package->loadOwners() as $owner) {
        $owner->delete();
      }
      foreach ($package->loadPaths() as $path) {
        $path->delete();
      }
      $ret = $package->delete();

    $package->saveTransaction();

    foreach ($mails as $mail) {
      $mail->saveAndSend();
    }

    return $ret;
  }

}
