<?php

/*
 * This file is part of the Packagist/Github Mirroring solution.
 *
 * (c) Ekino - Thomas Rabaix <thomas.rabaix@ekino.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

// error_reporting(E_ALL);

if (!is_file('config.php')) {
    die('Please create a config.php file');
}

include __DIR__.'/config.php';

if (!isset($_SERVER['PATH_INFO'])) {
    die('invalid package');
}

$data = build_parameters($_SERVER['PATH_INFO']);
$path = sprintf('caches/%s/%s', $data['vendor'], $data['name']);

if (!is_dir($path)) {
    mkdir($path, 0755, true);
}

$file = sprintf('%s/%s.bin', $path, hash('sha256', serialize($data)));

if (!is_file($file)) {
    rename(create_archive($data), $file);
}

if (!is_file($file)) {
    die('invalid package');
}

send_file(sprintf('%s-%s-%s.zip', $data['vendor'], $data['name'], $data['version']), $file);

$tag = preg_match('/[0-9]{1,2}\.[0-9]{1,2}\.[0-9]{1,2}/', $data['version']) === 1;

// not a sha1 or version (X.X.X) ...
if (strlen($data['version']) != 40 || !$tag) {
   unlink($file);
}

/**
 * Create the archive and return the localtion path
 * The created filed is temporary, and will be deleted by
 * the calling function
 *  
 * @return string 
 */
function create_archive(array $parameters)
{
    $out = sprintf('/tmp/php-mirroring-%s', md5(serialize($parameters).uniqid()));

    $path = sprintf('%s/%s.git', get_repository_path(), $parameters['package']);

    if (!$path || substr($path, 0, strlen(get_repository_path())) !== get_repository_path()) {
        throw new Exception("Unable to parse the request");   
    }

    if (!is_dir($path) || !is_file($path.'/config')) {
        throw new Exception("Unable to parse the request");
    }

    $cmd = sprintf('cd %s && /usr/bin/git archive %s --format=zip -o %s',
        escapeshellcmd($path),
        escapeshellcmd($parameters['version']),
        $out
     );

    system($cmd);

    if (!is_file($out)) {
        die('unable to create the file');
    }

    return $out;
}

function send_file($name, $file) {
    header('Content-Description: File Transfer');
    header('Content-Type: application/octet-stream');
    header(sprintf('Content-Disposition: attachment; filename=%s', $name));
    header('Content-Transfer-Encoding: binary');
    header('Expires: 0');
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    header('Content-Length: ' . filesize($file));

    readfile($file);
}
