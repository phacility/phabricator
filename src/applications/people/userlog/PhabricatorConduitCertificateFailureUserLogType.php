<?php

final class PhabricatorConduitCertificateFailureUserLogType
  extends PhabricatorUserLogType {

  const LOGTYPE = 'conduit-cert-fail';

  public function getLogTypeName() {
    return pht('Conduit: Read Certificate Failure');
  }

}
