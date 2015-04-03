<?php

final class PhragmentSnapshotViewController extends PhragmentController {

  private $id;

  public function shouldAllowPublic() {
    return true;
  }

  public function willProcessRequest(array $data) {
    $this->id = idx($data, 'id', '');
  }

  public function processRequest() {
    $request = $this->getRequest();
    $viewer = $request->getUser();

    $snapshot = id(new PhragmentSnapshotQuery())
      ->setViewer($viewer)
      ->withIDs(array($this->id))
      ->executeOne();
    if ($snapshot === null) {
      return new Aphront404Response();
    }

    $box = $this->createSnapshotView($snapshot);

    $fragment = id(new PhragmentFragmentQuery())
      ->setViewer($viewer)
      ->withPHIDs(array($snapshot->getPrimaryFragmentPHID()))
      ->executeOne();
    if ($fragment === null) {
      return new Aphront404Response();
    }

    $parents = $this->loadParentFragments($fragment->getPath());
    if ($parents === null) {
      return new Aphront404Response();
    }

    $crumbs = $this->buildApplicationCrumbsWithPath($parents);
    $crumbs->addTextCrumb(pht('"%s" Snapshot', $snapshot->getName()));

    $children = id(new PhragmentSnapshotChildQuery())
      ->setViewer($viewer)
      ->needFragments(true)
      ->needFragmentVersions(true)
      ->withSnapshotPHIDs(array($snapshot->getPHID()))
      ->execute();

    $list = id(new PHUIObjectItemListView())
      ->setUser($viewer);

    foreach ($children as $child) {
      $item = id(new PHUIObjectItemView())
        ->setHeader($child->getFragment()->getPath());

      if ($child->getFragmentVersion() !== null) {
        $item
          ->setHref($child->getFragmentVersion()->getURI())
          ->addAttribute(pht(
            'Version %s',
            $child->getFragmentVersion()->getSequence()));
      } else {
        $item
          ->setHref($child->getFragment()->getURI())
          ->addAttribute(pht('Directory'));
      }

      $list->addItem($item);
    }

    return $this->buildApplicationPage(
      array(
        $crumbs,
        $this->renderConfigurationWarningIfRequired(),
        $box,
        $list,
      ),
      array(
        'title' => pht('View Snapshot'),
      ));
  }

  protected function createSnapshotView($snapshot) {
    if ($snapshot === null) {
      return null;
    }

    $viewer = $this->getRequest()->getUser();

    $header = id(new PHUIHeaderView())
      ->setHeader(pht('"%s" Snapshot', $snapshot->getName()))
      ->setPolicyObject($snapshot)
      ->setUser($viewer);

    $zip_uri = $this->getApplicationURI(
      'zip@'.$snapshot->getName().
      '/'.$snapshot->getPrimaryFragment()->getPath());

    $can_edit = PhabricatorPolicyFilter::hasCapability(
      $viewer,
      $snapshot,
      PhabricatorPolicyCapability::CAN_EDIT);

    $actions = id(new PhabricatorActionListView())
      ->setUser($viewer)
      ->setObject($snapshot)
      ->setObjectURI($snapshot->getURI());
    $actions->addAction(
      id(new PhabricatorActionView())
        ->setName(pht('Download Snapshot as ZIP'))
        ->setHref($this->isCorrectlyConfigured() ? $zip_uri : null)
        ->setDisabled(!$this->isCorrectlyConfigured())
        ->setIcon('fa-floppy-o'));
    $actions->addAction(
      id(new PhabricatorActionView())
        ->setName(pht('Delete Snapshot'))
        ->setHref($this->getApplicationURI(
          'snapshot/delete/'.$snapshot->getID().'/'))
        ->setDisabled(!$can_edit)
        ->setWorkflow(true)
        ->setIcon('fa-times'));
    $actions->addAction(
      id(new PhabricatorActionView())
        ->setName(pht('Promote Another Snapshot to Here'))
        ->setHref($this->getApplicationURI(
          'snapshot/promote/'.$snapshot->getID().'/'))
        ->setDisabled(!$can_edit)
        ->setWorkflow(true)
        ->setIcon('fa-arrow-up'));

    $properties = id(new PHUIPropertyListView())
      ->setUser($viewer)
      ->setObject($snapshot)
      ->setActionList($actions);

    $properties->addProperty(
      pht('Name'),
      $snapshot->getName());
    $properties->addProperty(
      pht('Fragment'),
      $viewer->renderHandle($snapshot->getPrimaryFragmentPHID()));

    return id(new PHUIObjectBoxView())
      ->setHeader($header)
      ->addPropertyList($properties);
  }
}
