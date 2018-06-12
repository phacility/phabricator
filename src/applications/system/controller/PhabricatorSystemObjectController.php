<?php

final class PhabricatorSystemObjectController
  extends PhabricatorController {

  public function shouldAllowPublic() {
    return true;
  }

  public function handleRequest(AphrontRequest $request) {
    $viewer = $this->getViewer();
    $name = $request->getURIData('name');

    $object = id(new PhabricatorObjectQuery())
      ->setViewer($viewer)
      ->withNames(array($name))
      ->executeOne();
    if (!$object) {
      return new Aphront404Response();
    }

    $phid = $object->getPHID();
    $handles = $viewer->loadHandles(array($phid));
    $handle = $handles[$phid];

    $object_uri = $handle->getURI();
    if (!strlen($object_uri)) {
      return $this->newDialog()
        ->setTitle(pht('No Object URI'))
        ->appendParagraph(
          pht(
            'Object "%s" exists, but does not have a URI to redirect to.',
            $name))
        ->addCancelButton('/', pht('Done'));
    }

    return id(new AphrontRedirectResponse())->setURI($object_uri);
  }
}
