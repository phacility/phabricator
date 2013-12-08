<?php

final class PhragmentHistoryController extends PhragmentController {

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
    $current = idx($parents, count($parents) - 1, null);

    $path = $current->getPath();

    $crumbs = $this->buildApplicationCrumbsWithPath($parents);
    $crumbs->addAction(
      id(new PHUIListItemView())
        ->setName(pht('Create Fragment'))
        ->setHref($this->getApplicationURI('/create/'.$path))
        ->setIcon('create'));

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

    foreach ($versions as $version) {
      $item = id(new PHUIObjectItemView());
      $item->setHeader('Version '.$version->getSequence());
      $item->setHref($version->getURI());
      $item->addAttribute(phabricator_datetime(
        $version->getDateCreated(),
        $viewer));

      $disabled = !isset($files[$version->getFilePHID()]);
      $action = id(new PHUIListItemView())
        ->setIcon('download')
        ->setDisabled($disabled)
        ->setRenderNameAsTooltip(true)
        ->setName(pht("Download"));
      if (!$disabled) {
        $action->setHref($files[$version->getFilePHID()]->getBestURI());
      }
      $item->addAction($action);
      $list->addItem($item);
    }

    return $this->buildApplicationPage(
      array(
        $crumbs,
        $current_box,
        $list),
      array(
        'title' => pht('Fragment History'),
        'device' => true));
  }

}
