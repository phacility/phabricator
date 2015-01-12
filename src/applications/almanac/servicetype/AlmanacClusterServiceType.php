<?php

abstract class AlmanacClusterServiceType
  extends AlmanacServiceType {

  public function isClusterServiceType() {
    return true;
  }

  public function getServiceTypeIcon() {
    return 'fa-sitemap';
  }

  public function getStatusMessages(AlmanacService $service) {
    $messages = parent::getStatusMessages($service);

    if (!$service->getIsLocked()) {
      $doc_href = PhabricatorEnv::getDoclink(
        'User Guide: Phabricator Clusters');

      $doc_link = phutil_tag(
        'a',
        array(
          'href' => $doc_href,
          'target' => '_blank',
        ),
        pht('Learn More'));

      $messages[] = pht(
        'This is an unlocked cluster service. After you finish editing '.
        'it, you should lock it. %s.',
        $doc_link);
    }

    return $messages;
  }

}
