<?php

/**
 * @group pholio
 */
final class PholioInlineSaveController extends PholioController {

  private $operation;

  public function getOperation() {
    return $this->operation;
  }

  public function processRequest() {
    $request = $this->getRequest();
    $user = $request->getUser();

    $mock = id(new PholioMockQuery())
      ->setViewer($user)
      ->withIDs(array($request->getInt('mockID')))
      ->executeOne();

    if (!$mock) {
      return new Aphront404Response();
    }

    $this->operation = $request->getStr('op');

    if ($this->getOperation() == 'save') {
      $new_content = $request->getStr('text');

      if (!strlen($new_content)) {
        throw new Exception("Content must not be empty.");
      }

      $draft = id(new PholioTransactionComment());
      $draft->setImageID($request->getInt('imageID'));
      $draft->setX($request->getInt('startX'));
      $draft->setY($request->getInt('startY'));

      $draft->setCommentVersion(1);
      $draft->setAuthorPHID($user->getPHID());
      $draft->setEditPolicy($user->getPHID());
      $draft->setViewPolicy(PhabricatorPolicies::POLICY_PUBLIC);

      $content_source = PhabricatorContentSource::newForSource(
        PhabricatorContentSource::SOURCE_WEB,
        array(
          'ip' => $request->getRemoteAddr(),
        ));

      $draft->setContentSource($content_source);

      $draft->setWidth($request->getInt('endX') - $request->getInt('startX'));
      $draft->setHeight($request->getInt('endY') - $request->getInt('startY'));

      $draft->setContent($new_content);

      $draft->save();

      $handle = head($this->loadViewerHandles(array($user->getPHID())));

      $inline_view = id(new PholioInlineCommentView())
        ->setInlineComment($draft)
        ->setEngine(new PhabricatorMarkupEngine())
        ->setUser($user)
        ->setHandle($handle);

      return id(new AphrontAjaxResponse())
        ->setContent(
          $draft->toDictionary() + array(
            'contentHTML' => $inline_view->render(),
          ));
    } else {
      $dialog = new PholioInlineCommentSaveView();

      $dialog->setUser($user);
      $dialog->setSubmitURI($request->getRequestURI());

      $dialog->setTitle(pht('Add Inline Comment'));

      $dialog->addHiddenInput('op', 'save');

      $dialog->appendChild($this->renderTextArea(''));

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
