<?php

// define regexp for deletable sql files
$deletableFiles = array(
    'log_',
    'report_event$',
    'report_compared_product_index',
    'report_viewed_product_index',
    'index_event',
    'index_process_event',
    'catalog_product_flat_',
    'asynccache',
    'enterprise_logging_event',
    'core_cache$',
    'core_cache_tag',
    'enterprise_giftcard',
    'core_session',
    'cron_schedule',
    'sales_flat',
    'core_file_storage',
    'enterprise_customer_sales_',
    'enterprise_sales_order_grid_archive',
    'sales_payment_transaction',
    'sales_bestsellers',
);

try {
    $arguments = parseArgs();

    if (empty($arguments['sourcePath']) || !is_dir($arguments['sourcePath'])) {
        throw new Exception("Please provide a valid source path using --sourcePath=<path>");
    }

    $sourceDir = rtrim($arguments['sourcePath'], '/') . DIRECTORY_SEPARATOR . 'db' . DIRECTORY_SEPARATOR . 'latest';

    // do the magic
    processDirectory($sourceDir, $deletableFiles);

} catch (Exception $e) {
    echo "ERROR: {$e->getMessage()}\n";
    exit(1);
}

/**
 * Process source directory and remove futile files
 *
 * @param string $sourceDir
 * @return void
 */
function processDirectory($sourceDir, $deletableFiles) {

    printf("Start processing directory %s\n", $sourceDir);

    if ($dirHandle = opendir($sourceDir)) {
        $regexp = array();
        foreach ($deletableFiles as $regPart) {
            $regexp[] = '^' . $regPart . '.*?\.data.sql';
        }

        printf("Using regular expression: %s\n", implode('|', $regexp));

        while (($file = readdir($dirHandle)) !== false) {
            printf("Processing file %s\n", $sourceDir . DIRECTORY_SEPARATOR . $file);

            // only delete data files, leave structural files as is
            if (preg_match('/' . implode('|', $regexp) . '/', $file)) {
                printf("Deleting futile sql file: %s\n", $sourceDir . DIRECTORY_SEPARATOR . $file);

                if (!unlink($sourceDir . DIRECTORY_SEPARATOR . $file)) {
                    throw new Exception('Could not delete sql file: ' . $sourceDir . DIRECTORY_SEPARATOR . $file);
                }
            }
        }
        closedir($dirHandle);
        return true;
    }
    throw new Exception('Could not process given directory: ' . $sourceDir);
}

/**
 * parseArgs Command Line Interface (CLI) utility function.
 * Parse command-line arguments into array
 *
 * @static
 * @param null $argv
 * @return array
 * @usage               $args = parseArgs($_SERVER['argv']);
 * @author              Patrick Fisher <patrick@pwfisher.com>
 * @see                 https://github.com/pwfisher/CommandLine.php
 */
function parseArgs($argv = null) {
    if (is_null($argv)) {
        $argv = $_SERVER['argv'];
    }
    array_shift($argv);
    $out = array();
    foreach ($argv as $arg) {
        if (substr($arg, 0, 2) == '--') {
            $eqPos = strpos($arg, '=');
            if ($eqPos === false) {
                $key = substr($arg, 2);
                $out[$key] = isset($out[$key]) ? $out[$key] : true;
            } else {
                $key = substr($arg, 2, $eqPos - 2);
                $out[$key] = substr($arg, $eqPos + 1);
            }
        } else if (substr($arg, 0, 1) == '-') {
            if (substr($arg, 2, 1) == '=') {
                $key = substr($arg, 1, 1);
                $out[$key] = substr($arg, 3);
            } else {
                $chars = str_split(substr($arg, 1));
                foreach ($chars as $char) {
                    $key = $char;
                    $out[$key] = isset($out[$key]) ? $out[$key] : true;
                }
            }
        } else {
            $out[] = $arg;
        }
    }
    return $out;
}
