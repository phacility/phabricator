<?php

final class DiffusionSetupException extends AphrontUsageException {

  public function __construct($message) {
    parent::__construct(pht('Diffusion Setup Exception'), $message);
  }

}
