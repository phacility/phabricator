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
      'imageid = %d AND transactionphid IS NOT NULL',
      $this->id
    );

    $inlines = array();
    foreach ($inline_comments as $inline_comment) {
      $inlines[] = array(
        'phid' => $inline_comment->getPHID(),
        'imageID' => $inline_comment->getImageID(),
        'x' => $inline_comment->getX(),
        'y' => $inline_comment->getY(),
        'width' => $inline_comment->getWidth(),
        'height' => $inline_comment->getHeight(),
        'content' => $inline_comment->getContent());
    }

    return id(new AphrontAjaxResponse())->setContent($inlines);
  }

}
