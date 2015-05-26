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
      ->setSubHeader(pht("Maybe this is what you're likely here for."))
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

    $view2 = id(new AphrontMultiColumnView())
      ->setFluidLayout(true)
      ->setBorder(true);

    /* Action Panels */
    $panel1 = id(new PHUIActionPanelView())
      ->setFontIcon('fa-credit-card')
      ->setHeader(pht('Account Balance'))
      ->setHref('#')
      ->setSubHeader(pht('You were last billed $2,245.12 on Dec 12, 2014.'))
      ->setStatus(pht('Account in good standing.'))
      ->setState(PHUIActionPanelView::STATE_SUCCESS);
    $view2->addColumn($panel1);

    $panel2 = id(new PHUIActionPanelView())
      ->setBigText('148')
      ->setHeader(pht('Instance Users'))
      ->setHref('#')
      ->setSubHeader(
        pht('You currently have 140 active and 8 inactive accounts'));
    $view2->addColumn($panel2);

    $panel3 = id(new PHUIActionPanelView())
      ->setBigText('March 12')
      ->setHeader(pht('Next Maintenance Window'))
      ->setHref('#')
      ->setSubHeader(
        pht('At 6:00 am PST, Phacility will conduct weekly maintenence.'))
      ->setStatus(pht('Very Important!'))
      ->setState(PHUIActionPanelView::STATE_ERROR);
    $view2->addColumn($panel3);

    $panel4 = id(new PHUIActionPanelView())
      ->setBigText('1,113,377')
      ->setHeader(pht('Lines of Code'))
      ->setHref('#')
      ->setSubHeader(pht('Your team has reviewed lots of code!'));
    $view2->addColumn($panel4);

    $view = phutil_tag_div('mlb', $view);

    return phutil_tag_div('ml', array($view, $view2));
  }
}
