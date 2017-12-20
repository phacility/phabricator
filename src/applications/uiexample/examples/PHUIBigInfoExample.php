<?php

final class PHUIBigInfoExample extends PhabricatorUIExample {

  public function getName() {
    return pht('Big Info View');
  }

  public function getDescription() {
    return pht(
      'Basic New User State information block.');
  }

  public function renderExample() {
    $request = $this->getRequest();
    $viewer = $request->getUser();

    $image = PhabricatorFile::loadBuiltin($viewer,
      'projects/v3/rocket.png');

    $button = id(new PHUIButtonView())
      ->setTag('a')
      ->setText(pht('Launch Away'))
      ->setColor(PHUIButtonView::GREEN)
      ->setHref('#');

    $views = array();
    $views[] = id(new PHUIBigInfoView())
      ->setTitle(pht('Simply Slim'))
      ->setDescription(pht('A simple description'))
      ->addAction($button);

    $views[] = id(new PHUIBigInfoView())
      ->setTitle(pht('Basicly Basic'))
      ->setIcon('fa-rocket')
      ->setDescription(pht('A more basic description'))
      ->addAction($button);

    $views[] = id(new PHUIBigInfoView())
      ->setTitle(pht('A Modern Example'))
      ->setImage($image->getBestURI())
      ->setDescription(pht('A modern description with lots of frills.'))
      ->addAction($button);


    return phutil_tag_div('ml', $views);
  }
}
