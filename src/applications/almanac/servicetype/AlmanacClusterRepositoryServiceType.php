<?php

final class AlmanacClusterRepositoryServiceType
  extends AlmanacClusterServiceType {

  const SERVICETYPE = 'cluster.repository';

  public function getServiceTypeShortName() {
    return pht('Cluster Repository');
  }

  public function getServiceTypeName() {
    return pht('Phabricator Cluster: Repository');
  }

  public function getServiceTypeDescription() {
    return pht(
      'Defines a repository service for use in a Phabricator cluster.');
  }

  public function getFieldSpecifications() {
    return array(
      'closed' => id(new PhabricatorTextEditField()),
    );
  }

}
