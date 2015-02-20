<?php

final class AlmanacClusterDatabaseServiceType
  extends AlmanacClusterServiceType {

  public function getServiceTypeShortName() {
    return pht('Cluster Database');
  }

  public function getServiceTypeName() {
    return pht('Phabricator Cluster: Database');
  }

  public function getServiceTypeDescription() {
    return pht(
      'Defines a database service for use in a Phabricator cluster.');
  }

  public function getFieldSpecifications() {
    return array();
  }

}
