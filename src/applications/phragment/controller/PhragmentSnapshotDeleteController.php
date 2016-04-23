<?php

final class PhragmentSnapshotDeleteController extends PhragmentController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();
    $id = $request->getURIData('id');

    $snapshot = id(new PhragmentSnapshotQuery())
      ->setViewer($viewer)
      ->requireCapabilities(array(
        PhabricatorPolicyCapability::CAN_VIEW,
        PhabricatorPolicyCapability::CAN_EDIT,
      ))
      ->withIDs(array($id))
      ->executeOne();
    if ($snapshot === null) {
      return new Aphront404Response();
    }

    if ($request->isDialogFormPost()) {
      $fragment_uri = $snapshot->getPrimaryFragment()->getURI();

      $snapshot->delete();

      return id(new AphrontRedirectResponse())
        ->setURI($fragment_uri);
    }

    return $this->createDialog();
  }

  public function createDialog() {
    $viewer = $this->getViewer();

    $dialog = id(new AphrontDialogView())
      ->setTitle(pht('Really delete this snapshot?'))
      ->setUser($this->getViewer())
      ->addSubmitButton(pht('Delete'))
      ->addCancelButton(pht('Cancel'))
      ->appendParagraph(pht(
        'Deleting this snapshot is a permanent operation. You can not '.
        'recover the state of the snapshot.'));
    return id(new AphrontDialogResponse())->setDialog($dialog);
  }

}
