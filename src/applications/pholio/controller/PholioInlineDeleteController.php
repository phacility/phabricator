<?php

/**
 * @group pholio
 */
final class PholioInlineDeleteController extends PholioController {

  private $id;

  public function willProcessRequest(array $data) {
    $this->id = $data['id'];
  }

  public function processRequest() {
    $request = $this->getRequest();
    $user = $request->getUser();

    $inline_comment = id(new PholioTransactionComment())->loadOneWhere(
      'id = %d AND authorphid = %s AND transactionphid IS NULL',
      $this->id,
      $user->getPHID());

    if ($inline_comment == null) {
      return new Aphront404Response();
    }  else {

      $inline_comment->delete();
      return id(new AphrontAjaxResponse())
        ->setContent(array('success' => true));
    }

  }

}
