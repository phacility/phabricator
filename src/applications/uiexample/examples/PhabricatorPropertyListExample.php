<?php

final class PhabricatorPropertyListExample extends PhabricatorUIExample {

  public function getName() {
    return 'Property List';
  }

  public function getDescription() {
    return 'Use <tt>PhabricatorPropertyListView</tt> to render object '.
           'properties.';
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

    return $view;
  }
}
