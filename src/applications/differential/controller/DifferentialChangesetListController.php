<?php

final class DifferentialChangesetListController
  extends DifferentialController {

  private $diff;

  public function shouldAllowPublic() {
    return true;
  }

  public function handleRequest(AphrontRequest $request) {
    $viewer = $this->getViewer();

    $diff = id(new DifferentialDiffQuery())
      ->setViewer($viewer)
      ->withIDs(array($request->getURIData('id')))
      ->executeOne();
    if (!$diff) {
      return new Aphront404Response();
    }
    $this->diff = $diff;

    return id(new DifferentialChangesetSearchEngine())
      ->setController($this)
      ->setDiff($diff)
      ->buildResponse();
  }

  protected function buildApplicationCrumbs() {
    $crumbs = parent::buildApplicationCrumbs();

    $diff = $this->diff;
    if ($diff) {
      $revision = $diff->getRevision();
      if ($revision) {
        $crumbs->addTextCrumb(
          $revision->getMonogram(),
          $revision->getURI());
      }

      $crumbs->addTextCrumb(
        pht('Diff %d', $diff->getID()),
        $diff->getURI());
    }

    return $crumbs;
  }


}
