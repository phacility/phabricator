<?php

final class ReleephStatusFieldSpecification
  extends ReleephFieldSpecification {

  public function getFieldKey() {
    return 'status';
  }

  public function getName() {
    return 'Status';
  }

  public function renderValueForHeaderView() {
    return id(new ReleephRequestStatusView())
      ->setReleephRequest($this->getReleephRequest())
      ->render();
  }

}
