<?php

final class PHUIObjectItemListExample extends PhabricatorUIExample {

  public function getName() {
    return 'Object Item List';
  }

  public function getDescription() {
    return hsprintf(
      'Use <tt>PHUIObjectItemListView</tt> to render lists of objects.');
  }

  public function renderExample() {
    $request = $this->getRequest();
    $user = $request->getUser();

    $handle = id(new PhabricatorHandleQuery())
      ->setViewer($user)
      ->withPHIDs(array($user->getPHID()))
      ->executeOne();

    $out = array();

    $head = id(new PHUIHeaderView())
      ->setHeader(pht('Basic List'));

    $list = new PHUIObjectItemListView();

    $list->addItem(
      id(new PHUIObjectItemView())
        ->setObjectName('FRUIT1')
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

    $out[] = array($head, $list);


    $head = id(new PHUIHeaderView())
      ->setHeader(pht('Empty List'));
    $list = new PHUIObjectItemListView();

    $list->setNoDataString(pht('This list is empty.'));

    $out[] = array($head, $list);


    $head = id(new PHUIHeaderView())
      ->setHeader(pht('Stacked List'));
    $list = new PHUIObjectItemListView();
    $list->setStackable(true);

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

    $out[] = array($head, $list);

    $head = id(new PHUIHeaderView())
      ->setHeader(pht('Plain List'));
    $list = new PHUIObjectItemListView();
    $list->setPlain(true);

    $list->addItem(
      id(new PHUIObjectItemView())
        ->setHeader(pht('Monday'))
        ->setSubHead('I love cats')
        ->setHref('#'));
    $list->addItem(
      id(new PHUIObjectItemView())
        ->setHeader(pht('Tuesday'))
        ->setSubHead('Cat, cats, cats')
        ->setHref('#'));
    $list->addItem(
      id(new PHUIObjectItemView())
        ->setHeader(pht('Wednesday'))
        ->setSubHead('Meow, meow, meow')
        ->setHref('#'));
    $list->addItem(
      id(new PHUIObjectItemView())
        ->setHeader(pht('Thursday'))
        ->setSubHead('Every single day')
        ->setHref('#'));

    $out[] = array($head, $list);


    $head = id(new PHUIHeaderView())
      ->setHeader(pht('Card List'));
    $list = new PHUIObjectItemListView();
    $list->setCards(true);

    $list->addItem(
      id(new PHUIObjectItemView())
        ->setHeader(pht('Business Card'))
        ->setBarColor('red'));
    $list->addItem(
      id(new PHUIObjectItemView())
        ->setHeader(pht('Playing Card'))
        ->setBarColor('orange')
        ->addIcon('comment', pht('Royal Flush!')));

    $owner = phutil_tag('a', array('href' => '#'), pht('jackofclubs'));
    $list->addItem(
      id(new PHUIObjectItemView())
        ->setHeader(pht('House of Cards'))
        ->setBarColor('yellow')
        ->setDisabled(true)
        ->addByline(pht('Owner: %s', $owner)));

    $author = phutil_tag('a', array('href' => '#'), pht('agoat'));
    $list->addItem(
      id(new PHUIObjectItemView())
        ->setHeader(pht('Cardigan'))
        ->setBarColor('green')
        ->addIcon('highlight', pht('Warm!'))
        ->addByline(pht('Author: %s', $author)));
    $list->addItem(
      id(new PHUIObjectItemView())
        ->setHeader(pht('Cardamom'))
        ->addFootIcon('highlight-white', 'Spice')
        ->setBarColor('blue'));
    $list->addItem(
      id(new PHUIObjectItemView())
        ->setHeader(pht(
          'The human cardiovascular system includes the heart, lungs, and '.
          'some other parts; most of these parts are pretty squishy'))
        ->addFootIcon('search-white', pht('Respiration!'))
        ->addHandleIcon($handle, pht('You have a cardiovascular system!'))
        ->setBarColor('indigo'));


    $out[] = array($head, $list);


    $head = id(new PHUIHeaderView())
      ->setHeader(pht('Grippable List'));
    $list = new PHUIObjectItemListView();
    $list->setCards(true);

    $list->addItem(
      id(new PHUIObjectItemView())
        ->setHeader(pht('Grab ahold!'))
        ->setHref('#')
        ->setGrippable(true)
        ->setBarColor('red'));

    $list->addItem(
      id(new PHUIObjectItemView())
        ->setHeader(pht('Hold on tight!'))
        ->setHref('#')
        ->setGrippable(true)
        ->setBarColor('yellow'));

    $list->addItem(
      id(new PHUIObjectItemView())
        ->setHeader(pht("Don't let go!"))
        ->setHref('#')
        ->setGrippable(true)
        ->setBarColor('green')
        ->addAction(
          id(new PHUIListItemView())
            ->setHref('#')
            ->setIcon('delete')));

    $out[] = array($head, $list);

    $head = id(new PHUIHeaderView())
      ->setHeader(pht('List With Actions'));
    $list = new PHUIObjectItemListView();

    $list->addItem(
      id(new PHUIObjectItemView())
        ->setHeader(pht('You Have The Power'))
        ->setHref('#')
        ->setBarColor('blue')
        ->addAction(
          id(new PHUIListItemView())
            ->setHref('#')
            ->setName(pht('Moo'))
            ->setIcon('edit')));

    $list->addItem(
      id(new PHUIObjectItemView())
        ->setHeader(pht('Believe In Yourself'))
        ->setHref('#')
        ->setBarColor('violet')
        ->addAction(
          id(new PHUIListItemView())
            ->setHref('#')
            ->setName(pht('Quack'))
            ->setIcon('edit'))
        ->addAction(
          id(new PHUIListItemView())
            ->setHref('#')
            ->setName(pht('Oink'))
            ->setIcon('delete')));

    $out[] = array($head, $list);


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
        ->addIcon('love', pht('Ace'))
        ->addIcon('love-grey', pht('Hearts'))
        ->addFootIcon('blame-white', pht('Ace'))
        ->addFootIcon('love-white', pht('Heart'))
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
        ->addIcon('computer', pht('Stuff'))
        ->addIcon('computer', pht('Stuff'))
        ->addIcon('computer', pht('Stuff'))
        ->addHandleIcon($handle, pht('More Stuff')));

    $out[] = array($head, $list);


    $head = id(new PHUIHeaderView())
      ->setHeader(pht('Effects'));

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

    $out[] = array($head, $list);


    $head = id(new PHUIHeaderView())
      ->setHeader(pht('Colors'));

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
          ->setBarColor($bar_color));
    }

    $out[] = array($head, $list);


    $head = id(new PHUIHeaderView())
      ->setHeader(pht('Images'));

    $list = new PHUIObjectItemListView();

    $default_profile = PhabricatorFile::loadBuiltin($user, 'profile.png');
    $default_project = PhabricatorFile::loadBuiltin($user, 'project.png');

    $list->addItem(
      id(new PHUIObjectItemView())
        ->setImageURI($default_profile->getViewURI())
        ->setHeader(pht('Default User Profile Image'))
        ->setBarColor('violet')
        ->addAction(
          id(new PHUIListItemView())
            ->setHref('#')
            ->setIcon('create')));

    $list->addItem(
      id(new PHUIObjectItemView())
        ->setImageURI($default_project->getViewURI())
        ->setHeader(pht('Default Project Profile Image'))
        ->setGrippable(true)
        ->addAttribute(pht('This is the default project profile image.')));

    $out[] = array($head, $list);

    return $out;
  }
}
