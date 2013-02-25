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
    $authors = id(new PhabricatorObjectHandleData($author_phids))
      ->loadHandles();

    $inlines = array();

    foreach ($inline_comments as $inline_comment) {
      $inline_view = id(new PholioInlineCommentView())
        ->setHandle($authors[$inline_comment->getAuthorPHID()])
        ->setInlineComment($inline_comment);

     if ($inline_comment->getEditPolicy(PhabricatorPolicyCapability::CAN_EDIT)
        == $user->getPHID() && $inline_comment->getTransactionPHID() === null) {
          $inline_view->setEditable(true);
      }

      $inlines[] = $inline_comment->toDictionary() + array(
        'contentHTML' => $inline_view->render(),
      );
    }

    return id(new AphrontAjaxResponse())->setContent($inlines);
  }

}
