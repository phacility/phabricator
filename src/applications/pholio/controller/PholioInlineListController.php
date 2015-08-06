<?php

final class PholioInlineListController extends PholioController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();
    $id = $request->getURIData('id');

    $image = id(new PholioImageQuery())
      ->setViewer($viewer)
      ->withIDs(array($id))
      ->executeOne();
    if (!$image) {
      return new Aphront404Response();
    }

    $inline_comments = id(new PholioTransactionComment())->loadAllWhere(
      'imageid = %d AND (transactionphid IS NOT NULL
      OR (authorphid = %s AND transactionphid IS NULL))',
      $id,
      $viewer->getPHID());

    $author_phids = mpull($inline_comments, 'getAuthorPHID');
    $authors = $this->loadViewerHandles($author_phids);

    $inlines = array();
    foreach ($inline_comments as $inline_comment) {
      $inlines[] = $inline_comment->toDictionary();
    }

    return id(new AphrontAjaxResponse())->setContent($inlines);
  }

}
