<?php

final class PholioInlineListController extends PholioController {

  private $id;

  public function willProcessRequest(array $data) {
    $this->id = $data['id'];
  }

  public function processRequest() {
    $request = $this->getRequest();
    $user = $request->getUser();

    $image = id(new PholioImageQuery())
      ->setViewer($user)
      ->withIDs(array($this->id))
      ->executeOne();
    if (!$image) {
      return new Aphront404Response();
    }

    $inline_comments = id(new PholioTransactionComment())->loadAllWhere(
      'imageid = %d AND (transactionphid IS NOT NULL
      OR (authorphid = %s AND transactionphid IS NULL))',
      $this->id,
      $user->getPHID());

    $author_phids = mpull($inline_comments, 'getAuthorPHID');
    $authors = $this->loadViewerHandles($author_phids);

    $inlines = array();
    foreach ($inline_comments as $inline_comment) {
      $inlines[] = $inline_comment->toDictionary();
    }

    return id(new AphrontAjaxResponse())->setContent($inlines);
  }

}
