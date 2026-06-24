<?php

namespace BetterLinks;

use BetterLinks\Frontend\LinkChecker;

class Frontend {
    public function __construct() {
        new LinkChecker;
    }
}