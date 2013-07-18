<?php

/**
 * @group pholio
 */
final class PholioImageUploadController extends PholioController {

  public function processRequest() {
    $request = $this->getRequest();
    $viewer = $request->getUser();

    $phid = $request->getStr('filePHID');
    $file = id(new PhabricatorFileQuery())
      ->setViewer($viewer)
      ->withPHIDs(array($phid))
      ->executeOne();
    if (!$file) {
      return new Aphront404Response();
    }

    $image = id(new PholioImage())
      ->attachFile($file)
      ->setName($file->getName())
      ->makeEphemeral();

    $view = id(new PholioUploadedImageView())
      ->setUser($viewer)
      ->setImage($image);

    $content = array(
      'markup' => $view,
    );

    return id(new AphrontAjaxResponse())->setContent($content);
  }

}
