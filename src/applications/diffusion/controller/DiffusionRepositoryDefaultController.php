<?php

final class DiffusionRepositoryDefaultController extends DiffusionController {

  protected function processDiffusionRequest(AphrontRequest $request) {
    // NOTE: This controller is just here to make sure we call
    // willBeginExecution() on any /diffusion/X/ URI, so we can intercept
    // `git`, `hg` and `svn` HTTP protocol requests.

    // If we made it here, it's probably because the user copy-pasted a
    // clone URI with "/anything.git" at the end into their web browser.
    // Send them to the canonical repository URI.

    return id(new AphrontRedirectResponse())
      ->setURI($this->getDiffusionRequest()->getRepository()->getURI());
  }
}
