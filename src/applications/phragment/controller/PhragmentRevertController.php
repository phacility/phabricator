<?php

final class PhragmentRevertController extends PhragmentController {

  private $dblob;
  private $id;

  public function willProcessRequest(array $data) {
    $this->dblob = $data['dblob'];
    $this->id = $data['id'];
  }

  public function processRequest() {
    $request = $this->getRequest();
    $viewer = $request->getUser();

    $fragment = id(new PhragmentFragmentQuery())
      ->setViewer($viewer)
      ->withPaths(array($this->dblob))
      ->requireCapabilities(
        array(
          PhabricatorPolicyCapability::CAN_VIEW,
          PhabricatorPolicyCapability::CAN_EDIT,
        ))
      ->executeOne();
    if ($fragment === null) {
      return new Aphront404Response();
    }

    $version = id(new PhragmentFragmentVersionQuery())
      ->setViewer($viewer)
      ->withFragmentPHIDs(array($fragment->getPHID()))
      ->withIDs(array($this->id))
      ->executeOne();
    if ($version === null) {
      return new Aphront404Response();
    }

    if ($request->isDialogFormPost()) {
      $file_phid = $version->getFilePHID();

      $file = null;
      if ($file_phid !== null) {
        $file = id(new PhabricatorFileQuery())
          ->setViewer($viewer)
          ->withPHIDs(array($file_phid))
          ->executeOne();
        if ($file === null) {
          throw new Exception(
            pht('The file associated with this version was not found.'));
        }
      }

      if ($file === null) {
        $fragment->deleteFile($viewer);
      } else {
        $fragment->updateFromFile($viewer, $file);
      }

      return id(new AphrontRedirectResponse())
        ->setURI($this->getApplicationURI('/history/'.$this->dblob));
    }

    return $this->createDialog($fragment, $version);
  }

  public function createDialog(
    PhragmentFragment $fragment,
    PhragmentFragmentVersion $version) {

    $request = $this->getRequest();
    $viewer = $request->getUser();

    $dialog = id(new AphrontDialogView())
      ->setTitle(pht('Really revert this fragment?'))
      ->setUser($request->getUser())
      ->addSubmitButton(pht('Revert'))
      ->addCancelButton(pht('Cancel'))
      ->appendParagraph(pht(
        'Reverting this fragment to version %d will create a new version of '.
        'the fragment. It will not delete any version history.',
        $version->getSequence(),
        $version->getSequence()));
    return id(new AphrontDialogResponse())->setDialog($dialog);
  }

}
