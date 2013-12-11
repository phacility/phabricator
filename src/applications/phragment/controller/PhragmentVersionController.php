<?php

final class PhragmentVersionController extends PhragmentController {

  private $id;

  public function willProcessRequest(array $data) {
    $this->id = idx($data, "id", 0);
  }

  public function processRequest() {
    $request = $this->getRequest();
    $viewer = $request->getUser();

    $version = id(new PhragmentFragmentVersionQuery())
      ->setViewer($viewer)
      ->withIDs(array($this->id))
      ->executeOne();
    if ($version === null) {
      return new Aphront404Response();
    }

    $parents = $this->loadParentFragments($version->getFragment()->getPath());
    if ($parents === null) {
      return new Aphront404Response();
    }
    $current = idx($parents, count($parents) - 1, null);

    $crumbs = $this->buildApplicationCrumbsWithPath($parents);
    $crumbs->addCrumb(
      id(new PhabricatorCrumbView())
        ->setName(pht('View Version %d', $version->getSequence())));

    $phids = array();
    $phids[] = $version->getFilePHID();

    $this->loadHandles($phids);

    $file = id(new PhabricatorFileQuery())
      ->setViewer($viewer)
      ->withPHIDs(array($version->getFilePHID()))
      ->executeOne();
    if ($file !== null) {
      $file_uri = $file->getBestURI();
    }

    $header = id(new PHUIHeaderView())
      ->setHeader(pht(
        "%s at version %d",
        $version->getFragment()->getName(),
        $version->getSequence()))
      ->setPolicyObject($version)
      ->setUser($viewer);

    $actions = id(new PhabricatorActionListView())
      ->setUser($viewer)
      ->setObject($version)
      ->setObjectURI($version->getURI());
    $actions->addAction(
      id(new PhabricatorActionView())
        ->setName(pht('Download Version'))
        ->setHref($file_uri)
        ->setDisabled($file === null)
        ->setIcon('download'));

    $properties = id(new PHUIPropertyListView())
      ->setUser($viewer)
      ->setObject($version)
      ->setActionList($actions);
    $properties->addProperty(
      pht('File'),
      $this->renderHandlesForPHIDs(array($version->getFilePHID())));

    $box = id(new PHUIObjectBoxView())
      ->setHeader($header)
      ->addPropertyList($properties);

    return $this->buildApplicationPage(
      array(
        $crumbs,
        $box,
        $this->renderPatchFromPreviousVersion($version, $file),
        $this->renderPreviousVersionList($version)),
      array(
        'title' => pht('View Version'),
        'device' => true));
  }

  private function renderPatchFromPreviousVersion(
    PhragmentFragmentVersion $version,
    PhabricatorFile $file) {

    $request = $this->getRequest();
    $viewer = $request->getUser();

    $previous_file = null;
    $previous = id(new PhragmentFragmentVersionQuery())
      ->setViewer($viewer)
      ->withFragmentPHIDs(array($version->getFragmentPHID()))
      ->withSequences(array($version->getSequence() - 1))
      ->executeOne();
    if ($previous !== null) {
      $previous_file = id(new PhabricatorFileQuery())
        ->setViewer($viewer)
        ->withPHIDs(array($previous->getFilePHID()))
        ->executeOne();
    }

    $patch = PhragmentPatchUtil::calculatePatch($previous_file, $file);

    if ($patch === null) {
      return id(new AphrontErrorView())
        ->setSeverity(AphrontErrorView::SEVERITY_NOTICE)
        ->setTitle(pht("Identical Version"))
        ->appendChild(phutil_tag(
          'p',
          array(),
          pht("This version is identical to the previous version.")));
    }

    if (strlen($patch) > 20480) {
      // Patch is longer than 20480 characters.  Trim it and let the user know.
      $patch = substr($patch, 0, 20480)."\n...\n";
      $patch .= pht(
        "This patch is longer than 20480 characters.  Use the link ".
        "in the action list to download the full patch.");
    }

    return id(new PHUIObjectBoxView())
      ->setHeader(id(new PHUIHeaderView())
        ->setHeader(pht('Differences since previous version')))
      ->appendChild(id(new PhabricatorSourceCodeView())
        ->setLines(phutil_split_lines($patch)));
  }

  private function renderPreviousVersionList(
    PhragmentFragmentVersion $version) {

    $request = $this->getRequest();
    $viewer = $request->getUser();

    $previous_versions = id(new PhragmentFragmentVersionQuery())
      ->setViewer($viewer)
      ->withFragmentPHIDs(array($version->getFragmentPHID()))
      ->withSequenceBefore($version->getSequence())
      ->execute();

    $list = id(new PHUIObjectItemListView())
      ->setUser($viewer);

    foreach ($previous_versions as $previous_version) {
      $item = id(new PHUIObjectItemView());
      $item->setHeader('Version '.$previous_version->getSequence());
      $item->setHref($previous_version->getURI());
      $item->addAttribute(phabricator_datetime(
        $previous_version->getDateCreated(),
        $viewer));
      $item->addAction(id(new PHUIListItemView())
        ->setIcon('patch')
        ->setName(pht("Get Patch"))
        ->setHref($this->getApplicationURI(
          'patch/'.$previous_version->getID().'/'.$version->getID())));
      $list->addItem($item);
    }

    $item = id(new PHUIObjectItemView());
    $item->setHeader('Prior to Version 0');
    $item->addAttribute('Prior to any content (empty file)');
    $item->addAction(id(new PHUIListItemView())
      ->setIcon('patch')
      ->setName(pht("Get Patch"))
      ->setHref($this->getApplicationURI(
        'patch/x/'.$version->getID())));
    $list->addItem($item);

    return $list;
  }

}
