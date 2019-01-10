<?php

final class ManiphestTaskMFAEngine
  extends PhabricatorEditEngineMFAEngine {

  public function shouldRequireMFA() {
    $status = $this->getObject()->getStatus();
    return ManiphestTaskStatus::isMFAStatus($status);
  }

}
