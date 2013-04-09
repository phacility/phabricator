<?php

final class PhabricatorMultiColumnExample extends PhabricatorUIExample {

  public function getName() {
    return 'Multiple Column Layouts';
  }

  public function getDescription() {
    return 'A container good for 1-7 equally spaced columns. '.
      'Fixed and Fluid layouts.';
  }

  public function renderExample() {

    $column1 = phutil_tag(
      'div',
        array(
          'class' => 'pm',
          'style' => 'border: 1px solid green;'
        ),
        'Bruce Campbell');

    $column2 = phutil_tag(
      'div',
        array(
          'class' => 'pm',
          'style' => 'border: 1px solid blue;'
        ),
        'Army of Darkness');

    $head1 = id(new PhabricatorHeaderView())
      ->setHeader(pht('2 Column Fixed'));
    $layout1 = id(new AphrontMultiColumnView())
      ->addColumn($column1)
      ->addColumn($column2)
      ->setGutter(AphrontMultiColumnView::GUTTER_MEDIUM);

    $head2 = id(new PhabricatorHeaderView())
      ->setHeader(pht('2 Column Fluid'));
    $layout2 = id(new AphrontMultiColumnView())
      ->addColumn($column1)
      ->addColumn($column2)
      ->setFluidLayout(true)
      ->setGutter(AphrontMultiColumnView::GUTTER_MEDIUM);

    $head3 = id(new PhabricatorHeaderView())
      ->setHeader(pht('4 Column Fixed'));
    $layout3 = id(new AphrontMultiColumnView())
      ->addColumn($column1)
      ->addColumn($column2)
      ->addColumn($column1)
      ->addColumn($column2)
      ->setGutter(AphrontMultiColumnView::GUTTER_SMALL);

    $head4 = id(new PhabricatorHeaderView())
      ->setHeader(pht('4 Column Fluid'));
    $layout4 = id(new AphrontMultiColumnView())
      ->addColumn($column1)
      ->addColumn($column2)
      ->addColumn($column1)
      ->addColumn($column2)
      ->setFluidLayout(true)
      ->setGutter(AphrontMultiColumnView::GUTTER_SMALL);

    $head5 = id(new PhabricatorHeaderView())
      ->setHeader(pht('7 Column Fluid'));
    $layout5 = id(new AphrontMultiColumnView())
      ->addColumn('Sunday')
      ->addColumn('Monday')
      ->addColumn('Tuesday')
      ->addColumn('Wednesday')
      ->addColumn('Thursday')
      ->addColumn('Friday')
      ->addColumn('Saturday')
      ->setFluidLayout(true);

    $wrap1 = phutil_tag(
      'div',
        array(
          'class' => 'ml'
        ),
        $layout1);

    $wrap2 = phutil_tag(
      'div',
        array(
          'class' => 'ml'
        ),
        $layout2);

    $wrap3 = phutil_tag(
      'div',
        array(
          'class' => 'ml'
        ),
        $layout3);

    $wrap4 = phutil_tag(
      'div',
        array(
          'class' => 'ml'
        ),
        $layout4);

    $wrap5 = phutil_tag(
      'div',
        array(
          'class' => 'ml'
        ),
        $layout5);

    return phutil_tag(
      'div',
        array(),
        array(
          $head1,
          $wrap1,
          $head2,
          $wrap2,
          $head3,
          $wrap3,
          $head4,
          $wrap4,
          $head5,
          $wrap5
        ));
  }
}
