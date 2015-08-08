<?php

abstract class HeraldGroup extends Phobject {

  abstract public function getGroupLabel();

  protected function getGroupOrder() {
    return 1000;
  }

  public function getSortKey() {
    return sprintf('A%08d%s', $this->getGroupOrder(), $this->getGroupLabel());
  }

}
