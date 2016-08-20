<?php

final class PHUIObjectItemListExample extends PhabricatorUIExample {

  public function getName() {
    return pht('Object Item List');
  }

  public function getDescription() {
    return pht(
      'Use %s to render lists of objects.',
      hsprintf('<tt>PHUIObjectItemListView</tt>'));
  }

  public function renderExample() {
    $request = $this->getRequest();
    $user = $request->getUser();

    $handle = id(new PhabricatorHandleQuery())
      ->setViewer($user)
      ->withPHIDs(array($user->getPHID()))
      ->executeOne();

    $out = array();

    $list = new PHUIObjectItemListView();

    $list->addItem(
      id(new PHUIObjectItemView())
        ->setObjectName('FRUIT1')
        ->setStatusIcon('fa-apple')
        ->setHeader(pht('Apple'))
        ->setHref('#'));

    $list->addItem(
      id(new PHUIObjectItemView())
        ->setObjectName('FRUIT2')
        ->setHeader(pht('Banana'))
        ->setHref('#'));

    $list->addItem(
      id(new PHUIObjectItemView())
        ->setObjectName('FRUIT3')
        ->setHeader(pht('Cherry'))
        ->setHref('#'));

    $box = id(new PHUIObjectBoxView())
      ->setHeaderText('Basic List')
      ->setObjectList($list);

    $out[] = $box;

    $list = new PHUIObjectItemListView();
    $list->setNoDataString(pht('This list is empty.'));

    $box = id(new PHUIObjectBoxView())
      ->setHeaderText('Empty List')
      ->setObjectList($list);

    $out[] = $box;

    $list = new PHUIObjectItemListView();

    $list->addItem(
      id(new PHUIObjectItemView())
        ->setHeader(pht('Monday'))
        ->setHref('#'));
    $list->addItem(
      id(new PHUIObjectItemView())
        ->setHeader(pht('Tuesday'))
        ->setHref('#'));
    $list->addItem(
      id(new PHUIObjectItemView())
        ->setHeader(pht('Wednesday'))
        ->setHref('#'));
    $list->addItem(
      id(new PHUIObjectItemView())
        ->setHeader(pht('Thursday'))
        ->setHref('#'));

    $box = id(new PHUIObjectBoxView())
      ->setHeaderText('Stacked List')
      ->setObjectList($list);

    $out[] = $box;

    $list = new PHUIObjectItemListView();

    $list->addItem(
      id(new PHUIObjectItemView())
        ->setHeader(pht('Business Card'))
        ->setStatusIcon('fa-warning red'));
    $list->addItem(
      id(new PHUIObjectItemView())
        ->setHeader(pht('Playing Card'))
        ->setStatusIcon('fa-warning orange')
        ->addIcon('fa-comment', pht('Royal Flush!')));

    $owner = phutil_tag('a', array('href' => '#'), pht('jackofclubs'));
    $list->addItem(
      id(new PHUIObjectItemView())
        ->setHeader(pht('House of Cards'))
        ->setStatusIcon('fa-warning yellow')
        ->setDisabled(true)
        ->addByline(pht('Owner: %s', $owner)));

    $author = phutil_tag('a', array('href' => '#'), pht('agoat'));
    $list->addItem(
      id(new PHUIObjectItemView())
        ->setHeader(pht('Cardigan'))
        ->setStatusIcon('fa-check green')
        ->addIcon('fa-star', pht('Warm!'))
        ->addByline(pht('Author: %s', $author)));
    $list->addItem(
      id(new PHUIObjectItemView())
        ->setHeader(pht('Cardamom'))
        ->setStatusIcon('fa-check blue'));
    $list->addItem(
      id(new PHUIObjectItemView())
        ->setHeader(
          pht(
            'The human cardiovascular system includes the heart, lungs, and '.
            'some other parts; most of these parts are pretty squishy.'))
        ->addHandleIcon($handle, pht('You have a cardiovascular system!'))
        ->setStatusIcon('fa-check indigo'));


    $box = id(new PHUIObjectBoxView())
      ->setHeaderText('Card List')
      ->setObjectList($list);

    $out[] = $box;

    $list = new PHUIObjectItemListView();

    $list->addItem(
      id(new PHUIObjectItemView())
        ->setHeader(pht('Grab ahold!'))
        ->setHref('#')
        ->setGrippable(true));

    $list->addItem(
      id(new PHUIObjectItemView())
        ->setHeader(pht('Hold on tight!'))
        ->setHref('#')
        ->setGrippable(true));

    $list->addItem(
      id(new PHUIObjectItemView())
        ->setHeader(pht("Don't let go!"))
        ->setHref('#')
        ->setGrippable(true)
        ->addAction(
          id(new PHUIListItemView())
            ->setHref('#')
            ->setIcon('fa-times')));

    $box = id(new PHUIObjectBoxView())
      ->setHeaderText('Grippable List')
      ->setObjectList($list);

    $out[] = $box;

    $list = new PHUIObjectItemListView();

    $list->addItem(
      id(new PHUIObjectItemView())
        ->setHeader(pht('You Have The Power'))
        ->setHref('#')
        ->setStatusIcon('fa-circle-o blue')
        ->addAction(
          id(new PHUIListItemView())
            ->setHref('#')
            ->setName(pht('Moo'))
            ->setIcon('fa-pencil')));

    $list->addItem(
      id(new PHUIObjectItemView())
        ->setHeader(pht('Believe In Yourself'))
        ->setHref('#')
        ->setStatusIcon('fa-circle-o violet')
        ->addAction(
          id(new PHUIListItemView())
            ->setHref('#')
            ->setName(pht('Quack'))
            ->setIcon('fa-pencil'))
        ->addAction(
          id(new PHUIListItemView())
            ->setHref('#')
            ->setName(pht('Oink'))
            ->setIcon('fa-times')));

    $box = id(new PHUIObjectBoxView())
      ->setHeaderText('Action Link List')
      ->setObjectList($list);

    $head = id(new PHUIHeaderView())
      ->setHeader(pht('Extras'));

    $list = new PHUIObjectItemListView();

    $list->addItem(
      id(new PHUIObjectItemView())
        ->setHeader(pht('Ace of Hearts'))
        ->setSubHead(
          pht('This is a powerful card in the game "Hearts".'))
        ->setHref('#')
        ->addAttribute(pht('Suit: Hearts'))
        ->addAttribute(pht('Rank: Ace'))
        ->addIcon('fa-heart', pht('Ace'))
        ->addIcon('fa-heart red', pht('Hearts'))
        ->addHandleIcon($handle, pht('You hold all the cards.'))
        ->addHandleIcon($handle, pht('You make all the rules.')));

    $list->addItem(
      id(new PHUIObjectItemView())
        ->setHeader(pht('Just A Handle'))
        ->setHref('#')
        ->addHandleIcon($handle, pht('Handle Here')));

    $list->addItem(
      id(new PHUIObjectItemView())
        ->setHeader(pht('Poor Use of Space'))
        ->setHref('#')
        ->addAttribute('North West')
        ->addHandleIcon($handle, pht('South East')));

    $list->addItem(
      id(new PHUIObjectItemView())
        ->setHeader(pht('Crowded Eastern Edge'))
        ->setHref('#')
        ->addIcon('fa-circle red', pht('Stuff'))
        ->addIcon('fa-circle yellow', pht('Stuff'))
        ->addIcon('fa-circle green', pht('Stuff'))
        ->addHandleIcon($handle, pht('More Stuff')));

    $box = id(new PHUIObjectBoxView())
      ->setHeaderText('Action List')
      ->setObjectList($list);

    $out[] = $box;

    $list = new PHUIObjectItemListView();

    $list->addItem(
      id(new PHUIObjectItemView())
        ->setObjectName('X1')
        ->setHeader(pht('Normal'))
        ->setHref('#'));

    $list->addItem(
      id(new PHUIObjectItemView())
        ->setObjectName('X2')
        ->setHeader(pht('Highlighted'))
        ->setEffect('highlighted')
        ->setHref('#'));

    $list->addItem(
      id(new PHUIObjectItemView())
        ->setObjectName('X3')
        ->setHeader(pht('Selected'))
        ->setEffect('selected')
        ->setHref('#'));

    $list->addItem(
      id(new PHUIObjectItemView())
        ->setObjectName('X4')
        ->setHeader(pht('Disabled'))
        ->setDisabled(true)
        ->setHref('#'));

    $box = id(new PHUIObjectBoxView())
      ->setHeaderText('Extra Things and Stuff')
      ->setObjectList($list);

    $out[] = $box;

    $list = new PHUIObjectItemListView();

    $bar_colors = array(
      null      => pht('None'),
      'red'     => pht('Red'),
      'orange'  => pht('Orange'),
      'yellow'  => pht('Yellow'),
      'green'   => pht('Green'),
      'sky'     => pht('Sky'),
      'blue'    => pht('Blue'),
      'indigo'  => pht('Indigo'),
      'violet'  => pht('Violet'),
      'grey'    => pht('Grey'),
      'black'   => pht('Black'),
    );

    foreach ($bar_colors as $bar_color => $bar_label) {
      $list->addItem(
        id(new PHUIObjectItemView())
          ->setHeader($bar_label)
          ->setStatusIcon('fa-bell '.$bar_color));
    }

    $box = id(new PHUIObjectBoxView())
      ->setHeaderText('Color Icons')
      ->setObjectList($list);

    $out[] = $box;

    $list = new PHUIObjectItemListView();
    $list->setFlush(true);

    $default_profile = PhabricatorFile::loadBuiltin($user, 'profile.png');
    $default_project = PhabricatorFile::loadBuiltin($user, 'project.png');

    $list->addItem(
      id(new PHUIObjectItemView())
        ->setImageURI($default_profile->getViewURI())
        ->setHeader(pht('Default User Profile Image'))
        ->addAction(
          id(new PHUIListItemView())
            ->setHref('#')
            ->setIcon('fa-pencil-square'))
        ->addAction(
          id(new PHUIListItemView())
            ->setHref('#')
            ->setIcon('fa-plus-square'))
        ->addAction(
          id(new PHUIListItemView())
            ->setHref('#')
            ->setIcon('fa-minus-square')));

    $list->addItem(
      id(new PHUIObjectItemView())
        ->setImageURI($default_project->getViewURI())
        ->setHeader(pht('Default Project Profile Image'))
        ->setGrippable(true)
        ->addAttribute(pht('This is the default project profile image.')));

    $box = id(new PHUIObjectBoxView())
      ->setHeaderText('Profile Images')
      ->setObjectList($list);

    $out[] = $box;

    return $out;
  }
}
