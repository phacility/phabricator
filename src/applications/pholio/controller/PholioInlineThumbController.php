<?php

final class PholioInlineThumbController extends PholioController {

  private $imageid;

  public function shouldAllowPublic() {
    return true;
  }

  public function willProcessRequest(array $data) {
    $this->imageid = idx($data, 'imageid');
  }

  public function processRequest() {
    $request = $this->getRequest();
    $user = $request->getUser();

    $image = id(new PholioImage())->load($this->imageid);

    if ($image == null) {
      return new Aphront404Response();
    }

    $mock = id(new PholioMockQuery())
      ->setViewer($user)
      ->withIDs(array($image->getMockID()))
      ->executeOne();

    if (!$mock) {
      return new Aphront404Response();
    }

    $file = id(new PhabricatorFileQuery())
      ->setViewer($user)
      ->witHPHIDs(array($image->getFilePHID()))
      ->executeOne();

    if (!$file) {
      return new Aphront404Response();
    }

    return id(new AphrontRedirectResponse())->setURI($file->getThumb60x45URI());
  }

}
