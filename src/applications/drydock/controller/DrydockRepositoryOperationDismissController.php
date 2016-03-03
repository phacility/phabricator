<?php

final class DrydockRepositoryOperationDismissController
  extends DrydockRepositoryOperationController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();
    $id = $request->getURIData('id');

    $operation = id(new DrydockRepositoryOperationQuery())
      ->setViewer($viewer)
      ->withIDs(array($id))
      ->requireCapabilities(
        array(
          PhabricatorPolicyCapability::CAN_VIEW,
          PhabricatorPolicyCapability::CAN_EDIT,
        ))
      ->executeOne();
    if (!$operation) {
      return new Aphront404Response();
    }

    $object_phid = $operation->getObjectPHID();
    $handles = $viewer->loadHandles(array($object_phid));
    $done_uri = $handles[$object_phid]->getURI();

    if ($operation->getIsDismissed()) {
      return $this->newDialog()
        ->setTitle(pht('Already Dismissed'))
        ->appendParagraph(
          pht(
            'This operation has already been dismissed, and can not be '.
            'dismissed any further.'))
        ->addCancelButton($done_uri);
    }


    if ($request->isFormPost()) {
      $operation
        ->setIsDismissed(1)
        ->save();

      return id(new AphrontRedirectResponse())->setURI($done_uri);
    }

    return $this->newDialog()
      ->setTitle(pht('Dismiss Operation'))
      ->appendParagraph(
        pht(
          'Dismiss this operation? It will no longer be shown, but logs '.
          'can be found in Drydock.'))
      ->addSubmitButton(pht('Dismiss'))
      ->addCancelButton($done_uri);
  }

}
