<?php

final class PhamePostDeleteController extends PhameController {

  public function handleRequest(AphrontRequest $request) {
    $user = $request->getUser();

    $post = id(new PhamePostQuery())
      ->setViewer($user)
      ->withIDs(array($request->getURIData('id')))
      ->requireCapabilities(
        array(
          PhabricatorPolicyCapability::CAN_EDIT,
        ))
      ->executeOne();
    if (!$post) {
      return new Aphront404Response();
    }

    if ($request->isFormPost()) {
      $post->delete();
      return id(new AphrontRedirectResponse())
        ->setURI('/phame/post/');
    }

    $cancel_uri = $this->getApplicationURI('/post/view/'.$post->getID().'/');

    $dialog = id(new AphrontDialogView())
      ->setUser($user)
      ->setTitle(pht('Delete Post?'))
      ->appendChild(
        pht(
          'Really delete the post "%s"? It will be gone forever.',
          $post->getTitle()))
      ->addSubmitButton(pht('Delete'))
      ->addCancelButton($cancel_uri);

    return id(new AphrontDialogResponse())->setDialog($dialog);
  }

}
