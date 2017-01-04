<?php

final class DiffusionRepositoryDefaultController extends DiffusionController {

  public function shouldAllowPublic() {
    // NOTE: We allow public access to this controller because it handles
    // redirecting paths that are missing a trailing "/". We need to manually
    // redirect these instead of relying on the automatic redirect because
    // some VCS requests may omit the slashes. See T12035, and below, for some
    // discussion.
    return true;
  }

  public function handleRequest(AphrontRequest $request) {
    $response = $this->loadDiffusionContext();
    if ($response) {
      return $response;
    }

    // NOTE: This controller is just here to make sure we call
    // willBeginExecution() on any /diffusion/X/ URI, so we can intercept
    // `git`, `hg` and `svn` HTTP protocol requests.

    // If we made it here, it's probably because the user copy-pasted a
    // clone URI with "/anything.git" at the end into their web browser.
    // Send them to the canonical repository URI.

    $drequest = $this->getDiffusionRequest();
    $repository = $drequest->getRepository();

    return id(new AphrontRedirectResponse())
      ->setURI($repository->getURI());
  }
}
