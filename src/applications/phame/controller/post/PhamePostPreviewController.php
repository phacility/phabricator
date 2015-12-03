<?php

final class PhamePostPreviewController extends PhamePostController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();
    $id = $request->getURIData('id');

    $post = id(new PhamePostQuery())
      ->setViewer($viewer)
      ->withIDs(array($id))
      ->requireCapabilities(
        array(
          PhabricatorPolicyCapability::CAN_EDIT,
        ))
      ->executeOne();
    if (!$post) {
      return new Aphront404Response();
    }

    $view_uri = $this->getApplicationURI('/post/view/'.$post->getID().'/');

    if ($request->isFormPost()) {
      $xactions = array();
      $xactions[] = id(new PhamePostTransaction())
        ->setTransactionType(PhamePostTransaction::TYPE_VISIBILITY)
        ->setNewValue(PhameConstants::VISIBILITY_PUBLISHED);

      id(new PhamePostEditor())
        ->setActor($viewer)
        ->setContentSourceFromRequest($request)
        ->setContinueOnNoEffect(true)
        ->setContinueOnMissingFields(true)
        ->applyTransactions($post, $xactions);

      return id(new AphrontRedirectResponse())->setURI($view_uri);
    }

    $form = id(new AphrontFormView())
      ->setUser($viewer)
      ->appendChild(
        id(new AphrontFormSubmitControl())
          ->setValue(pht('Publish Post'))
          ->addCancelButton($view_uri));

    $frame = $this->renderPreviewFrame($post);

    $form_box = id(new PHUIObjectBoxView())
      ->setHeaderText(pht('Preview Post'))
      ->setForm($form);

    $blog = $post->getBlog();

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addTextCrumb(
        $blog->getName(),
        $this->getApplicationURI('blog/view/'.$blog->getID().'/'));
    $crumbs->addTextCrumb(pht('Preview Post'), $view_uri);

    return $this->newPage()
      ->setTitle(pht('Preview Post'))
      ->setCrumbs($crumbs)
      ->appendChild(
        array(
          $form_box,
          $frame,
      ));
  }

  private function renderPreviewFrame(PhamePost $post) {

    return phutil_tag(
      'div',
      array(
        'style' => 'text-align: center; padding: 16px;',
      ),
      phutil_tag(
        'iframe',
        array(
          'style' => 'width: 100%; height: 800px; '.
                     'border: 1px solid #BFCFDA; '.
                     'background-color: #fff; '.
                     'border-radius: 3px; ',
          'src' => $this->getApplicationURI('/post/framed/'.$post->getID().'/'),
        ),
        ''));
  }

}
