<?php

final class PhabricatorConfigEditController
  extends PhabricatorConfigController {

  private $key;

  public function willProcessRequest(array $data) {
    $this->key = idx($data, 'key');
  }

  public function processRequest() {
    $request = $this->getRequest();
    $user = $request->getUser();

    $config = id(new PhabricatorConfigFileSource('default'))
      ->getAllKeys();
    if (!$this->key || !array_key_exists($this->key, $config)) {
      return new Aphront404Response();
    }

    $default = $this->prettyPrintJSON($config[$this->key]);

    // Check if the config key is already stored in the database.
    // Grab the value if it is.
    $value = null;
    $config_entry = id(new PhabricatorConfigEntry())
      ->loadOneWhere(
        'configKey = %s AND namespace=%s',
        $this->key,
        'default');
    if ($config_entry) {
      $value = $config_entry->getValue();
    } else {
      $config_entry = id(new PhabricatorConfigEntry())
        ->setConfigKey($this->key);
    }

    $e_value = null;
    $errors = array();
    if ($request->isFormPost()) {
      $new_value = $request->getStr('value');
      if (strlen($new_value)) {
        $json = json_decode($new_value, true);
        if ($json === null && strtolower($new_value) != 'null') {
          $e_value = 'Invalid';
          $errors[] = 'The given value must be valid JSON. This means, among '.
            'other things, that you must wrap strings in double-quotes.';
          $value = $new_value;
        } else {
          $value = $json;
        }
      } else {
        // TODO: When we do Transactions, make this just set isDeleted = 1
        $config_entry->delete();
        return id(new AphrontRedirectResponse())
          ->setURI($config_entry->getURI());
      }

      $config_entry->setValue($value);
      $config_entry->setNamespace('default');

      if (!$errors) {
        $config_entry->save();
        return id(new AphrontRedirectResponse())
          ->setURI($config_entry->getURI());
      }
    }

    $form = new AphrontFormView();
    $form->setFlexible(true);

    $error_view = null;
    if ($errors) {
      $error_view = id(new AphrontErrorView())
        ->setTitle('You broke everything!')
        ->setErrors($errors);
    } else {
      $value = $this->prettyPrintJSON($value);
    }

    $form
      ->setUser($user)
      ->appendChild(
        id(new AphrontFormTextAreaControl())
          ->setLabel('JSON Value')
          ->setError($e_value)
          ->setValue($value)
          ->setHeight(AphrontFormTextAreaControl::HEIGHT_VERY_TALL)
          ->setCustomClass('PhabricatorMonospaced')
        ->setName('value'))
      ->appendChild(
        id(new AphrontFormSubmitControl())
          ->addCancelButton($config_entry->getURI())
          ->setValue(pht('Save Config Entry')))
      ->appendChild(
        phutil_render_tag(
          'p',
          array(
            'class' => 'aphront-form-input',
          ),
          'If left blank, the setting will return to its default value. '.
          'Its default value is:'))
      ->appendChild(
          phutil_render_tag(
            'pre',
            array(
              'class' => 'aphront-form-input',
            ),
            phutil_escape_html($default)));

      $title = pht('Edit %s', $this->key);
      $short = pht('Edit');

    $crumbs = $this->buildApplicationCrumbs($this->buildSideNavView());
    $crumbs->addCrumb(
      id(new PhabricatorCrumbView())
        ->setName($this->key)
        ->setHref('/config/edit/'.$this->key));
    $crumbs->addCrumb(
      id(new PhabricatorCrumbView())->setName($short));

    return $this->buildApplicationPage(
      array(
        $crumbs,
        id(new PhabricatorHeaderView())->setHeader($title),
        $error_view,
        $form,
      ),
      array(
        'title' => $title,
        'device' => true,
      ));
  }

}
