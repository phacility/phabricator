<?php

final class PhameBlogDeleteController extends PhameController {

  public function handleRequest(AphrontRequest $request) {
    $user = $request->getUser();
    $id = $request->getURIData('id');

    $blog = id(new PhameBlogQuery())
      ->setViewer($user)
      ->withIDs(array($id))
      ->requireCapabilities(
        array(
          PhabricatorPolicyCapability::CAN_EDIT,
        ))
      ->executeOne();
    if (!$blog) {
      return new Aphront404Response();
    }

    if ($request->isFormPost()) {
      $blog->delete();
      return id(new AphrontRedirectResponse())
        ->setURI($this->getApplicationURI());
    }

    $cancel_uri = $this->getApplicationURI('/blog/view/'.$blog->getID().'/');

    $dialog = id(new AphrontDialogView())
      ->setUser($user)
      ->setTitle(pht('Delete Blog?'))
      ->appendChild(
        pht(
          'Really delete the blog "%s"? It will be gone forever.',
          $blog->getName()))
      ->addSubmitButton(pht('Delete'))
      ->addCancelButton($cancel_uri);

    return id(new AphrontDialogResponse())->setDialog($dialog);
  }

}
