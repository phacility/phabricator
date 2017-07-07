<?php

final class PhamePostArchiveController extends PhamePostController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();

    $id = $request->getURIData('id');
    $post = id(new PhamePostQuery())
      ->setViewer($viewer)
      ->withIDs(array($id))
      ->requireCapabilities(
        array(
          PhabricatorPolicyCapability::CAN_VIEW,
          PhabricatorPolicyCapability::CAN_EDIT,
        ))
      ->executeOne();
    if (!$post) {
      return new Aphront404Response();
    }

    $cancel_uri = $post->getViewURI();

    if ($request->isFormPost()) {
      $xactions = array();

      $new_value = PhameConstants::VISIBILITY_ARCHIVED;
      $xactions[] = id(new PhamePostTransaction())
        ->setTransactionType(PhamePostVisibilityTransaction::TRANSACTIONTYPE)
        ->setNewValue($new_value);

      id(new PhamePostEditor())
        ->setActor($viewer)
        ->setContentSourceFromRequest($request)
        ->setContinueOnNoEffect(true)
        ->setContinueOnMissingFields(true)
        ->applyTransactions($post, $xactions);

      return id(new AphrontRedirectResponse())
        ->setURI($cancel_uri);
    }

    $title = pht('Archive Post');
    $body = pht(
      'If you archive this post, it will only be visible to users who can '.
      'edit %s.',
      $viewer->renderHandle($post->getBlogPHID()));
    $button = pht('Archive Post');

    return $this->newDialog()
      ->setTitle($title)
      ->appendParagraph($body)
      ->addSubmitButton($button)
      ->addCancelButton($cancel_uri);
  }

}
