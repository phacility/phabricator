<?php

final class PhamePostPublishController extends PhamePostController {

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

    $action = $request->getURIData('action');
    $is_publish = ($action == 'publish');

    if ($request->isFormPost()) {
      $xactions = array();

      if ($is_publish) {
        $new_value = PhameConstants::VISIBILITY_PUBLISHED;
      } else {
        $new_value = PhameConstants::VISIBILITY_DRAFT;
      }

      $xactions[] = id(new PhamePostTransaction())
        ->setTransactionType(PhamePostTransaction::TYPE_VISIBILITY)
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

    if ($is_publish) {
      $title = pht('Publish Post');
      $body = pht('This post will go live once you publish it.');
      $button = pht('Publish');
    } else {
      $title = pht('Unpublish Post');
      $body = pht(
        'This post will revert to draft status and no longer be visible '.
        'to other users.');
      $button = pht('Unpublish');
    }

    return $this->newDialog()
      ->setTitle($title)
      ->appendParagraph($body)
      ->addSubmitButton($button)
      ->addCancelButton($cancel_uri);
  }

}
