<?php

final class HarbormasterUnitMessageViewController
  extends HarbormasterController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $this->getViewer();

    $message_id = $request->getURIData('id');

    $message = id(new HarbormasterBuildUnitMessage())->load($message_id);
    if (!$message) {
      return new Aphront404Response();
    }

    $build_target = id(new HarbormasterBuildTargetQuery())
      ->setViewer($viewer)
      ->withPHIDs(array($message->getBuildTargetPHID()))
      ->executeOne();
    if (!$build_target) {
      return new Aphront404Response();
    }

    $build = $build_target->getBuild();
    $buildable = $build->getBuildable();
    $buildable_id = $buildable->getID();

    $id = $message->getID();
    $display_name = $message->getUnitMessageDisplayName();

    $status = $message->getResult();
    $status_icon = HarbormasterUnitStatus::getUnitStatusIcon($status);
    $status_color = HarbormasterUnitStatus::getUnitStatusColor($status);
    $status_label = HarbormasterUnitStatus::getUnitStatusLabel($status);

    $header = id(new PHUIHeaderView())
      ->setHeader($display_name)
      ->setStatus($status_icon, $status_color, $status_label);

    $properties = $this->buildPropertyListView($message);
    $curtain = $this->buildCurtainView($message, $build);

    $unit = id(new PHUIObjectBoxView())
      ->setHeaderText(pht('TEST RESULT'))
      ->setBackground(PHUIObjectBoxView::BLUE_PROPERTY)
      ->addPropertyList($properties);

    $crumbs = $this->buildApplicationCrumbs();
    $this->addBuildableCrumb($crumbs, $buildable);

    $crumbs->addTextCrumb(
      pht('Unit Tests'),
      "/harbormaster/unit/{$buildable_id}/");

    $crumbs->addTextCrumb(pht('Unit %d', $id));
    $crumbs->setBorder(true);

    $title = array(
      $display_name,
      $buildable->getMonogram(),
    );

    $view = id(new PHUITwoColumnView())
      ->setHeader($header)
      ->setCurtain($curtain)
      ->setMainColumn(array(
        $unit,
      ));

    return $this->newPage()
      ->setTitle($title)
      ->setCrumbs($crumbs)
      ->appendChild($view);
  }

  private function buildPropertyListView(
    HarbormasterBuildUnitMessage $message) {
    $viewer = $this->getViewer();

    $view = id(new PHUIPropertyListView())
      ->setUser($viewer);

    $view->addProperty(
      pht('Run At'),
      phabricator_datetime($message->getDateCreated(), $viewer));

    $details = $message->getUnitMessageDetails();
    if (strlen($details)) {
      // TODO: Use the log view here, once it gets cleaned up.
      // Shenanigans below.
      $details = phutil_tag(
        'div',
        array(
          'class' => 'PhabricatorMonospaced',
          'style' =>
            'white-space: pre-wrap; '.
            'color: #666666; '.
            'overflow-x: auto;',
        ),
        $details);
    } else {
      $details = phutil_tag('em', array(), pht('No details provided.'));
    }

    $view->addSectionHeader(
      pht('Details'),
      PHUIPropertyListView::ICON_TESTPLAN);
    $view->addTextContent($details);

    return $view;
  }

  private function buildCurtainView(
    HarbormasterBuildUnitMessage $message,
    HarbormasterBuild $build) {
    $viewer = $this->getViewer();

    $curtain = $this->newCurtainView($build);

    $curtain->addAction(
      id(new PhabricatorActionView())
        ->setName(pht('View Build'))
        ->setHref($build->getURI())
        ->setIcon('fa-wrench'));

    return $curtain;
  }
}
