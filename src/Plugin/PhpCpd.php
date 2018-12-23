<?php

namespace PHPCensor\Plugin;

use PHPCensor\Builder;
use PHPCensor\Model\Build;
use PHPCensor\Model\BuildError;
use PHPCensor\Plugin;
use PHPCensor\ZeroConfigPluginInterface;

/**
 * PHP Copy / Paste Detector - Allows PHP Copy / Paste Detector testing.
 *
 * @author Dan Cryer <dan@block8.co.uk>
 */
class PhpCpd extends Plugin implements ZeroConfigPluginInterface
{
    /**
     * @var string, based on the assumption the root may not hold the code to be
     * tested, extends the base directory
     */
    protected $directory;

    /**
     * @var string
     */
    protected $executable;
    /**
     * @var array - paths to ignore
     */
    protected $ignore;

    /**
     * @return string
     */
    public static function pluginName()
    {
        return 'php_cpd';
    }

    /**
     * {@inheritdoc}
     */
    public function __construct(Builder $builder, Build $build, array $options = [])
    {
        parent::__construct($builder, $build, $options);

        /** @deprecated Option "path" deprecated and will be deleted in version 2.0 (Use option "directory" instead)! */
        if (isset($options['path']) && !isset($options['directory'])) {
            $this->builder->logWarning(
                '[DEPRECATED] Option "path" deprecated and will be deleted in version 2.0 (Use option "directory" instead)!'
            );

            $options['directory'] = $options['path'];
        }

        $this->directory = $this->getWorkingDirectory($options);

        $this->builder->logDebug('Directory : '.$this->directory);
        $this->executable = $this->findBinary('phpcpd');

        // only subdirectory of $this->directory can be ignored, and string must not include root
        if (array_key_exists('ignore', $options)) {
            $this->ignore = $this->ignorePathRelativeToDirectory(
                $this->directory,
                array_merge($this->builder->ignore, $options['ignore'])
            );
        } else {
            $this->ignore = $this->ignorePathRelativeToDirectory($this->directory, $this->builder->ignore);
        }
    }

    /**
     * {@inheritdoc}
     */
    public static function canExecuteOnStage($stage, Build $build)
    {
        if (Build::STAGE_TEST === $stage) {
            return true;
        }

        return false;
    }

    /**
     * Runs PHP Copy/Paste Detector in a specified directory.
     */
    public function execute()
    {
        $ignore       = '';
        $namesExclude = ' --names-exclude ';
        if (is_array($this->ignore)) {
            foreach ($this->ignore as $item) {
                $item = rtrim($item, '/');
                if (is_file(rtrim($this->directory, '/') . '/' . $item)) {
                    $ignoredFile     = explode('/', $item);
                    $filesToIgnore[] = array_pop($ignoredFile);
                } else {
                    $ignore .= ' --exclude ' . $item;
                }
            }
        }

        if (isset($filesToIgnore)) {
            $filesToIgnore = $namesExclude . implode(',', $filesToIgnore);
            $ignore        = $ignore . $filesToIgnore;
        }

        $phpcpd = $this->executable;

        $tmpFileName = tempnam(sys_get_temp_dir(), (self::pluginName() . '_'));

        $cmd     = $phpcpd . ' --log-pmd "%s" %s "%s"';
        $success = $this->builder->executeCommand($cmd, $tmpFileName, $ignore, $this->directory);

        $errorCount = $this->processReport(file_get_contents($tmpFileName));

        $this->build->storeMeta((self::pluginName() . '-warnings'), $errorCount);

        unlink($tmpFileName);

        return $success;
    }

    /**
     * Process the PHPCPD XML report.
     *
     * @param $xmlString
     *
     * @return integer
     *
     * @throws \Exception
     */
    protected function processReport($xmlString)
    {
        $xml = simplexml_load_string($xmlString);

        if (false === $xml) {
            $this->builder->log($xmlString);
            throw new \Exception('Could not process the report generated by PHPCpd.');
        }

        $warnings = 0;
        foreach ($xml->duplication as $duplication) {
            foreach ($duplication->file as $file) {
                $fileName = (string) $file['path'];
                $fileName = str_replace($this->builder->buildPath, '', $fileName);

                $message = <<<CPD
Copy and paste detected:

```
{$duplication->codefragment}
```
CPD;

                $this->build->reportError(
                    $this->builder,
                    self::pluginName(),
                    $message,
                    BuildError::SEVERITY_NORMAL,
                    $fileName,
                    (int) $file['line'],
                    (int) $file['line'] + (int) $duplication['lines']
                );
            }

            $warnings++;
        }

        return $warnings;
    }
}
