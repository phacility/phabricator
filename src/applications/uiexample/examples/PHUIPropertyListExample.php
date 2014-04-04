<?php

final class PHUIPropertyListExample extends PhabricatorUIExample {

  public function getName() {
    return 'Property List';
  }

  public function getDescription() {
    return hsprintf(
      'Use <tt>PHUIPropertyListView</tt> to render object properties.');
  }

  public function renderExample() {
    $request = $this->getRequest();
    $user = $request->getUser();

    $details1 = id(new PHUIListItemView())
      ->setName('Details')
      ->setSelected(true);

    $details2 = id(new PHUIListItemView())
      ->setName('Rainbow Info')
      ->setStatusColor(PHUIListItemView::STATUS_WARN);

    $details3 = id(new PHUIListItemView())
      ->setName('Pasta Haiku')
      ->setStatusColor(PHUIListItemView::STATUS_FAIL);

    $statustabs = id(new PHUIListView())
      ->setType(PHUIListView::NAVBAR_LIST)
      ->addMenuItem($details1)
      ->addMenuItem($details2)
      ->addMenuItem($details3);

    $view = new PHUIPropertyListView();

    $view->addProperty(
      pht('Color'),
      pht('Yellow'));

    $view->addProperty(
      pht('Size'),
      pht('Mouse'));

    $view->addProperty(
      pht('Element'),
      pht('Electric'));

    $view->addTextContent(
      'Lorem ipsum dolor sit amet, consectetur adipiscing elit. '.
      'Quisque rhoncus tempus massa, sit amet faucibus lectus bibendum '.
      'viverra. Nunc tempus tempor quam id iaculis. Maecenas lectus '.
      'velit, aliquam et consequat quis, tincidunt id dolor.');


    $view2 = new PHUIPropertyListView();
    $view2->addSectionHeader('Colors of the Rainbow');

    $view2->addProperty('R', 'Red');
    $view2->addProperty('O', 'Orange');
    $view2->addProperty('Y', 'Yellow');
    $view2->addProperty('G', 'Green');
    $view2->addProperty('B', 'Blue');
    $view2->addProperty('I', 'Indigo');
    $view2->addProperty('V', 'Violet');


    $view3 = new PHUIPropertyListView();
    $view3->addSectionHeader('Haiku About Pasta');

    $view3->addTextContent(
      hsprintf(
        'this is a pasta<br />'.
        'haiku. it is very bad.<br />'.
        'what did you expect?'));

    $object_box1 = id(new PHUIObjectBoxView())
      ->setHeaderText('PHUIPropertyListView Stackered')
      ->addPropertyList($view, $details1)
      ->addPropertyList($view2, $details2)
      ->addPropertyList($view3, $details3);

    $edge_cases_view = new PHUIPropertyListView();

    $edge_cases_view->addProperty(
      pht('Description'),
      pht('These layouts test UI edge cases in the element. This block '.
          'tests wrapping and overflow behavior.'));

    $edge_cases_view->addProperty(
      pht('A Very Very Very Very Very Very Very Very Very Long Property Label'),
      pht('This property label and property value are quite long. They '.
          'demonstrate the wrapping behavior of the element, or lack thereof '.
          'if something terrible has happened.'));

    $edge_cases_view->addProperty(
      pht('AVeryVeryVeryVeryVeryVeryVeryVeryVeryVeryLongUnbrokenPropertyLabel'),
      pht('Thispropertylabelandpropertyvaluearequitelongandhave'.
          'nospacestheydemonstratetheoverflowbehavioroftheelement'.
          'orlackthereof.'));


    $edge_cases_view->addProperty(
      pht('Description'),
      pht('The next section is an empty text block.'));

    $edge_cases_view->addTextContent('');

    $edge_cases_view->addProperty(
      pht('Description'),
      pht('This section should have multiple properties with the same name.'));

    $edge_cases_view->addProperty(
      pht('Joe'),
      pht('Smith'));
    $edge_cases_view->addProperty(
      pht('Joe'),
      pht('Smith'));
    $edge_cases_view->addProperty(
      pht('Joe'),
      pht('Smith'));

    $object_box2 = id(new PHUIObjectBoxView())
      ->setHeaderText('Some Bad Examples')
      ->addPropertyList($edge_cases_view);

    return array(
      $object_box1,
      $object_box2,
    );
  }
}
