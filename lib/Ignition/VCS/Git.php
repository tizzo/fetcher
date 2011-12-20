<?php

namespace Ignition\VCS;

class Git {

  public function update($localDirectory, $label = '') {
    //update = "cd %s; git checkout %s; git pull; git submodule update --init" % (localDirectory, label)
  }
}
