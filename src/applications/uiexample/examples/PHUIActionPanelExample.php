<?php

final class PHUIActionPanelExample extends PhabricatorUIExample {

  public function getName() {
    return 'Action Panel';
  }

  public function getDescription() {
    return 'A panel with strong tendencies for inciting ACTION!';
  }

  public function renderExample() {

    $view = id(new AphrontMultiColumnView())
      ->setFluidLayout(true)
      ->setBorder(true);

    /* Action Panels */
    $panel1 = id(new PHUIActionPanelView())
      ->setFontIcon('fa-book')
      ->setHeader(pht('Read Documentation'))
      ->setHref('#')
      ->setSubHeader(pht('Reading is a common way to learn about things.'))
      ->setStatus(pht('Carrots help you see better.'))
      ->setState(PHUIActionPanelView::STATE_NONE);
    $view->addColumn($panel1);

    $panel2 = id(new PHUIActionPanelView())
      ->setFontIcon('fa-server')
      ->setHeader(pht('Launch Instance'))
      ->setHref('#')
      ->setSubHeader(pht('Maybe this is what you\'re likely here for.'))
      ->setStatus(pht('You have no instances.'))
      ->setState(PHUIActionPanelView::STATE_ERROR);
    $view->addColumn($panel2);

    $panel3 = id(new PHUIActionPanelView())
      ->setFontIcon('fa-group')
      ->setHeader(pht('Code with Friends'))
      ->setHref('#')
      ->setSubHeader(pht('Writing code is much more fun with friends!'))
      ->setStatus(pht('You need more friends.'))
      ->setState(PHUIActionPanelView::STATE_WARN);
    $view->addColumn($panel3);

    $panel4 = id(new PHUIActionPanelView())
      ->setFontIcon('fa-cloud-download')
      ->setHeader(pht('Download Data'))
      ->setHref('#')
      ->setSubHeader(pht('Need a backup of all your kitten memes?'))
      ->setStatus(pht('Building Download'))
      ->setState(PHUIActionPanelView::STATE_PROGRESS);
    $view->addColumn($panel4);


    return phutil_tag_div('ml', $view);
  }
}
