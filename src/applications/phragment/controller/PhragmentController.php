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
    $crumbs->addTextCrumb('/', '/phragment/');
    foreach ($fragments as $parent) {
      $crumbs->addTextCrumb(
        $parent->getName(),
        '/phragment/browse/'.$parent->getPath());
    }
    return $crumbs;
  }

  protected function createCurrentFragmentView($fragment, $is_history_view) {
    if ($fragment === null) {
      return null;
    }

    $viewer = $this->getRequest()->getUser();

    $snapshot_phids = array();
    $snapshots = id(new PhragmentSnapshotQuery())
      ->setViewer($viewer)
      ->withPrimaryFragmentPHIDs(array($fragment->getPHID()))
      ->execute();
    foreach ($snapshots as $snapshot) {
      $snapshot_phids[] = $snapshot->getPHID();
    }

    $file = null;
    $file_uri = null;
    if (!$fragment->isDirectory()) {
      $file = id(new PhabricatorFileQuery())
        ->setViewer($viewer)
        ->withPHIDs(array($fragment->getLatestVersion()->getFilePHID()))
        ->executeOne();
      if ($file !== null) {
        $file_uri = $file->getDownloadURI();
      }
    }

    $header = id(new PHUIHeaderView())
      ->setHeader($fragment->getName())
      ->setPolicyObject($fragment)
      ->setUser($viewer);

    $can_edit = PhabricatorPolicyFilter::hasCapability(
      $viewer,
      $fragment,
      PhabricatorPolicyCapability::CAN_EDIT);

    $zip_uri = $this->getApplicationURI('zip/'.$fragment->getPath());

    $actions = id(new PhabricatorActionListView())
      ->setUser($viewer)
      ->setObject($fragment)
      ->setObjectURI($fragment->getURI());
    $actions->addAction(
      id(new PhabricatorActionView())
        ->setName(pht('Download Fragment'))
        ->setHref($this->isCorrectlyConfigured() ? $file_uri : null)
        ->setDisabled($file === null || !$this->isCorrectlyConfigured())
        ->setIcon('fa-download'));
    $actions->addAction(
      id(new PhabricatorActionView())
        ->setName(pht('Download Contents as ZIP'))
        ->setHref($this->isCorrectlyConfigured() ? $zip_uri : null)
        ->setDisabled(!$this->isCorrectlyConfigured())
        ->setIcon('fa-floppy-o'));
    if (!$fragment->isDirectory()) {
      $actions->addAction(
        id(new PhabricatorActionView())
          ->setName(pht('Update Fragment'))
          ->setHref($this->getApplicationURI('update/'.$fragment->getPath()))
          ->setDisabled(!$can_edit)
          ->setWorkflow(!$can_edit)
          ->setIcon('fa-refresh'));
    } else {
      $actions->addAction(
        id(new PhabricatorActionView())
          ->setName(pht('Convert to File'))
          ->setHref($this->getApplicationURI('update/'.$fragment->getPath()))
          ->setDisabled(!$can_edit)
          ->setWorkflow(!$can_edit)
          ->setIcon('fa-file-o'));
    }
    $actions->addAction(
      id(new PhabricatorActionView())
        ->setName(pht('Set Fragment Policies'))
        ->setHref($this->getApplicationURI('policy/'.$fragment->getPath()))
        ->setDisabled(!$can_edit)
        ->setWorkflow(!$can_edit)
        ->setIcon('fa-asterisk'));
    if ($is_history_view) {
      $actions->addAction(
        id(new PhabricatorActionView())
          ->setName(pht('View Child Fragments'))
          ->setHref($this->getApplicationURI('browse/'.$fragment->getPath()))
          ->setIcon('fa-search-plus'));
    } else {
      $actions->addAction(
        id(new PhabricatorActionView())
          ->setName(pht('View History'))
          ->setHref($this->getApplicationURI('history/'.$fragment->getPath()))
          ->setIcon('fa-list'));
    }
    $actions->addAction(
      id(new PhabricatorActionView())
        ->setName(pht('Create Snapshot'))
        ->setHref($this->getApplicationURI(
          'snapshot/create/'.$fragment->getPath()))
        ->setDisabled(!$can_edit)
        ->setWorkflow(!$can_edit)
        ->setIcon('fa-files-o'));
    $actions->addAction(
      id(new PhabricatorActionView())
        ->setName(pht('Promote Snapshot to Here'))
        ->setHref($this->getApplicationURI(
          'snapshot/promote/latest/'.$fragment->getPath()))
        ->setWorkflow(true)
        ->setDisabled(!$can_edit)
        ->setIcon('fa-arrow-circle-up'));

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
        $viewer->renderHandle($fragment->getLatestVersionPHID()));
    } else {
      $properties->addProperty(
        pht('Type'),
        pht('Directory'));
    }

    if (count($snapshot_phids) > 0) {
      $properties->addProperty(
        pht('Snapshots'),
        $viewer->renderHandleList($snapshot_phids));
    }

    return id(new PHUIObjectBoxView())
      ->setHeader($header)
      ->addPropertyList($properties);
  }

  public function renderConfigurationWarningIfRequired() {
    $alt = PhabricatorEnv::getEnvConfig('security.alternate-file-domain');
    if ($alt === null) {
      return id(new PHUIInfoView())
        ->setTitle(pht('security.alternate-file-domain must be configured!'))
        ->setSeverity(PHUIInfoView::SEVERITY_ERROR)
        ->appendChild(phutil_tag('p', array(), pht(
          'Because Phragment generates files (such as ZIP archives and '.
          'patches) as they are requested, it requires that you configure '.
          'the `security.alternate-file-domain` option. This option on it\'s '.
          'own will also provide additional security when serving files '.
          'across Phabricator.')));
    }
    return null;
  }

  /**
   * We use this to disable the download links if the alternate domain is
   * not configured correctly. Although the download links will mostly work
   * for logged in users without an alternate domain, the behaviour is
   * reasonably non-consistent and will deny public users, even if policies
   * are configured otherwise (because the Files app does not support showing
   * the info page to viewers who are not logged in).
   */
  public function isCorrectlyConfigured() {
    $alt = PhabricatorEnv::getEnvConfig('security.alternate-file-domain');
    return $alt !== null;
  }

}
