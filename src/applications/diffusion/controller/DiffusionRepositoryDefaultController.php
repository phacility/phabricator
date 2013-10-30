<?php

final class DiffusionRepositoryDefaultController extends DiffusionController {

  public function processRequest() {
    // NOTE: This controller is just here to make sure we call
    // willBeginExecution() on any /diffusion/X/ URI, so we can intercept
    // `git`, `hg` and `svn` HTTP protocol requests.
    return new Aphront404Response();
  }
}
