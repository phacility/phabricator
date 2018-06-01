<?php

final class HarbormasterBuildLogViewController
  extends HarbormasterController {

  public function shouldAllowPublic() {
    return true;
  }

  public function handleRequest(AphrontRequest $request) {
    $viewer = $this->getViewer();

    $id = $request->getURIData('id');

    $log = id(new HarbormasterBuildLogQuery())
      ->setViewer($viewer)
      ->withIDs(array($id))
      ->executeOne();
    if (!$log) {
      return new Aphront404Response();
    }

    $target = $log->getBuildTarget();
    $build = $target->getBuild();

    $page_title = pht('Build Log %d', $log->getID());

    $log_view = id(new HarbormasterBuildLogView())
      ->setViewer($viewer)
      ->setBuildLog($log)
      ->setHighlightedLineRange($request->getURIData('lines'))
      ->setEnableHighlighter(true);

    $crumbs = $this->buildApplicationCrumbs()
      ->addTextCrumb(pht('Build Logs'))
      ->addTextCrumb(
        pht('Build %d', $build->getID()),
        $build->getURI())
      ->addTextCrumb($page_title)
      ->setBorder(true);

    $page_header = id(new PHUIHeaderView())
      ->setHeader($page_title);

    $page_view = id(new PHUITwoColumnView())
      ->setHeader($page_header)
      ->setFooter($log_view);

    return $this->newPage()
      ->setTitle($page_title)
      ->setCrumbs($crumbs)
      ->appendChild($page_view);
  }

}
