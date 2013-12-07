<?php

final class PhragmentBrowseController extends PhragmentController {

  private $dblob;

  public function willProcessRequest(array $data) {
    $this->dblob = idx($data, "dblob", "");
  }

  public function processRequest() {
    $request = $this->getRequest();
    $viewer = $request->getUser();

    $parents = $this->loadParentFragments($this->dblob);
    if ($parents === null) {
      return new Aphront404Response();
    }
    $current = nonempty(last($parents), null);

    $path = '';
    if ($current !== null) {
      $path = $current->getPath();
    }

    $crumbs = $this->buildApplicationCrumbsWithPath($parents);
    $crumbs->addAction(
      id(new PHUIListItemView())
        ->setName(pht('Create Fragment'))
        ->setHref($this->getApplicationURI('/create/'.$path))
        ->setIcon('create'));

    $current_box = $this->createCurrentFragmentView($current);

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
      $item->setHref($this->getApplicationURI('/browse/'.$fragment->getPath()));
      $item->addAttribute(pht(
        'Last Updated %s',
        phabricator_datetime(
          $fragment->getLatestVersion()->getDateCreated(),
          $viewer)));
      $item->addAttribute(pht(
        'Latest Version %s',
        $fragment->getLatestVersion()->getSequence()));
      $list->addItem($item);
    }

    return $this->buildApplicationPage(
      array(
        $crumbs,
        $current_box,
        $list),
      array(
        'title' => pht('Browse Phragments'),
        'device' => true));
  }

  private function createCurrentFragmentView($fragment) {
    if ($fragment === null) {
      return null;
    }

    $viewer = $this->getRequest()->getUser();

    $header = id(new PHUIHeaderView())
      ->setHeader($fragment->getName())
      ->setPolicyObject($fragment)
      ->setUser($viewer);
    $properties = new PHUIPropertyListView();

    $phids = array();
    $phids[] = $fragment->getLatestVersionPHID();

    $this->loadHandles($phids);

    $properties->addProperty(
      pht('Latest Version'),
      $this->renderHandlesForPHIDs(array($fragment->getLatestVersionPHID())));

    return id(new PHUIObjectBoxView())
      ->setHeader($header)
      ->addPropertyList($properties);
  }

}
