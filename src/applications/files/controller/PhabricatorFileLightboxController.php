<?php

final class PhabricatorFileLightboxController
  extends PhabricatorFileController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();
    $phid = $request->getURIData('phid');
    $comment = $request->getStr('comment');

    $file = id(new PhabricatorFileQuery())
      ->setViewer($viewer)
      ->withPHIDs(array($phid))
      ->executeOne();
    if (!$file) {
      return new Aphront404Response();
    }

    if (strlen($comment)) {
      $xactions = array();
      $xactions[] = id(new PhabricatorFileTransaction())
        ->setTransactionType(PhabricatorTransactions::TYPE_COMMENT)
        ->attachComment(
          id(new PhabricatorFileTransactionComment())
            ->setContent($comment));

      $editor = id(new PhabricatorFileEditor())
        ->setActor($viewer)
        ->setContinueOnNoEffect(true)
        ->setContentSourceFromRequest($request);

      $editor->applyTransactions($file, $xactions);
    }

    $transactions = id(new PhabricatorFileTransactionQuery())
      ->withTransactionTypes(array(PhabricatorTransactions::TYPE_COMMENT));
    $timeline = $this->buildTransactionTimeline($file, $transactions);

    $comment_form = $this->renderCommentForm($file);

    $info = phutil_tag(
      'div',
      array(
        'class' => 'phui-comment-panel-header',
      ),
      $file->getName());

    require_celerity_resource('phui-comment-panel-css');
    $content = phutil_tag(
      'div',
      array(
        'class' => 'phui-comment-panel',
      ),
      array(
        $info,
        $timeline,
        $comment_form,
      ));

    return id(new AphrontAjaxResponse())
      ->setContent($content);
  }

  private function renderCommentForm(PhabricatorFile $file) {
    $viewer = $this->getViewer();

    if (!$viewer->isLoggedIn()) {
      $login_href = id(new PhutilURI('/auth/start/'))
        ->setQueryParam('next', '/'.$file->getMonogram());
      return id(new PHUIFormLayoutView())
        ->addClass('phui-comment-panel-empty')
        ->appendChild(
          id(new PHUIButtonView())
          ->setTag('a')
          ->setText(pht('Login to Comment'))
          ->setHref((string)$login_href));
    }

    $draft = PhabricatorDraft::newFromUserAndKey(
      $viewer,
      $file->getPHID());
    $post_uri = $this->getApplicationURI('thread/'.$file->getPHID().'/');

    $form = id(new AphrontFormView())
      ->setUser($viewer)
      ->setAction($post_uri)
      ->addSigil('lightbox-comment-form')
      ->addClass('lightbox-comment-form')
      ->setWorkflow(true)
      ->appendChild(
        id(new PhabricatorRemarkupControl())
        ->setUser($viewer)
        ->setName('comment')
        ->setValue($draft->getDraft()))
      ->appendChild(
        id(new AphrontFormSubmitControl())
          ->setValue(pht('Comment')));

    $view = phutil_tag_div('phui-comment-panel', $form);

    return $view;

  }

}
