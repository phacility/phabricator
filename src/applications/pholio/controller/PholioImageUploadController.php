<?php

final class PholioImageUploadController extends PholioController {

  public function processRequest() {
    $request = $this->getRequest();
    $viewer = $request->getUser();

    $phid = $request->getStr('filePHID');
    $replaces_phid = $request->getStr('replacesPHID');
    $title = $request->getStr('title');
    $description = $request->getStr('description');

    $file = id(new PhabricatorFileQuery())
      ->setViewer($viewer)
      ->withPHIDs(array($phid))
      ->executeOne();
    if (!$file) {
      return new Aphront404Response();
    }

    if (!strlen($title)) {
      $title = $file->getName();
    }

    $image = id(new PholioImage())
      ->attachFile($file)
      ->setName($title)
      ->setDescription($description)
      ->makeEphemeral();

    $view = id(new PholioUploadedImageView())
      ->setUser($viewer)
      ->setImage($image)
      ->setReplacesPHID($replaces_phid);

    $content = array(
      'markup' => $view,
    );

    return id(new AphrontAjaxResponse())->setContent($content);
  }

}
