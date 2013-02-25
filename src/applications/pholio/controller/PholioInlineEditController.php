<?php

/**
 * @group pholio
 */
final class PholioInlineEditController extends PholioController {

  private $id;
  private $operation;

  public function getOperation() {
    return $this->operation;
  }

  public function willProcessRequest(array $data) {
    $this->id = $data['id'];
  }

  public function processRequest() {
    $request = $this->getRequest();
    $user = $request->getUser();

    $this->operation = $request->getBool('op');

    $inline_comment = id(new PholioTransactionComment())->loadOneWhere(
      'id = %d AND authorphid = %s AND transactionphid IS NULL',
      $this->id,
      $user->getPHID());

    if ($inline_comment == null) {
      return new Aphront404Response();
    }

    switch ($this->getOperation()) {
      case 'update':
        $new_content = $request->getStr('content');

        if (strlen(trim($new_content)) == 0) {
          return id(new AphrontAjaxResponse())
            ->setContent(array('success' => false))
            ->setError(pht('Empty comment'));
        }

        $inline_comment->setContent($request->getStr('content'));
        $inline_comment->save();

        return id(new AphrontAjaxResponse())
          ->setContent(array('success' => true));

      default:
        $dialog = new PholioInlineCommentEditView();
        $dialog->setInlineComment($inline_comment);

        $dialog->setUser($user);
        $dialog->setSubmitURI($request->getRequestURI());

        $dialog->setTitle(pht('Edit inline comment'));

        $dialog->addHiddenInput('id', $this->id);
        $dialog->addHiddenInput('op', 'edit');

        $dialog->appendChild(
          $this->renderTextArea($inline_comment->getContent()));

        return id(new AphrontAjaxResponse())->setContent($dialog->render());
    }

  }

  private function renderTextArea($text) {
    return javelin_tag(
      'textarea',
      array(
        'class' => 'pholio-inline-comment-dialog-textarea',
        'name' => 'text',
      ),
      $text);
  }



}
