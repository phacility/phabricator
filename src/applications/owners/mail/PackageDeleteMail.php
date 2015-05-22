<?php

final class PackageDeleteMail extends PackageMail {

  protected function getVerb() {
    return pht('Deleted');
  }

  protected function isNewThread() {
    return false;
  }

}
