<?php

/**
 * @group pholio
 */
final class PholioInlineController extends PholioController {

  private $id;

  public function willProcessRequest(array $data) {
    $this->id = $data['id'];
  }

  public function processRequest() {
    $request = $this->getRequest();
    $user = $request->getUser();

    $inline_comments = id(new PholioTransactionComment())->loadAllWhere(
      'imageid = %d AND (transactionphid IS NOT NULL
      OR (authorphid = %s AND transactionphid IS NULL))',
      $this->id,
      $user->getPHID());

    $author_phids = mpull($inline_comments, 'getAuthorPHID');
    $authors = $this->loadViewerHandles($author_phids);

    $inlines = array();

    $engine = new PhabricatorMarkupEngine();

    foreach ($inline_comments as $inline_comment) {
      $inline_view = id(new PholioInlineCommentView())
        ->setUser($user)
        ->setHandle($authors[$inline_comment->getAuthorPHID()])
        ->setInlineComment($inline_comment)
        ->setEngine($engine);

      $inlines[] = $inline_comment->toDictionary() + array(
        'contentHTML' => $inline_view->render(),
      );
    }

    return id(new AphrontAjaxResponse())->setContent($inlines);
  }

}
