<?php

// The classes of the libraries the fixture uses, reduced to what the taint model names.
foreach (glob(__DIR__.'/stand-ins/*.php') ?: [] as $file) {
    require_once $file;
}
