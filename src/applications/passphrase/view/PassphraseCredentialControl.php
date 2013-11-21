<?php

final class PassphraseCredentialControl extends AphrontFormControl {

  private $options;
  private $credentialType;

  public function setCredentialType($credential_type) {
    $this->credentialType = $credential_type;
    return $this;
  }

  public function getCredentialType() {
    return $this->credentialType;
  }

  public function setOptions(array $options) {
    assert_instances_of($options, 'PassphraseCredential');
    $this->options = $options;
    return $this;
  }

  protected function getCustomControlClass() {
    return 'passphrase-credential-control';
  }

  protected function renderInput() {

    $options_map = array();
    foreach ($this->options as $option) {
      $options_map[$option->getPHID()] = pht(
        "%s %s",
        'K'.$option->getID(),
        $option->getName());
    }

    $disabled = $this->getDisabled();
    if (!$options_map) {
      $options_map[''] = pht('(No Existing Credentials)');
      $disabled = true;
    }

    Javelin::initBehavior('passphrase-credential-control');

    $options = AphrontFormSelectControl::renderSelectTag(
      $this->getValue(),
      $options_map,
      array(
        'id' => $this->getControlID(),
        'name' => $this->getName(),
        'disabled' => $disabled ? 'disabled' : null,
        'sigil' => 'passphrase-credential-select',
      ));

    $button = javelin_tag(
      'a',
      array(
        'href' => '#',
        'class' => 'button grey',
        'sigil' => 'passphrase-credential-add',
        'mustcapture' => true,
      ),
      pht('Add Credential'));

    return javelin_tag(
      'div',
      array(
        'sigil' => 'passphrase-credential-control',
        'meta' => array(
          'type' => $this->getCredentialType(),
        ),
      ),
      array(
        $options,
        $button,
      ));
  }

}
