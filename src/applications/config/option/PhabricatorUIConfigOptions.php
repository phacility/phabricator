<?php

final class PhabricatorUIConfigOptions
  extends PhabricatorApplicationConfigOptions {

  public function getName() {
    return pht('User Interface');
  }

  public function getDescription() {
    return pht('Configure the Phabricator UI, including colors.');
  }

  public function getOptions() {
    $manifest = PHUIIconView::getSheetManifest('main-header');

    $options = array();
    foreach (array_keys($manifest) as $sprite_name) {
      $key = substr($sprite_name, strlen('main-header-'));
      $options[$key] = $key;
    }

    $example = <<<EOJSON
[
  {
    "name" : "Copyright 2199 Examplecorp"
  },
  {
    "name" : "Privacy Policy",
    "href" : "http://www.example.org/privacy/"
  },
  {
    "name" : "Terms and Conditions",
    "href" : "http://www.example.org/terms/"
  }
]
EOJSON;

    return array(
      $this->newOption('ui.header-color', 'enum', 'dark')
        ->setDescription(
          pht(
            'Sets the color of the main header.'))
        ->setEnumOptions($options),
      $this->newOption('ui.footer-items', 'list<wild>', array())
        ->setSummary(
          pht(
            'Allows you to add footer links on most pages.'))
        ->setDescription(
          pht(
            "Allows you to add a footer with links in it to most ".
            "pages. You might want to use these links to point at legal ".
            "information or an about page.\n\n".
            "Specify a list of dictionaries. Each dictionary describes ".
            "a footer item. These keys are supported:\n\n".
            "  - `name` The name of the item.\n".
            "  - `href` Optionally, the link target of the item. You can ".
            "    omit this if you just want a piece of text, like a copyright ".
            "    notice."))
        ->addExample($example, pht('Basic Example')),
    );
  }

}
