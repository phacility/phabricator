<?php

final class AlmanacClusterDatabaseServiceType
  extends AlmanacClusterServiceType {

  const SERVICETYPE = 'cluster.database';

  public function getServiceTypeShortName() {
    return pht('Cluster Database');
  }

  public function getServiceTypeName() {
    return pht('Cluster: Database');
  }

  public function getServiceTypeDescription() {
    return pht(
      'Defines a database service for use in a cluster.');
  }

}
