<?php

final class DiffusionSetupException extends AphrontUsageException {

  public function __construct($message) {
    parent::__construct('Diffusion Setup Exception', $message);
  }

}
