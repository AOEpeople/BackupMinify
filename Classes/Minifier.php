<?php

/**
 * Systemstorage minifier
 *
 * @author Fabrizio Branca <fabrizio.branca@aoemedia.de>
 * @author Daniel Pötzinger <daniel.poetzinger@aoemedia.de>
 * @since 2012-06-30
 */
class BackupMinify_Minifier {

	protected $sourcePath;
	protected $targetPath;
	protected $imageConvertBinary = '/usr/bin/convert';
	protected $imageConvertCommandTemplate = ' -quality 1 "%1$s" "%2$s"';
	protected $imageFileTypes = array('jpg', 'png');
	protected $totalNumberOfFiles;
	protected $statistics = array(
		'total_files' => 0,
		'skipped' => 0,
		'converted' => 0,
		'directories_created' => 0,
		'copied' => 0
	);
	protected $durationsStack = array();

	/**
	 * Constructor
	 *
	 * @param string $sourcePath
	 * @param string $targetPath
	 * @throws Exception
	 */
	public function __construct($sourcePath, $targetPath) {
		if (empty($sourcePath)) {
			throw new Exception("Please provide a source path using --source=<path>");
		}
		if (empty($targetPath)) {
			throw new Exception("Please provide a target path using --target=<path>");
		}

		if (!is_executable($this->imageConvertBinary)) {
			throw new Exception("The image convert executable ". $this->imageConvertBinary." does not exist or cannot be executed.");
		}

		// force ending slash
		$sourcePath = rtrim($sourcePath, '/') . '/';
		$targetPath = rtrim($targetPath, '/') . '/';

		if (!is_dir($sourcePath)) {
			throw new Exception("Could not find source dir '$sourcePath'");
		}
		if (!is_dir($targetPath)) {
			throw new Exception("Could not find target dir '$targetPath'");
		}
		$this->sourcePath = $sourcePath;
		$this->targetPath = $targetPath;
	}

	/**
	 * Get total number of files in source path
	 *
	 * @return int
	 */
	protected function getTotalNumberOfFiles() {
		if (is_null($this->totalNumberOfFiles)) {
			$this->totalNumberOfFiles = trim(shell_exec("find '{$this->sourcePath}' -type f | wc -l"));
		}
		return $this->totalNumberOfFiles;
	}

	/**
	 * Get iterator
	 *
	 * @return RecursiveIteratorIterator
	 */
	protected function getIterator() {
		return new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->sourcePath));
	}

	/**
	 * Run minifier
	 */
	public function run() {
		foreach ($this->getIterator() as $filename => $cur) { /* @var $cur FileInfoSpl */
			if ($cur->isDir()) { continue; }
			$relativeFileName = str_replace($this->sourcePath, '', $filename);
			$targetFileName = $this->targetPath . $relativeFileName;
			$this->statistics['total_files']++;
			if (is_file($targetFileName)) {
				$this->statistics['skipped']++;
				printf("[%s/%s] Skipping file: %s (already exists)\n",
					$this->statistics['total_files'],
					$this->getTotalNumberOfFiles(),
					$filename
				);
				continue;
			}
            if ($cur->isLink()) {
                echo "Symlink created: $targetFileName\n";
                symlink($targetFileName, $cur->getLinkTarget());
                continue;
            }

			$pathInfo = pathinfo($filename);
			$dirname = dirname($targetFileName);
			if (!is_dir($dirname)) {
				$this->statistics['directories_created']++;
				echo "Creating directory: $dirname\n";
				mkdir($dirname, 0777, true);
			}

			if ($this->convertImageFiles($pathInfo, $filename, $targetFileName)) {
				continue; //next file
			}
			if ($this->convertPDFFiles($pathInfo, $filename, $targetFileName)) {
				continue; //next file
			}


			// Creating a hard link for non-image files
			$linkResult = @link($filename, $targetFileName);
			if (!$linkResult) {
				printf("Linking file failed: %s to %s \n",$filename,$targetFileName);
				$copyResult = copy($filename, $targetFileName);
				if (!$copyResult) {
					throw new Exception( sprintf("Copy file failed too: %s to %s \n",$filename,$targetFileName) );
				}
			}

			$this->statistics['copied']++;
			printf("[%s/%s] Copying file: %s\n",
				$this->statistics['total_files'],
				$this->getTotalNumberOfFiles(),
				$filename
			);

		}
	}

	/**
	 * @param array $pathInfo
	 * @param $filename
	 * @param $targetFileName
	 * @return bool
	 */
	protected function convertPDFFiles(array $pathInfo, $filename, $targetFileName) {
		if ($pathInfo['extension'] != 'pdf') {
			return false;
		}

		$this->statistics['converted']++;

		$startTime = microtime(true);

		// Convert...
		copy(dirname(__FILE__).'/../Resources/dummy.pdf', $targetFileName);

		$this->putDurationOnStack(microtime(true) - $startTime);

		$conversionsPerMinute = $this->getConversionsPerMinute();
		$eta = $this->getEta($conversionsPerMinute);

		printf("[%s/%s] Converted PDF file: %s (%s cpm, ETA: %s:%02s h)\n",
			$this->statistics['total_files'],
			$this->getTotalNumberOfFiles(),
			$filename,
			round($conversionsPerMinute),
			(int)($eta / 60),
				$eta % 60
		);
		return true;
	}

	/**
	 * @param array $pathInfo
	 * @param string $filename
	 * @param string $targetFileName
	 * @return bool
	 */
	protected function convertImageFiles(array $pathInfo, $filename, $targetFileName) {
		if (!in_array(strtolower($pathInfo['extension']), $this->imageFileTypes)) {
			return false;
		}

		$this->statistics['converted']++;

		$startTime = microtime(true);

		// Convert...
		exec(sprintf($this->imageConvertBinary.$this->imageConvertCommandTemplate, $filename, $targetFileName));

		$this->putDurationOnStack(microtime(true) - $startTime);

		$conversionsPerMinute = $this->getConversionsPerMinute();
		$eta = $this->getEta($conversionsPerMinute);

		printf("[%s/%s] Converted Image file: %s (%s cpm, ETA: %s:%02s h)\n",
			$this->statistics['total_files'],
			$this->getTotalNumberOfFiles(),
			$filename,
			round($conversionsPerMinute),
			(int)($eta / 60),
				$eta % 60
		);
		return true;
	}

	/**
	 * @param $duration
	 */
	protected function putDurationOnStack($duration) {
		$this->durationsStack[] = $duration;
		if (count($this->durationsStack) > 100) {
			array_shift($this->durationsStack);
		}
	}

	protected function getAvgDuration() {
		return array_sum($this->durationsStack) / count($this->durationsStack);
	}

	protected function getConversionsPerMinute() {
		return 60 / $this->getAvgDuration();
	}

	protected function getEta($conversionsPerMinute) {
		$filesLeft = $this->getTotalNumberOfFiles() - $this->statistics['total_files'];
		return $filesLeft / $conversionsPerMinute;
	}



}


