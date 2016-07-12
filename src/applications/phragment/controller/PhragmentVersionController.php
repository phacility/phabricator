<?php

final class PhragmentVersionController extends PhragmentController {

  public function shouldAllowPublic() {
    return true;
  }

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();
    $id = $request->getURIData('id');

    $version = id(new PhragmentFragmentVersionQuery())
      ->setViewer($viewer)
      ->withIDs(array($id))
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
    $crumbs->addTextCrumb(pht('View Version %d', $version->getSequence()));

    $file = id(new PhabricatorFileQuery())
      ->setViewer($viewer)
      ->withPHIDs(array($version->getFilePHID()))
      ->executeOne();
    if ($file !== null) {
      $file_uri = $file->getDownloadURI();
    }

    $header = id(new PHUIHeaderView())
      ->setHeader(pht(
        '%s at version %d',
        $version->getFragment()->getName(),
        $version->getSequence()))
      ->setPolicyObject($version)
      ->setUser($viewer);

    $actions = id(new PhabricatorActionListView())
      ->setUser($viewer)
      ->setObject($version);
    $actions->addAction(
      id(new PhabricatorActionView())
        ->setName(pht('Download Version'))
        ->setDisabled($file === null || !$this->isCorrectlyConfigured())
        ->setHref($this->isCorrectlyConfigured() ? $file_uri : null)
        ->setIcon('fa-download'));

    $properties = id(new PHUIPropertyListView())
      ->setUser($viewer)
      ->setObject($version)
      ->setActionList($actions);
    $properties->addProperty(
      pht('File'),
      $viewer->renderHandle($version->getFilePHID()));

    $box = id(new PHUIObjectBoxView())
      ->setHeader($header)
      ->addPropertyList($properties);

    $title = pht('View Version');

    $view = array(
      $this->renderConfigurationWarningIfRequired(),
      $box,
      $this->renderPreviousVersionList($version),
    );

    return $this->newPage()
      ->setTitle($title)
      ->setCrumbs($crumbs)
      ->appendChild($view);
  }

  private function renderPreviousVersionList(
    PhragmentFragmentVersion $version) {
    $viewer = $this->getViewer();

    $previous_versions = id(new PhragmentFragmentVersionQuery())
      ->setViewer($viewer)
      ->withFragmentPHIDs(array($version->getFragmentPHID()))
      ->withSequenceBefore($version->getSequence())
      ->execute();

    $list = id(new PHUIObjectItemListView())
      ->setUser($viewer);

    foreach ($previous_versions as $previous_version) {
      $item = id(new PHUIObjectItemView());
      $item->setHeader(pht('Version %s', $previous_version->getSequence()));
      $item->setHref($previous_version->getURI());
      $item->addAttribute(phabricator_datetime(
        $previous_version->getDateCreated(),
        $viewer));
      $patch_uri = $this->getApplicationURI(
        'patch/'.$previous_version->getID().'/'.$version->getID());
      $item->addAction(id(new PHUIListItemView())
        ->setIcon('fa-file-o')
        ->setName(pht('Get Patch'))
        ->setHref($this->isCorrectlyConfigured() ? $patch_uri : null)
        ->setDisabled(!$this->isCorrectlyConfigured()));
      $list->addItem($item);
    }

    $item = id(new PHUIObjectItemView());
    $item->setHeader(pht('Prior to Version 0'));
    $item->addAttribute(pht('Prior to any content (empty file)'));
    $item->addAction(id(new PHUIListItemView())
      ->setIcon('fa-file-o')
      ->setName(pht('Get Patch'))
      ->setHref($this->getApplicationURI(
        'patch/x/'.$version->getID())));
    $list->addItem($item);

    return $list;
  }

}
