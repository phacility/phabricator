<?php

final class NuanceItemManageController extends NuanceController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $this->getViewer();
    $id = $request->getURIData('id');

    $item = id(new NuanceItemQuery())
      ->setViewer($viewer)
      ->withIDs(array($id))
      ->executeOne();
    if (!$item) {
      return new Aphront404Response();
    }

    $title = pht('Item %d', $item->getID());

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addTextCrumb(
      pht('Items'),
      $this->getApplicationURI('item/'));
    $crumbs->addTextCrumb(
      $title,
      $item->getURI());
    $crumbs->addTextCrumb(pht('Manage'));
    $crumbs->setBorder(true);

    $properties = $this->buildPropertyView($item);
    $curtain = $this->buildCurtain($item);

    $header = id(new PHUIHeaderView())
      ->setHeader($title);

    $timeline = $this->buildTransactionTimeline(
      $item,
      new NuanceItemTransactionQuery());
    $timeline->setShouldTerminate(true);

    $view = id(new PHUITwoColumnView())
      ->setHeader($header)
      ->setCurtain($curtain)
      ->addPropertySection(pht('Details'), $properties)
      ->setMainColumn($timeline);

    return $this->newPage()
      ->setTitle($title)
      ->setCrumbs($crumbs)
      ->appendChild($view);
  }

  private function buildPropertyView(NuanceItem $item) {
    $viewer = $this->getViewer();

    $properties = id(new PHUIPropertyListView())
      ->setUser($viewer);

    $properties->addProperty(
      pht('Date Created'),
      phabricator_datetime($item->getDateCreated(), $viewer));

    $requestor_phid = $item->getRequestorPHID();
    if ($requestor_phid) {
      $requestor_view = $viewer->renderHandle($requestor_phid);
    } else {
      $requestor_view = phutil_tag('em', array(), pht('None'));
    }
    $properties->addProperty(pht('Requestor'), $requestor_view);

    $properties->addProperty(
      pht('Source'),
      $viewer->renderHandle($item->getSourcePHID()));

    $queue_phid = $item->getQueuePHID();
    if ($queue_phid) {
      $queue_view = $viewer->renderHandle($queue_phid);
    } else {
      $queue_view = phutil_tag('em', array(), pht('None'));
    }
    $properties->addProperty(pht('Queue'), $queue_view);

    $source = $item->getSource();
    $definition = $source->getDefinition();

    $definition->renderItemEditProperties(
      $viewer,
      $item,
      $properties);

    return $properties;
  }

  private function buildCurtain(NuanceItem $item) {
    $viewer = $this->getViewer();
    $id = $item->getID();

    $curtain = $this->newCurtainView($item);

    $curtain->addAction(
      id(new PhabricatorActionView())
        ->setName(pht('View Item'))
        ->setIcon('fa-eye')
        ->setHref($item->getURI()));

    return $curtain;
  }


}
