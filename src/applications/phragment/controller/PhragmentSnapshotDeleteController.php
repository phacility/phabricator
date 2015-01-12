<?php

final class PhragmentSnapshotDeleteController extends PhragmentController {

  private $id;

  public function willProcessRequest(array $data) {
    $this->id = $data['id'];
  }

  public function processRequest() {
    $request = $this->getRequest();
    $viewer = $request->getUser();

    $snapshot = id(new PhragmentSnapshotQuery())
      ->setViewer($viewer)
      ->requireCapabilities(array(
        PhabricatorPolicyCapability::CAN_VIEW,
        PhabricatorPolicyCapability::CAN_EDIT,
      ))
      ->withIDs(array($this->id))
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
    $request = $this->getRequest();
    $viewer = $request->getUser();

    $dialog = id(new AphrontDialogView())
      ->setTitle(pht('Really delete this snapshot?'))
      ->setUser($request->getUser())
      ->addSubmitButton(pht('Delete'))
      ->addCancelButton(pht('Cancel'))
      ->appendParagraph(pht(
        'Deleting this snapshot is a permanent operation. You can not '.
        'recover the state of the snapshot.'));
    return id(new AphrontDialogResponse())->setDialog($dialog);
  }

}
