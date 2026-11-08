<?php

declare(strict_types=1);

/*
 * Downloads the Biome binary for this machine through kocal/biome-js-bundle, without a Symfony kernel: phil-dev js
 * runs it with the autoloader of vendor-bin/biome, the directory to write to and the version.
 *
 *     php download.php vendor-bin/biome/vendor/autoload.php var/phil-dev/bin 2.6.6
 */
if ($argc !== 4) {
    fwrite(STDERR, "Usage: php download.php <autoload.php> <directory> <version>\n");

    exit(2);
}

[, $autoload, $directory, $version] = $argv;

if (!is_file($autoload)) {
    fwrite(STDERR, "Run composer bin biome install first: {$autoload} does not exist.\n");

    exit(1);
}

require $autoload;

$command = new Kocal\BiomeJsBundle\Command\BiomeJsDownloadCommand($version, new Symfony\Component\Filesystem\Filesystem());

exit($command->run(new Symfony\Component\Console\Input\ArrayInput(['destination-dir' => $directory]), new Symfony\Component\Console\Output\ConsoleOutput()));
