<?php

abstract class PhragmentController extends PhabricatorController {

  protected function loadParentFragments($path) {
    $components = explode('/', $path);

    $combinations = array();
    $current = '';
    foreach ($components as $component) {
      $current .= '/'.$component;
      $current = trim($current, '/');
      if (trim($current) === '') {
        continue;
      }

      $combinations[] = $current;
    }

    $fragments = array();
    $results = id(new PhragmentFragmentQuery())
      ->setViewer($this->getRequest()->getUser())
      ->needLatestVersion(true)
      ->withPaths($combinations)
      ->execute();
    foreach ($combinations as $combination) {
      $found = false;
      foreach ($results as $fragment) {
        if ($fragment->getPath() === $combination) {
          $fragments[] = $fragment;
          $found = true;
          break;
        }
      }
      if (!$found) {
        return null;
      }
    }
    return $fragments;
  }

  protected function buildApplicationCrumbsWithPath(array $fragments) {
    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addCrumb(
      id(new PhabricatorCrumbView())
        ->setName('/')
        ->setHref('/phragment/'));
    foreach ($fragments as $parent) {
      $crumbs->addCrumb(
        id(new PhabricatorCrumbView())
          ->setName($parent->getName())
          ->setHref('/phragment/browse/'.$parent->getPath()));
    }
    return $crumbs;
  }

  protected function createCurrentFragmentView($fragment, $is_history_view) {
    if ($fragment === null) {
      return null;
    }

    $viewer = $this->getRequest()->getUser();

    $phids = array();
    $phids[] = $fragment->getLatestVersionPHID();

    $snapshot_phids = array();
    $snapshots = id(new PhragmentSnapshotQuery())
      ->setViewer($viewer)
      ->withPrimaryFragmentPHIDs(array($fragment->getPHID()))
      ->execute();
    foreach ($snapshots as $snapshot) {
      $phids[] = $snapshot->getPHID();
      $snapshot_phids[] = $snapshot->getPHID();
    }

    $this->loadHandles($phids);

    $file = null;
    $file_uri = null;
    if (!$fragment->isDirectory()) {
      $file = id(new PhabricatorFileQuery())
        ->setViewer($viewer)
        ->withPHIDs(array($fragment->getLatestVersion()->getFilePHID()))
        ->executeOne();
      if ($file !== null) {
        $file_uri = $file->getBestURI();
      }
    }

    $header = id(new PHUIHeaderView())
      ->setHeader($fragment->getName())
      ->setPolicyObject($fragment)
      ->setUser($viewer);

    $actions = id(new PhabricatorActionListView())
      ->setUser($viewer)
      ->setObject($fragment)
      ->setObjectURI($fragment->getURI());
    $actions->addAction(
      id(new PhabricatorActionView())
        ->setName(pht('Download Fragment'))
        ->setHref($file_uri)
        ->setDisabled($file === null)
        ->setIcon('download'));
    $actions->addAction(
      id(new PhabricatorActionView())
        ->setName(pht('Download Contents as ZIP'))
        ->setHref($this->getApplicationURI("zip/".$fragment->getPath()))
        ->setDisabled(false) // TODO: Policy
        ->setIcon('zip'));
    if (!$fragment->isDirectory()) {
      $actions->addAction(
        id(new PhabricatorActionView())
          ->setName(pht('Update Fragment'))
          ->setHref($this->getApplicationURI("update/".$fragment->getPath()))
          ->setDisabled(false) // TODO: Policy
          ->setIcon('edit'));
    } else {
      $actions->addAction(
        id(new PhabricatorActionView())
          ->setName(pht('Convert to File'))
          ->setHref($this->getApplicationURI("update/".$fragment->getPath()))
          ->setDisabled(false) // TODO: Policy
          ->setIcon('edit'));
    }
    if ($is_history_view) {
      $actions->addAction(
        id(new PhabricatorActionView())
          ->setName(pht('View Child Fragments'))
          ->setHref($this->getApplicationURI("browse/".$fragment->getPath()))
          ->setIcon('browse'));
    } else {
      $actions->addAction(
        id(new PhabricatorActionView())
          ->setName(pht('View History'))
          ->setHref($this->getApplicationURI("history/".$fragment->getPath()))
          ->setIcon('history'));
    }
    $actions->addAction(
      id(new PhabricatorActionView())
        ->setName(pht('Create Snapshot'))
        ->setHref($this->getApplicationURI(
          "snapshot/create/".$fragment->getPath()))
        ->setDisabled(false) // TODO: Policy
        ->setIcon('snapshot'));
    $actions->addAction(
      id(new PhabricatorActionView())
        ->setName(pht('Promote Snapshot to Here'))
        ->setHref($this->getApplicationURI(
          "snapshot/promote/latest/".$fragment->getPath()))
        ->setWorkflow(true)
        ->setDisabled(false) // TODO: Policy
        ->setIcon('promote'));

    $properties = id(new PHUIPropertyListView())
      ->setUser($viewer)
      ->setObject($fragment)
      ->setActionList($actions);

    if (!$fragment->isDirectory()) {
      if ($fragment->isDeleted()) {
        $properties->addProperty(
          pht('Type'),
          pht('File (Deleted)'));
      } else {
        $properties->addProperty(
          pht('Type'),
          pht('File'));
      }
      $properties->addProperty(
        pht('Latest Version'),
        $this->renderHandlesForPHIDs(array($fragment->getLatestVersionPHID())));
    } else {
      $properties->addProperty(
        pht('Type'),
        pht('Directory'));
    }

    if (count($snapshot_phids) > 0) {
      $properties->addProperty(
        pht('Snapshots'),
        $this->renderHandlesForPHIDs($snapshot_phids));
    }

    return id(new PHUIObjectBoxView())
      ->setHeader($header)
      ->addPropertyList($properties);
  }

}
