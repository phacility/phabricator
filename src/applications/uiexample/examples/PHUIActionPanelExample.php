<?php

final class PHUIActionPanelExample extends PhabricatorUIExample {

  public function getName() {
    return pht('Action Panel');
  }

  public function getDescription() {
    return pht('A panel with strong tendencies for inciting ACTION!');
  }

  public function renderExample() {

    $view = id(new AphrontMultiColumnView())
      ->setFluidLayout(true);

    /* Action Panels */
    $panel1 = id(new PHUIActionPanelView())
      ->setFontIcon('fa-book')
      ->setHeader(pht('Read Documentation'))
      ->setHref('#')
      ->setSubHeader(pht('Reading is a common way to learn about things.'))
      ->setState(PHUIActionPanelView::COLOR_BLUE);
    $view->addColumn($panel1);

    $panel2 = id(new PHUIActionPanelView())
      ->setFontIcon('fa-server')
      ->setHeader(pht('Launch Instance'))
      ->setHref('#')
      ->setSubHeader(pht("Maybe this is what you're likely here for."))
      ->setState(PHUIActionPanelView::COLOR_RED);
    $view->addColumn($panel2);

    $panel3 = id(new PHUIActionPanelView())
      ->setFontIcon('fa-group')
      ->setHeader(pht('Code with Friends'))
      ->setHref('#')
      ->setSubHeader(pht('Writing code is much more fun with friends!'))
      ->setState(PHUIActionPanelView::COLOR_YELLOW);
    $view->addColumn($panel3);

    $panel4 = id(new PHUIActionPanelView())
      ->setFontIcon('fa-cloud-download')
      ->setHeader(pht('Download Data'))
      ->setHref('#')
      ->setSubHeader(pht('Need a backup of all your kitten memes?'))
      ->setState(PHUIActionPanelView::COLOR_PINK);
    $view->addColumn($panel4);

    $view2 = id(new AphrontMultiColumnView())
      ->setFluidLayout(true);

    /* Action Panels */
    $panel1 = id(new PHUIActionPanelView())
      ->setFontIcon('fa-credit-card')
      ->setHeader(pht('Account Balance'))
      ->setHref('#')
      ->setSubHeader(pht('You were last billed $2,245.12 on Dec 12, 2014.'))
      ->setState(PHUIActionPanelView::COLOR_GREEN);
    $view2->addColumn($panel1);

    $panel2 = id(new PHUIActionPanelView())
      ->setBigText(true)
      ->setHeader(pht('Instance Users'))
      ->setHref('#')
      ->setSubHeader(
        pht('148'));
    $view2->addColumn($panel2);

    $panel3 = id(new PHUIActionPanelView())
      ->setBigText(true)
      ->setHeader(pht('Next Maintenance Window'))
      ->setHref('#')
      ->setSubHeader(
        pht('March 12'))
      ->setState(PHUIActionPanelView::COLOR_ORANGE);
    $view2->addColumn($panel3);

    $panel4 = id(new PHUIActionPanelView())
      ->setBigText(true)
      ->setHeader(pht('Lines of Code'))
      ->setHref('#')
      ->setSubHeader(pht('1,113,377'))
      ->setState(PHUIActionPanelView::COLOR_INDIGO);
    $view2->addColumn($panel4);

    $view = phutil_tag_div('mlb', $view);

    return phutil_tag_div('ml', array($view, $view2));
  }
}
