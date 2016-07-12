<?php

final class PhabricatorHelpDocumentationController
  extends PhabricatorHelpController {

  public function shouldAllowPublic() {
    return true;
  }

  public function handleRequest(AphrontRequest $request) {
    $viewer = $this->getViewer();

    $application_class = $request->getURIData('application');
    $application = id(new PhabricatorApplicationQuery())
      ->setViewer($viewer)
      ->withClasses(array($application_class))
      ->executeOne();
    if (!$application) {
      return new Aphront404Response();
    }

    $items = $application->getHelpMenuItems($viewer);
    $title = pht('%s Help', $application->getName());

    $list = id(new PHUIObjectItemListView())
      ->setUser($viewer);
    foreach ($items as $item) {
      if ($item->getType() == PHUIListItemView::TYPE_LABEL) {
        continue;
      }
      $list->addItem(
        id(new PHUIObjectItemView())
          ->setHeader($item->getName())
          ->setWorkflow($item->getWorkflow())
          ->setHref($item->getHref()));
    }

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addTextCrumb($title);

    return $this->newPage()
      ->setTitle($title)
      ->setCrumbs($crumbs)
      ->appendChild($list);
  }


}
