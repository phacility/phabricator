<?php

abstract class DiffusionLogController extends DiffusionController {

  protected function shouldLoadDiffusionRequest() {
    return false;
  }

}
