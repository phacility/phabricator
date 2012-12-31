<?php

final class PhabricatorConfigEditController
  extends PhabricatorConfigController {

  private $key;

  public function willProcessRequest(array $data) {
    $this->key = $data['key'];
  }

  public function processRequest() {
    $request = $this->getRequest();
    $user = $request->getUser();


    $options = PhabricatorApplicationConfigOptions::loadAllOptions();
    if (empty($options[$this->key])) {
      return new Aphront404Response();
    }
    $option = $options[$this->key];
    $group = $option->getGroup();
    $group_uri = $this->getApplicationURI('group/'.$group->getKey().'/');

    $issue = $request->getStr('issue');
    if ($issue) {
      // If the user came here from an open setup issue, send them back.
      $done_uri = $this->getApplicationURI('issue/'.$issue.'/');
    } else {
      $done_uri = $group_uri;
    }

    // TODO: This isn't quite correct -- we should read from the entire
    // configuration stack, ignoring database configuration. For now, though,
    // it's a reasonable approximation.
    $default_value = $option->getDefault();

    $default = $this->prettyPrintJSON($default_value);

    // Check if the config key is already stored in the database.
    // Grab the value if it is.
    $value = null;
    $config_entry = id(new PhabricatorConfigEntry())
      ->loadOneWhere(
        'configKey = %s AND namespace = %s',
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
        return id(new AphrontRedirectResponse())->setURI($done_uri);
      }

      $config_entry->setValue($value);
      $config_entry->setNamespace('default');

      if (!$errors) {
        $config_entry->save();
        return id(new AphrontRedirectResponse())->setURI($done_uri);
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
      ->addHiddenInput('issue', $request->getStr('issue'))
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
          ->addCancelButton($done_uri)
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

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs
      ->addCrumb(
        id(new PhabricatorCrumbView())
          ->setName(pht('Config'))
          ->setHref($this->getApplicationURI()))
      ->addCrumb(
        id(new PhabricatorCrumbView())
          ->setName($group->getName())
          ->setHref($group_uri))
      ->addCrumb(
        id(new PhabricatorCrumbView())
          ->setName($this->key)
          ->setHref('/config/edit/'.$this->key));

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
