<?php

final class PhabricatorMultiColumnUIExample extends PhabricatorUIExample {

  public function getName() {
    return pht('Multiple Column Layouts');
  }

  public function getDescription() {
    return pht(
      'A container good for 1-7 equally spaced columns. '.
      'Fixed and Fluid layouts.');
  }

  public function renderExample() {
    $request = $this->getRequest();
    $user = $request->getUser();

    $column1 = phutil_tag(
      'div',
        array(
          'class' => 'pm',
          'style' => 'border: 1px solid green;',
        ),
        'Bruce Campbell');

    $column2 = phutil_tag(
      'div',
        array(
          'class' => 'pm',
          'style' => 'border: 1px solid blue;',
        ),
        'Army of Darkness');

    $head1 = id(new PHUIHeaderView())
      ->setHeader(pht('2 Column Fixed'));
    $layout1 = id(new AphrontMultiColumnView())
      ->addColumn($column1)
      ->addColumn($column2)
      ->setGutter(AphrontMultiColumnView::GUTTER_MEDIUM);

    $head2 = id(new PHUIHeaderView())
      ->setHeader(pht('2 Column Fluid'));
    $layout2 = id(new AphrontMultiColumnView())
      ->addColumn($column1)
      ->addColumn($column2)
      ->setFluidLayout(true)
      ->setGutter(AphrontMultiColumnView::GUTTER_MEDIUM);

    $head3 = id(new PHUIHeaderView())
      ->setHeader(pht('4 Column Fixed'));
    $layout3 = id(new AphrontMultiColumnView())
      ->addColumn($column1)
      ->addColumn($column2)
      ->addColumn($column1)
      ->addColumn($column2)
      ->setGutter(AphrontMultiColumnView::GUTTER_SMALL);

    $head4 = id(new PHUIHeaderView())
      ->setHeader(pht('4 Column Fluid'));
    $layout4 = id(new AphrontMultiColumnView())
      ->addColumn($column1)
      ->addColumn($column2)
      ->addColumn($column1)
      ->addColumn($column2)
      ->setFluidLayout(true)
      ->setGutter(AphrontMultiColumnView::GUTTER_SMALL);

    $sunday = hsprintf('<strong>Sunday</strong><br /><br />Watch Football'.
      '<br />Code<br />Eat<br />Sleep');

    $monday = hsprintf('<strong>Monday</strong><br /><br />Code'.
      '<br />Eat<br />Sleep');

    $tuesday = hsprintf('<strong>Tuesday</strong><br />'.
      '<br />Code<br />Eat<br />Sleep');

    $wednesday = hsprintf('<strong>Wednesday</strong><br /><br />Code'.
      '<br />Eat<br />Sleep');

    $thursday = hsprintf('<strong>Thursday</strong><br />'.
      '<br />Code<br />Eat<br />Sleep');

    $friday = hsprintf('<strong>Friday</strong><br /><br />Code'.
      '<br />Eat<br />Sleep');

    $saturday = hsprintf('<strong>Saturday</strong><br /><br />StarCraft II'.
      '<br />All<br />Damn<br />Day');

    $head5 = id(new PHUIHeaderView())
      ->setHeader(pht('7 Column Fluid'));
    $layout5 = id(new AphrontMultiColumnView())
      ->addColumn($sunday)
      ->addColumn($monday)
      ->addColumn($tuesday)
      ->addColumn($wednesday)
      ->addColumn($thursday)
      ->addColumn($friday)
      ->addColumn($saturday)
      ->setFluidLayout(true)
      ->setBorder(true);

    $shipping = id(new PHUIFormLayoutView())
      ->setUser($user)
      ->setFullWidth(true)
      ->appendChild(
        id(new AphrontFormTextControl())
        ->setLabel(pht('Name'))
        ->setDisableAu4tocomplete(true)
        ->setSigil('name-input'))
      ->appendChild(
        id(new AphrontFormTextControl())
        ->setLabel(pht('Address'))
        ->setDisableAutocomplete(true)
        ->setSigil('address-input'))
      ->appendChild(
        id(new AphrontFormTextControl())
        ->setLabel(pht('City/State'))
        ->setDisableAutocomplete(true)
        ->setSigil('city-input'))
      ->appendChild(
        id(new AphrontFormTextControl())
        ->setLabel(pht('Country'))
        ->setDisableAutocomplete(true)
        ->setSigil('country-input'))
      ->appendChild(
        id(new AphrontFormTextControl())
        ->setLabel(pht('Postal Code'))
        ->setDisableAutocomplete(true)
        ->setSigil('postal-input'));

    $cc = id(new PHUIFormLayoutView())
      ->setUser($user)
      ->setFullWidth(true)
      ->appendChild(
        id(new AphrontFormTextControl())
        ->setLabel(pht('Card Number'))
        ->setDisableAutocomplete(true)
        ->setSigil('number-input')
        ->setError(''))
      ->appendChild(
        id(new AphrontFormTextControl())
        ->setLabel(pht('CVC'))
        ->setDisableAutocomplete(true)
        ->setSigil('cvc-input')
        ->setError(''))
      ->appendChild(
        id(new PhortuneMonthYearExpiryControl())
        ->setLabel(pht('Expiration'))
        ->setUser($user)
        ->setError(''));

    $shipping_title = pht('Shipping Address');
    $billing_title = pht('Billing Address');
    $cc_title = pht('Payment Information');

    $head6 = id(new PHUIHeaderView())
      ->setHeader(pht("Let's Go Shopping"));
    $layout6 = id(new AphrontMultiColumnView())
      ->addColumn(hsprintf('<h1>%s</h1>%s', $shipping_title, $shipping))
      ->addColumn(hsprintf('<h1>%s</h1>%s', $billing_title, $shipping))
      ->addColumn(hsprintf('<h1>%s</h1>%s', $cc_title, $cc))
      ->setFluidLayout(true)
      ->setBorder(true);

    $wrap1 = phutil_tag(
      'div',
        array(
          'class' => 'ml',
        ),
        $layout1);

    $wrap2 = phutil_tag(
      'div',
        array(
          'class' => 'ml',
        ),
        $layout2);

    $wrap3 = phutil_tag(
      'div',
        array(
          'class' => 'ml',
        ),
        $layout3);

    $wrap4 = phutil_tag(
      'div',
        array(
          'class' => 'ml',
        ),
        $layout4);

    $wrap5 = phutil_tag(
      'div',
        array(
          'class' => 'ml',
        ),
        $layout5);

    $wrap6 = phutil_tag(
      'div',
        array(
          'class' => 'ml',
        ),
        $layout6);

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
          $wrap5,
          $head6,
          $wrap6,
        ));
  }
}
