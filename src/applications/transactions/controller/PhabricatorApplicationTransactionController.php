<?php

abstract class PhabricatorApplicationTransactionController
  extends PhabricatorController {

  protected function guessCancelURI(
    PhabricatorUser $viewer,
    PhabricatorApplicationTransaction $xaction) {

    // Take an educated guess at the URI where the transactions appear so we
    // can send the cancel button somewhere sensible. This won't always get the
    // best answer (for example, Diffusion's history is visible on a page other
    // than the main object view page) but should always get a reasonable one.

    $cancel_uri = '/';
    $handle = id(new PhabricatorHandleQuery())
      ->setViewer($viewer)
      ->withPHIDs(array($xaction->getObjectPHID()))
      ->executeOne();
    if ($handle) {
      $cancel_uri = $handle->getURI();
    }

    return $cancel_uri;
  }

}
