<?php

final class PhamePostMoveController extends PhamePostController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();
    $id = $request->getURIData('id');

    $post = id(new PhamePostQuery())
      ->setViewer($viewer)
      ->withIDs(array($id))
      ->requireCapabilities(
        array(
          PhabricatorPolicyCapability::CAN_EDIT,
          PhabricatorPolicyCapability::CAN_VIEW,
        ))
      ->executeOne();

    if (!$post) {
      return new Aphront404Response();
    }

    $view_uri = $post->getViewURI();
    $v_blog = $post->getBlog()->getPHID();

    if ($request->isFormPost()) {
      $v_blog = $request->getStr('blogPHID');

      $xactions = array();
      $xactions[] = id(new PhamePostTransaction())
        ->setTransactionType(PhamePostTransaction::TYPE_BLOG)
        ->setNewValue($v_blog);

      $editor = id(new PhamePostEditor())
        ->setActor($viewer)
        ->setContentSourceFromRequest($request)
        ->setContinueOnMissingFields(true)
        ->setContinueOnNoEffect(true);

      $editor->applyTransactions($post, $xactions);

      $view_uri = $post->getViewURI();

      return id(new AphrontRedirectResponse())
        ->setURI($view_uri.'?moved=1');
    }

    $blogs = id(new PhameBlogQuery())
      ->setViewer($viewer)
      ->requireCapabilities(
        array(
          PhabricatorPolicyCapability::CAN_VIEW,
          PhabricatorPolicyCapability::CAN_EDIT,
        ))
      ->execute();

    $options = mpull($blogs, 'getName', 'getPHID');
    asort($options);

    $form = id(new PHUIFormLayoutView())
      ->setUser($viewer)
      ->appendChild(
        id(new AphrontFormSelectControl())
          ->setLabel(pht('Blog'))
          ->setName('blogPHID')
          ->setOptions($options)
          ->setValue($v_blog));

    return $this->newDialog()
      ->setTitle(pht('Move Post'))
      ->appendChild($form)
      ->addSubmitButton(pht('Move Post'))
      ->addCancelButton($view_uri);
    }

}
