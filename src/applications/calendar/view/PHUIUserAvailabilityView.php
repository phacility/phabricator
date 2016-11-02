<?php

final class PHUIUserAvailabilityView
  extends AphrontTagView {

  private $user;

  public function setAvailableUser(PhabricatorUser $user) {
    $this->user = $user;
    return $this;
  }

  public function getAvailableUser() {
    return $this->user;
  }

  protected function getTagContent() {
    $viewer = $this->getViewer();
    $user = $this->getAvailableUser();

    $until = $user->getAwayUntil();
    if (!$until) {
      return pht('Available');
    }

    $away_tag = id(new PHUITagView())
      ->setType(PHUITagView::TYPE_SHADE)
      ->setShade(PHUITagView::COLOR_RED)
      ->setName(pht('Away'))
      ->setDotColor(PHUITagView::COLOR_RED);

    $now = PhabricatorTime::getNow();
    $description = pht(
      'Away until %s',
      $viewer->formatShortDateTime($until, $now));

    return array(
      $away_tag,
      ' ',
      $description,
    );
  }

}
