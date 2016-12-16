<?php

final class DifferentialRevisionEditController
  extends DifferentialController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $this->getViewer();

    // If we have a Diff ID, this is an "/attach/123/to/456/" action. The
    // user just created a diff and is trying to use it to create or update
    // a revision.
    $diff_id = $request->getURIData('diffID');

    if ($diff_id) {
      $diff = id(new DifferentialDiffQuery())
        ->setViewer($viewer)
        ->withIDs(array($diff_id))
        ->executeOne();
      if (!$diff) {
        return new Aphront404Response();
      }

      if ($diff->getRevisionID()) {
        $revision = $diff->getRevision();
        return $this->newDialog()
          ->setTitle(pht('Diff Already Attached'))
          ->appendParagraph(
            pht(
              'This diff is already attached to a revision.'))
          ->addCancelButton($revision->getURI(), pht('Continue'));
      }
    } else {
      $diff = null;
    }

    $revision_id = $request->getURIData('id');
    if (!$diff && !$revision_id) {
      return $this->newDialog()
        ->setTitle(pht('Diff Required'))
        ->appendParagraph(
          pht(
            'You can not create a revision without a diff.'))
        ->addCancelButton($this->getApplicationURI());
    }

    $engine = id(new DifferentialRevisionEditEngine())
      ->setController($this);

    if ($diff) {
      $engine->setDiff($diff);
    }

    return $engine->buildResponse();
  }

}
