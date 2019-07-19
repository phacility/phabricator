<?php

final class PhabricatorConduitCertificateUserLogType
  extends PhabricatorUserLogType {

  const LOGTYPE = 'conduit-cert';

  public function getLogTypeName() {
    return pht('Conduit: Read Certificate');
  }

}
