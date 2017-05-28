<?php

final class PhabricatorBadgesAwardTestDataGenerator
  extends PhabricatorTestDataGenerator {

  const GENERATORKEY = 'badges.award';

  public function getGeneratorName() {
    return pht('Badges Award');
  }

  public function generateObject() {
    $author = $this->loadRandomUser();
    $recipient = $this->loadRandomUser();
    $badge_phid = $this->loadRandomPHID(new PhabricatorBadgesBadge());

    $xactions = array();

    $xactions[] = array(
      'type' => 'award',
      'value' => array($recipient->getPHID()),
    );

    $params = array(
      'transactions' => $xactions,
      'objectIdentifier' => $badge_phid,
    );

    $result = id(new ConduitCall('badge.edit', $params))
      ->setUser($author)
      ->execute();

    return $result['object']['phid'];
  }

}
