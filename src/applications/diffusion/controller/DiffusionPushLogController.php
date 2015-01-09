<?php

abstract class DiffusionPushLogController extends DiffusionController {

  protected function shouldLoadDiffusionRequest() {
    return false;
  }

}
