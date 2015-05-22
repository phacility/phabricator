<?php

final class FlagDeleteConduitAPIMethod extends FlagConduitAPIMethod {

  public function getAPIMethodName() {
    return 'flag.delete';
  }

  public function getMethodDescription() {
    return pht('Clear a flag.');
  }

  protected function defineParamTypes() {
    return array(
      'id'         => 'optional id',
      'objectPHID' => 'optional phid',
    );
  }

  protected function defineReturnType() {
    return 'dict | null';
  }

  protected function defineErrorTypes() {
    return array(
      'ERR_NOT_FOUND'  => pht('Bad flag ID.'),
      'ERR_WRONG_USER' => pht('You are not the creator of this flag.'),
      'ERR_NEED_PARAM' => pht('Must pass an id or an objectPHID.'),
    );
  }

  protected function execute(ConduitAPIRequest $request) {
    $id = $request->getValue('id');
    $object = $request->getValue('objectPHID');
    if ($id) {
      $flag = id(new PhabricatorFlag())->load($id);
      if (!$flag) {
        throw new ConduitException('ERR_NOT_FOUND');
      }
      if ($flag->getOwnerPHID() != $request->getUser()->getPHID()) {
        throw new ConduitException('ERR_WRONG_USER');
      }
    } else if ($object) {
      $flag = id(new PhabricatorFlag())->loadOneWhere(
        'objectPHID = %s AND ownerPHID = %s',
        $object,
        $request->getUser()->getPHID());
      if (!$flag) {
        return null;
      }
    } else {
      throw new ConduitException('ERR_NEED_PARAM');
    }
    $this->attachHandleToFlag($flag, $request->getUser());
    $ret = $this->buildFlagInfoDictionary($flag);
    $flag->delete();
    return $ret;
  }

}
