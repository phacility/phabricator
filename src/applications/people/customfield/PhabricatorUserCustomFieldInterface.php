<?php

interface PhabricatorUserCustomFieldInterface {

  const ROLE_EDIT = 'user.edit';

  public function shouldAppearOnProfileEdit();

}
