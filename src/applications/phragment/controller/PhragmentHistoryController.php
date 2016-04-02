<?php

final class PhragmentHistoryController extends PhragmentController {

  public function shouldAllowPublic() {
    return true;
  }

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();
    $dblob = $request->getURIData('dblob');

    $parents = $this->loadParentFragments($dblob);
    if ($parents === null) {
      return new Aphront404Response();
    }
    $current = idx($parents, count($parents) - 1, null);

    $path = $current->getPath();

    $crumbs = $this->buildApplicationCrumbsWithPath($parents);
    if ($this->hasApplicationCapability(
      PhragmentCanCreateCapability::CAPABILITY)) {
      $crumbs->addAction(
        id(new PHUIListItemView())
          ->setName(pht('Create Fragment'))
          ->setHref($this->getApplicationURI('/create/'.$path))
          ->setIcon('fa-plus-square'));
    }

    $current_box = $this->createCurrentFragmentView($current, true);

    $versions = id(new PhragmentFragmentVersionQuery())
      ->setViewer($viewer)
      ->withFragmentPHIDs(array($current->getPHID()))
      ->execute();

    $list = id(new PHUIObjectItemListView())
      ->setUser($viewer);

    $file_phids = mpull($versions, 'getFilePHID');
    $files = id(new PhabricatorFileQuery())
      ->setViewer($viewer)
      ->withPHIDs($file_phids)
      ->execute();
    $files = mpull($files, null, 'getPHID');

    $can_edit = PhabricatorPolicyFilter::hasCapability(
      $viewer,
      $current,
      PhabricatorPolicyCapability::CAN_EDIT);

    $first = true;
    foreach ($versions as $version) {
      $item = id(new PHUIObjectItemView());
      $item->setHeader(pht('Version %s', $version->getSequence()));
      $item->setHref($version->getURI());
      $item->addAttribute(phabricator_datetime(
        $version->getDateCreated(),
        $viewer));

      if ($version->getFilePHID() === null) {
        $item->setDisabled(true);
        $item->addAttribute(pht('Deletion'));
      }

      if (!$first && $can_edit) {
        $item->addAction(id(new PHUIListItemView())
          ->setIcon('fa-refresh')
          ->setRenderNameAsTooltip(true)
          ->setWorkflow(true)
          ->setName(pht('Revert to Here'))
          ->setHref($this->getApplicationURI(
            'revert/'.$version->getID().'/'.$current->getPath())));
      }

      $disabled = !isset($files[$version->getFilePHID()]);
      $action = id(new PHUIListItemView())
        ->setIcon('fa-download')
        ->setDisabled($disabled || !$this->isCorrectlyConfigured())
        ->setRenderNameAsTooltip(true)
        ->setName(pht('Download'));
      if (!$disabled && $this->isCorrectlyConfigured()) {
        $action->setHref($files[$version->getFilePHID()]
          ->getDownloadURI($version->getURI()));
      }
      $item->addAction($action);

      $list->addItem($item);

      $first = false;
    }

    $title = pht('Fragment History');

    $view = array(
      $this->renderConfigurationWarningIfRequired(),
      $current_box,
      $list,
    );

    return $this->newPage()
      ->setTitle($title)
      ->setCrumbs($crumbs)
      ->appendChild($view);

  }

}
