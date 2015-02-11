<?php

final class AlmanacClusterRepositoryServiceType
  extends AlmanacClusterServiceType {

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
      'closed' => array(
        'type' => 'bool',
        'name' => pht('Closed'),
        'default' => false,
        'strings' => array(
          'edit.checkbox' => pht(
            'Prevent new repositories from being allocated on this '.
            'service.'),
        ),
      ),
    );
  }

}
