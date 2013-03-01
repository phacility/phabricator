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
    $handle = head($this->loadViewerHandles(
                array($inline_comment->getAuthorPHID())));

    $inline_view = id(new PholioInlineCommentView())
      ->setUser($user)
      ->setHandle($handle)
      ->setInlineComment($inline_comment)
      ->setEngine(new PhabricatorMarkupEngine());

    return id(new AphrontAjaxResponse())->setContent(
      $inline_comment->toDictionary() + array(
        'contentHTML' => $inline_view->render(),
      ));
  }

}
