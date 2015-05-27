<?php

final class PHUIInfoPanelExample extends PhabricatorUIExample {

  public function getName() {
    return pht('Info Panel');
  }

  public function getDescription() {
    return pht('A medium sized box with bits of gooey information.');
  }

  public function renderExample() {
    $header1 = id(new PHUIHeaderView())
      ->setHeader(pht('Conpherence'));

    $header2 = id(new PHUIHeaderView())
      ->setHeader(pht('Diffusion'));

    $header3 = id(new PHUIHeaderView())
      ->setHeader(pht('Backend Ops Projects'));

    $header4 = id(new PHUIHeaderView())
      ->setHeader(pht('Revamp Liberty'))
      ->setSubHeader(pht('For great justice'))
      ->setImage(
        celerity_get_resource_uri('/rsrc/image/people/washington.png'));

    $header5 = id(new PHUIHeaderView())
      ->setHeader(pht('Phacility Redesign'))
      ->setSubHeader(pht('Move them pixels'))
      ->setImage(
        celerity_get_resource_uri('/rsrc/image/people/harding.png'));

    $header6 = id(new PHUIHeaderView())
      ->setHeader(pht('Python Phlux'))
      ->setSubHeader(pht('No. Sleep. Till Brooklyn.'))
      ->setImage(
      celerity_get_resource_uri('/rsrc/image/people/taft.png'));

    $column1 = id(new PHUIInfoPanelView())
      ->setHeader($header1)
      ->setColumns(3)
      ->addInfoBlock(3, pht('Needs Triage'))
      ->addInfoBlock(5, pht('Unbreak Now'))
      ->addInfoBlock(0, pht('High'))
      ->addInfoBlock(0, pht('Normal'))
      ->addInfoBlock(12, pht('Low'))
      ->addInfoBlock(123, pht('Wishlist'));

    $column2 = id(new PHUIInfoPanelView())
      ->setHeader($header2)
      ->setColumns(3)
      ->addInfoBlock(3, pht('Needs Triage'))
      ->addInfoBlock(5, pht('Unbreak Now'))
      ->addInfoBlock(0, pht('High'))
      ->addInfoBlock(0, pht('Normal'))
      ->addInfoBlock(12, pht('Low'))
      ->addInfoBlock(123, pht('Wishlist'));

    $column3 = id(new PHUIInfoPanelView())
      ->setHeader($header3)
      ->setColumns(3)
      ->addInfoBlock(3, pht('Needs Triage'))
      ->addInfoBlock(5, pht('Unbreak Now'))
      ->addInfoBlock(0, pht('High'))
      ->addInfoBlock(0, pht('Normal'))
      ->addInfoBlock(12, pht('Low'))
      ->addInfoBlock(123, pht('Wishlist'));

    $column4 = id(new PHUIInfoPanelView())
      ->setHeader($header4)
      ->setColumns(3)
      ->setProgress(90)
      ->addInfoBlock(3, pht('Needs Triage'))
      ->addInfoBlock(5, pht('Unbreak Now'))
      ->addInfoBlock(0, pht('High'))
      ->addInfoBlock(0, pht('Normal'))
      ->addInfoBlock(0, pht('Wishlist'));

    $column5 = id(new PHUIInfoPanelView())
      ->setHeader($header5)
      ->setColumns(2)
      ->setProgress(25)
      ->addInfoBlock(3, pht('Needs Triage'))
      ->addInfoBlock(5, pht('Unbreak Now'))
      ->addInfoBlock(0, pht('High'))
      ->addInfoBlock(0, pht('Normal'));

    $column6 = id(new PHUIInfoPanelView())
      ->setHeader($header6)
      ->setColumns(2)
      ->setProgress(50)
      ->addInfoBlock(3, pht('Needs Triage'))
      ->addInfoBlock(5, pht('Unbreak Now'))
      ->addInfoBlock(0, pht('High'))
      ->addInfoBlock(0, pht('Normal'));

    $layout1 = id(new AphrontMultiColumnView())
      ->addColumn($column1)
      ->addColumn($column2)
      ->addColumn($column3)
      ->setFluidLayout(true);

    $layout2 = id(new AphrontMultiColumnView())
      ->addColumn($column4)
      ->addColumn($column5)
      ->addColumn($column6)
      ->setFluidLayout(true);


    $head1 = id(new PHUIHeaderView())
      ->setHeader(pht('Flagged'));

    $head2 = id(new PHUIHeaderView())
      ->setHeader(pht('Sprints'));


    $wrap1 = id(new PHUIBoxView())
      ->appendChild($layout1)
      ->addMargin(PHUI::MARGIN_LARGE_BOTTOM);

    $wrap2 = id(new PHUIBoxView())
      ->appendChild($layout2)
      ->addMargin(PHUI::MARGIN_LARGE_BOTTOM);


    return phutil_tag(
      'div',
        array(),
        array(
          $head1,
          $wrap1,
          $head2,
          $wrap2,
        ));
  }
}
