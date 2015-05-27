<?php

final class FlagEditConduitAPIMethod extends FlagConduitAPIMethod {

  public function getAPIMethodName() {
    return 'flag.edit';
  }

  public function getMethodDescription() {
    return pht('Create or modify a flag.');
  }

  protected function defineParamTypes() {
    return array(
      'objectPHID' => 'required phid',
      'color'      => 'optional int',
      'note'       => 'optional string',
    );
  }

  protected function defineReturnType() {
    return 'dict';
  }

  protected function execute(ConduitAPIRequest $request) {
    $user = $request->getUser()->getPHID();
    $phid = $request->getValue('objectPHID');
    $new = false;

    $flag = id(new PhabricatorFlag())->loadOneWhere(
      'objectPHID = %s AND ownerPHID = %s',
      $phid,
      $user);
    if ($flag) {
      $params = $request->getAllParameters();
      if (isset($params['color'])) {
        $flag->setColor($params['color']);
      }
      if (isset($params['note'])) {
        $flag->setNote($params['note']);
      }
    } else {
      $default_color = PhabricatorFlagColor::COLOR_BLUE;
      $flag = id(new PhabricatorFlag())
        ->setOwnerPHID($user)
        ->setType(phid_get_type($phid))
        ->setObjectPHID($phid)
        ->setReasonPHID($user)
        ->setColor($request->getValue('color', $default_color))
        ->setNote($request->getValue('note', ''));
      $new = true;
    }
    $this->attachHandleToFlag($flag, $request->getUser());
    $flag->save();
    $ret = $this->buildFlagInfoDictionary($flag);
    $ret['new'] = $new;
    return $ret;
  }

}
