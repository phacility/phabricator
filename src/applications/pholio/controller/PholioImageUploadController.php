<?php

final class PholioImageUploadController extends PholioController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();

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

    if (!phutil_nonempty_string($title)) {
      $title = $file->getName();
    }

    $image = PholioImage::initializeNewImage()
      ->setAuthorPHID($viewer->getPHID())
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
