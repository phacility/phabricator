<?php

final class PhabricatorProfileHeaderView extends AphrontView {

  protected $profilePicture;
  protected $profileName;
  protected $profileDescription;
  protected $profileActions = array();
  protected $profileStatus;

  public function setProfilePicture($picture) {
    $this->profilePicture = $picture;
    return $this;
  }

  public function setName($name) {
    $this->profileName = $name;
    return $this;
  }

  public function setDescription($description) {
    $this->profileDescription = $description;
    return $this;
  }

  public function addAction($action) {
    $this->profileActions[] = $action;
    return $this;
  }

  public function setStatus($status) {
    $this->profileStatus = $status;
    return $this;
  }

  public function render() {
    require_celerity_resource('phabricator-profile-header-css');

    $image = null;
    if ($this->profilePicture) {
      $image = phutil_render_tag(
        'div',
        array(
          'class' => 'profile-header-picture-frame',
          'style' => 'background-image: url('.$this->profilePicture.');',
        ),
        '');
    }

    $description = phutil_escape_html($this->profileDescription);
    if ($this->profileStatus != '') {
      $description =
        '<strong>'.phutil_escape_html($this->profileStatus).'</strong>'.
        ($description != '' ? ' &mdash; ' : '').
        $description;
    }

    return
      '<table class="phabricator-profile-header">
        <tr>
          <td class="profile-header-name">'.
            phutil_escape_html($this->profileName).
          '</td>
          <td class="profile-header-actions" rowspan="2">'.
            self::renderSingleView($this->profileActions).
          '</td>
          <td class="profile-header-picture" rowspan="2">'.
            $image.
          '</td>
        </tr>
        <tr>
          <td class="profile-header-description">'.
            $description.
          '</td>
        </tr>
      </table>'.
      $this->renderChildren();
  }
}
