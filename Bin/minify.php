<?php

require_once(dirname(__FILE__) . '/../Classes/Minifier.php');

try {
	$parsedArguments = parseArgs();

	if (empty($parsedArguments['source'])) {
		throw new Exception("Please provide a source path using --source=<path>");
	}
	if (empty($parsedArguments['target'])) {
		throw new Exception("Please provide a target path using --target=<path>");
	}

	$minifier = new BackupMinify_Minifier($parsedArguments['source'], $parsedArguments['target']);
	if (isset($parsedArguments['skipExistingFiles']) && $parsedArguments['skipExistingFiles'] == 0) {
		$minifier->setSkipExistingFiles(false);
	}
	if (isset($parsedArguments['quiteMode']) && $parsedArguments['quiteMode'] == 1) echo 'Typo detected. Please change "quiteMode" option to "quietMode".';
	if ((isset($parsedArguments['quiteMode']) && $parsedArguments['quiteMode'] == 1) || (isset($parsedArguments['quietMode']) && $parsedArguments['quietMode'] == 1)) {
		$minifier->setQuietMode(true);
	}

	$minifier->setImageConvertBinary(BackupMinify_Minifier::IMAGE_MAGICK_CONVERT_BINARY);
	if (isset($parsedArguments['imageconverter'])) {
		$parsedArguments['imageconverter'] = strtolower($parsedArguments['imageconverter']);

		switch ($parsedArguments['imageconverter']) {
			case 'imagemagick':
				$minifier->setImageConvertBinary(BackupMinify_Minifier::IMAGE_MAGICK_CONVERT_BINARY);
				break;
			case 'im':
				$minifier->setImageConvertBinary(BackupMinify_Minifier::IMAGE_MAGICK_CONVERT_BINARY);
				break;
			case 'graphicsmagick':
				$minifier->setImageConvertBinary(BackupMinify_Minifier::GRAPHICS_MAGICK_CONVERT_BINARY);
				break;
			case 'gm':
				$minifier->setImageConvertBinary(BackupMinify_Minifier::GRAPHICS_MAGICK_CONVERT_BINARY);
				break;
			default:
				throw new Exception("Please provide a valid image converter --imageconverter=<imageconverter>");
		}
	}

	$minifier->run();
} catch (Exception $e) {
	echo "ERROR: {$e->getMessage()}\n\n";
	exit(1);
}



/**
 * Parse command-line arguments into array
 *
 * @static
 * @param null $argv
 * @return array
 * @see http://www.php.net/manual/de/features.commandline.php#93086
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
