<?php

final class PhragmentBrowseController extends PhragmentController {

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
    $current = nonempty(last($parents), null);

    $path = '';
    if ($current !== null) {
      $path = $current->getPath();
    }

    $crumbs = $this->buildApplicationCrumbsWithPath($parents);
    if ($this->hasApplicationCapability(
      PhragmentCanCreateCapability::CAPABILITY)) {
      $crumbs->addAction(
        id(new PHUIListItemView())
          ->setName(pht('Create Fragment'))
          ->setHref($this->getApplicationURI('/create/'.$path))
          ->setIcon('fa-plus-square'));
    }

    $current_box = $this->createCurrentFragmentView($current, false);

    $list = id(new PHUIObjectItemListView())
      ->setUser($viewer);

    $fragments = null;
    if ($current === null) {
      // Find all root fragments.
      $fragments = id(new PhragmentFragmentQuery())
        ->setViewer($this->getRequest()->getUser())
        ->needLatestVersion(true)
        ->withDepths(array(1))
        ->execute();
    } else {
      // Find all child fragments.
      $fragments = id(new PhragmentFragmentQuery())
        ->setViewer($this->getRequest()->getUser())
        ->needLatestVersion(true)
        ->withLeadingPath($current->getPath().'/')
        ->withDepths(array($current->getDepth() + 1))
        ->execute();
    }

    foreach ($fragments as $fragment) {
      $item = id(new PHUIObjectItemView());
      $item->setHeader($fragment->getName());
      $item->setHref($fragment->getURI());
      if (!$fragment->isDirectory()) {
        $item->addAttribute(pht(
          'Last Updated %s',
          phabricator_datetime(
            $fragment->getLatestVersion()->getDateCreated(),
            $viewer)));
        $item->addAttribute(pht(
          'Latest Version %s',
          $fragment->getLatestVersion()->getSequence()));
        if ($fragment->isDeleted()) {
          $item->setDisabled(true);
          $item->addAttribute(pht('Deleted'));
        }
      } else {
        $item->addAttribute(pht('Directory'));
      }
      $list->addItem($item);
    }

    $title = pht('Browse Fragments');

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
