<?php

/*
 * This file is part of the Packagist/Github Mirroring solution.
 *
 * (c) Ekino - Thomas Rabaix <thomas.rabaix@ekino.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

error_reporting(E_ALL);

if (php_sapi_name() !== 'cli') {
   die('This script must run from the command line');
}

if (!is_file(__DIR__.'/config.php')) {
    die('Please create a config.php file');
}

ini_set('memory_limit','256M');

include __DIR__.'/config.php';


echo "step 1 : retrieving composer.phar\n";
file_put_contents('composer.phar', file_get_contents('http://getcomposer.org/composer.phar'));

echo "step 2: retrieving packages.json - updated packages\n";
list($packages,) = download_file('packages.json', array());

echo "step 3: retrieving packages definition \n";

foreach ($packages['provider-includes'] as $file => &$options) {
    list($content, $algo, ) = download_file($file, $options);

    if ($content === false) {
        continue; // fail to download
    }

    if (isset($content['packages'])) {
        $content['packages'] = update_packages($content['packages']);
    } else {
        // legacy repo handling
        $content['providers'] = update_providers($content['providers']);
    }

    $options[$algo] = store_content($file, $content, $algo);
}

store_content('packages.json', $packages, 'sha256');

echo "step 4: have a beer!\n";

/**
 * Iterate over package and fix references
 *
 * @param array $packages
 *
 * @return array the altered packages array
 */
function update_packages(array $packages)
{
    foreach ($packages as $package => &$versions) {
        foreach ($versions as $version => &$metadata) {
            if (include_source()) {
                if (isset($metadata['source'])) {
                    $metadata['source']['url'] = replace_source_host($metadata['source']['url']);
                }
            } else {
                unset($metadata['source']);
            }

            if (include_dist()) {
                if (isset($metadata['dist'])) {
                    $metadata['dist']['url'] = replace_dist_host($metadata);
                }
            } else {
                unset($metadata['dist']);
            }
        }
    }

    return $packages;
}

/**
 * Loop providers to retrieve the different packages, and update the hash value
 *
 * @param array $providers
 *
 * @return array the altered providers array
 */
function update_providers(array $providers)
{
    foreach ($providers as $provider => &$options) {
        list($content, $algo, ) = download_file($provider, $options);

        if ($content === false) {
            continue; // fail to download, ignore the package
        }

        if (is_array($content['packages'])) {
            $content['packages'] = update_packages($content['packages']);
        }

        $options[$algo] = store_content($provider, $content, $algo);
    }

    return $providers;
}

/**
 * @param string $file file name
 * @param array  $content the content to store
 * @param string $algo
 *
 * @return string
 */
function store_content($file, array $content, $algo)
{
    if (substr($file, -5) != '.json') {
        $file = 'p/'.$file . ".json";
    }

    $content = json_encode($content);
    $hash = hash($algo, $content);

    $path = dirname($file);

    if (!is_dir($path)) {
        mkdir($path, 0755, true);
    }

    if (strpos($file, '%hash%') !== false) {
        $file = str_replace('%hash%', $hash, $file);
    } else {
        file_put_contents($file, $content);

        $file = sprintf("%s$%s.json",
            str_replace('.json', '', $file),
            $hash
        );
    }

    file_put_contents($file, $content);

    return $hash;
}

/**
 * Download a json file from packagist, the code also a use case where the package name is
 * provided.
 *
 * @param string $file the file to download
 * @param array  $hash the information
 */
function download_file($file, array $hash)
{
    if (substr($file, -5) != '.json') {
        $file = 'p/'.$file . ".json";
    }

    $t = $hash;
    $algo = isset($hash['sha1']) ? 'sha1' : 'sha256';
    $hash = isset($hash[$algo]) ? $hash[$algo] : null;

    if ($algo == 'sha256') {
        $file = str_replace('%hash%', $hash, $file);
    }

    $target = 'packagist/'.$file;

    $path = dirname($file);

    if (!is_dir('packagist/'.$path)) {
        mkdir('packagist/'.$path, 0755, true);
    }

    if (!is_file($target) || hash_file($algo, $target) != $hash) {
        echo sprintf("  > Retrieving %- 60s => %s \n", 'http://packagist.org/'.$file, $target);

        $content = @file_get_contents('http://packagist.org/'.$file);

        if (!$content) {
            echo "Unable to retrieve the file http://packagist.org/$file\n";

            return array(false, false, false);
        }

        file_put_contents($target, $content);
    }

    return array(json_decode(file_get_contents($target), true), $algo, 'packagist/'.$file);
}

if (!function_exists('include_dist')) {
    /**
     * Return true if you want to include the dist array
     * The dist array is used to download the archive version (zip)
     *
     * @return boolean
     */
    function include_dist() {
        return true;
    }

}

if (!function_exists('include_source')) {
    /**
     * Return true if you want to include the source array
     * The source array is used to download from git/subversion
     *
     * @return boolean
     */
    function include_source() {
        return true;
    }
}
