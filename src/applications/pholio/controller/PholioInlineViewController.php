<?php

/**
 * @group pholio
 */
final class PholioInlineViewController extends PholioController {

  private $id;

  public function willProcessRequest(array $data) {
    $this->id = $data['id'];
  }

  public function processRequest() {
    $request = $this->getRequest();
    $user = $request->getUser();

    $inline_comment = id(new PholioTransactionComment())->load($this->id);
    $handle = PhabricatorObjectHandleData::loadOneHandle(
      $inline_comment->getAuthorPHID());

    $inline_view = id(new PholioInlineCommentView())
      ->setHandle($handle)
      ->setInlineComment($inline_comment);

    if ($inline_comment->getEditPolicy(PhabricatorPolicyCapability::CAN_EDIT)
      == $user->getPHID() && $inline_comment->getTransactionPHID() === null) {
      $inline_view->setEditable(true);
    }

    return id(new AphrontAjaxResponse())->setContent(
      array(
        'id' => $inline_comment->getID(),
        'phid' => $inline_comment->getPHID(),
        'contentHTML' => $inline_view->render()
      ));
  }

}
