<?php

final class PhamePostUnpublishController extends PhameController {

  public function handleRequest(AphrontRequest $request) {
    $user = $request->getUser();
    $id = $request->getURIData('id');

    $post = id(new PhamePostQuery())
      ->setViewer($user)
      ->withIDs(array($id))
      ->requireCapabilities(
        array(
          PhabricatorPolicyCapability::CAN_EDIT,
        ))
      ->executeOne();
    if (!$post) {
      return new Aphront404Response();
    }

    if ($request->isFormPost()) {
      $post->setVisibility(PhamePost::VISIBILITY_DRAFT);
      $post->setDatePublished(0);
      $post->save();

      return id(new AphrontRedirectResponse())
        ->setURI($this->getApplicationURI('/post/view/'.$post->getID().'/'));
    }

    $cancel_uri = $this->getApplicationURI('/post/view/'.$post->getID().'/');

    $dialog = id(new AphrontDialogView())
      ->setUser($user)
      ->setTitle(pht('Unpublish Post?'))
      ->appendChild(
        pht(
          'The post "%s" will no longer be visible to other users until you '.
          'republish it.',
          $post->getTitle()))
      ->addSubmitButton(pht('Unpublish'))
      ->addCancelButton($cancel_uri);

    return id(new AphrontDialogResponse())->setDialog($dialog);
  }

}
