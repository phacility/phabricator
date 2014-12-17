<?php

abstract class AlmanacClusterServiceType
  extends AlmanacServiceType {

  public function isClusterServiceType() {
    return true;
  }

  public function getServiceTypeIcon() {
    return 'fa-sitemap';
  }

}
