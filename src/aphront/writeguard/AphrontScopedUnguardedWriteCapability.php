<?php

final class AphrontScopedUnguardedWriteCapability extends Phobject {

  public function __destruct() {
    AphrontWriteGuard::endUnguardedWrites();
  }

}
