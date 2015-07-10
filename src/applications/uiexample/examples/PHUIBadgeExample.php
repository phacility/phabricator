<?php

final class PHUIBadgeExample extends PhabricatorUIExample {

  public function getName() {
    return pht('Badge');
  }

  public function getDescription() {
    return pht('Celebrate the moments of your life.');
  }

  public function renderExample() {

    $badges1 = array();
    $badges1[] = id(new PHUIBadgeView())
      ->setIcon('fa-users')
      ->setHeader(pht('Phacility High Command'))
      ->setHref('/')
      ->setSource('Projects (automatic)')
      ->addByline(pht('Dec 31, 1969'))
      ->addByline('3 Members');

    $badges1[] = id(new PHUIBadgeView())
      ->setIcon('fa-lock')
      ->setHeader(pht('Blessed Committers'))
      ->setSource('Projects (automatic)')
      ->addByline(pht('Dec 31, 1969'))
      ->addByline('12 Members');

    $badges1[] = id(new PHUIBadgeView())
      ->setIcon('fa-camera-retro')
      ->setHeader(pht('Design'))
      ->setSource('Projects (automatic)')
      ->addByline(pht('Dec 31, 1969'))
      ->addByline('2 Members');

    $badges1[] = id(new PHUIBadgeView())
      ->setIcon('fa-lock')
      ->setHeader(pht('Blessed Reviewers'))
      ->setSource('Projects (automatic)')
      ->addByline(pht('Dec 31, 1969'))
      ->addByline('3 Members');

    $badges1[] = id(new PHUIBadgeView())
      ->setIcon('fa-umbrella')
      ->setHeader(pht('Wikipedia'))
      ->setSource('Projects (automatic)')
      ->addByline(pht('Dec 31, 1969'))
      ->addByline('22 Members');

    $badges2 = array();
    $badges2[] = id(new PHUIBadgeView())
      ->setIcon('fa-user')
      ->setHeader(pht('Phabricator User'))
      ->setSubhead(pht('Confirmed your account.'))
      ->setQuality(PHUIBadgeView::POOR)
      ->setSource(pht('People (automatic)'))
      ->addByline(pht('Dec 31, 1969'))
      ->addByline('212 Issued (100%)');

    $badges2[] = id(new PHUIBadgeView())
      ->setIcon('fa-code')
      ->setHeader(pht('Code Contributor'))
      ->setSubhead(pht('Wrote code that was acceptable'))
      ->setQuality(PHUIBadgeView::COMMON)
      ->setSource('Diffusion (automatic)')
      ->addByline(pht('Dec 31, 1969'))
      ->addByline('200 Awarded (98%)');

    $badges2[] = id(new PHUIBadgeView())
      ->setIcon('fa-bug')
      ->setHeader(pht('Task Master'))
      ->setSubhead(pht('Closed over 100 tasks'))
      ->setQuality(PHUIBadgeView::UNCOMMON)
      ->setSource('Maniphest (automatic)')
      ->addByline(pht('Dec 31, 1969'))
      ->addByline('56 Awarded (43%)');

    $badges2[] = id(new PHUIBadgeView())
      ->setIcon('fa-star')
      ->setHeader(pht('Code Weaver'))
      ->setSubhead(pht('Landed 1,000 Commits'))
      ->setQuality(PHUIBadgeView::RARE)
      ->setSource('Diffusion (automatic)')
      ->addByline(pht('Dec 31, 1969'))
      ->addByline('42 Awarded (20%)');

    $badges2[] = id(new PHUIBadgeView())
      ->setIcon('fa-users')
      ->setHeader(pht('Security Team'))
      ->setSubhead(pht('<sripts>alert(1);</script>'))
      ->setQuality(PHUIBadgeView::EPIC)
      ->setSource('Projects (automatic)')
      ->addByline(pht('Dec 31, 1969'))
      ->addByline('21 Awarded (10%)');

    $badges2[] = id(new PHUIBadgeView())
      ->setIcon('fa-user')
      ->setHeader(pht('Adminstrator'))
      ->setSubhead(pht('Drew the short stick'))
      ->setQuality(PHUIBadgeView::LEGENDARY)
      ->setSource(pht('People (automatic)'))
      ->addByline(pht('Dec 31, 1969'))
      ->addByline('3 Awarded (1.4%)');

    $badges2[] = id(new PHUIBadgeView())
      ->setIcon('fa-compass')
      ->setHeader(pht('Lead Developer'))
      ->setSubhead(pht('Lead Developer of Phabricator'))
      ->setQuality(PHUIBadgeView::HEIRLOOM)
      ->setSource(pht('Direct Award (epriestley)'))
      ->addByline(pht('Dec 31, 1969'))
      ->addByline('1 Awarded (0.4%)');

    $flex1 = new PHUIBadgeBoxView();
    $flex1->addItems($badges1);

    $box1 = id(new PHUIObjectBoxView())
      ->setHeaderText(pht('Project Membership'))
      ->appendChild($flex1);

    $flex2 = new PHUIBadgeBoxView();
    $flex2->addItems($badges2);

    $box2 = id(new PHUIObjectBoxView())
      ->setHeaderText(pht('Acheivements'))
      ->appendChild($flex2);

    return array($box1, $box2);
  }
}
