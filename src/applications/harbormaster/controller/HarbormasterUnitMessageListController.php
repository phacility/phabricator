<?php

final class HarbormasterUnitMessageListController
  extends HarbormasterController {

  public function shouldAllowPublic() {
    return true;
  }

  public function handleRequest(AphrontRequest $request) {
    $viewer = $this->getViewer();

    $buildable = id(new HarbormasterBuildableQuery())
      ->setViewer($viewer)
      ->withIDs(array($request->getURIData('id')))
      ->needBuilds(true)
      ->needTargets(true)
      ->executeOne();
    if (!$buildable) {
      return new Aphront404Response();
    }

    $id = $buildable->getID();

    $target_phids = array();
    foreach ($buildable->getBuilds() as $build) {
      foreach ($build->getBuildTargets() as $target) {
        $target_phids[] = $target->getPHID();
      }
    }

    $unit_data = array();
    if ($target_phids) {
      $unit_data = id(new HarbormasterBuildUnitMessageQuery())
        ->setViewer($viewer)
        ->withBuildTargetPHIDs($target_phids)
        ->execute();
    } else {
      $unit_data = array();
    }

    $unit = id(new HarbormasterUnitSummaryView())
      ->setViewer($viewer)
      ->setBuildable($buildable)
      ->setUnitMessages($unit_data);

    $crumbs = $this->buildApplicationCrumbs();
    $this->addBuildableCrumb($crumbs, $buildable);
    $crumbs->addTextCrumb(pht('Unit Tests'));
    $crumbs->setBorder(true);

    $title = array(
      $buildable->getMonogram(),
      pht('Unit Tests'),
    );

    $header = id(new PHUIHeaderView())
      ->setHeader($buildable->getMonogram().' '.pht('Unit Tests'));

    $view = id(new PHUITwoColumnView())
      ->setHeader($header)
      ->setFooter(array(
        $unit,
      ));

    return $this->newPage()
      ->setTitle($title)
      ->setCrumbs($crumbs)
      ->appendChild($view);

  }

}
