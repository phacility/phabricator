<?php

final class PhabricatorPropertyListExample extends PhabricatorUIExample {

  public function getName() {
    return 'Property List';
  }

  public function getDescription() {
    return hsprintf(
      'Use <tt>PhabricatorPropertyListView</tt> to render object properties.');
  }

  public function renderExample() {
    $request = $this->getRequest();
    $user = $request->getUser();

    $view = new PhabricatorPropertyListView();

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


    $view->addSectionHeader('Colors of the Rainbow');

    $view->addProperty('R', 'Red');
    $view->addProperty('O', 'Orange');
    $view->addProperty('Y', 'Yellow');
    $view->addProperty('G', 'Green');
    $view->addProperty('B', 'Blue');
    $view->addProperty('I', 'Indigo');
    $view->addProperty('V', 'Violet');

    $view->addSectionHeader('Haiku About Pasta');

    $view->addTextContent(
      hsprintf(
        'this is a pasta<br />'.
        'haiku. it is very bad.<br />'.
        'what did you expect?'));

    $edge_cases_header = id(new PhabricatorHeaderView())
      ->setHeader(pht('Edge Cases'));

    $edge_cases_view = new PhabricatorPropertyListView();

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

    $edge_cases_view->addProperty(
      pht('Description'),
      pht('The next section shows adjacent section headers.'));

    $edge_cases_view->addSectionHeader('Several');
    $edge_cases_view->addSectionHeader('Adjacent');
    $edge_cases_view->addSectionHeader('Section');
    $edge_cases_view->addSectionHeader('Headers');

    $edge_cases_view->addProperty(
      pht('Description'),
      pht('The next section is several adjacent text blocks.'));

    $edge_cases_view->addTextContent('Lorem');
    $edge_cases_view->addTextContent('ipsum');
    $edge_cases_view->addTextContent('dolor');
    $edge_cases_view->addTextContent('sit');
    $edge_cases_view->addTextContent('amet...');

    return array(
      $view,
      $edge_cases_header,
      $edge_cases_view,
    );
  }
}
