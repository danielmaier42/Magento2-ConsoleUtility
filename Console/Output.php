<?php

namespace DanielMaier\ConsoleUtility\Console;

use Magento\Framework\Exception\AbstractAggregateException;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Formatter\OutputFormatterInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Zend\Log\Logger;
use Zend\Log\Writer\Stream;

class Output implements OutputInterface, LoggerInterface
{
    /**
     * @var bool
     */
    protected $hasError = false;

    /**
     * @var string[]
     */
    protected $errors = [];

    /**
     * @var string
     */
    protected $fileName;

    /**
     * @var OutputInterface
     */
    private $parentOutput;

    /**
     * @var Stream
     */
    private $writer;

    /**
     * @var Logger
     */
    private $logger;

    /**
     * Output constructor.
     * @param OutputInterface $parentOutput
     */
    public function __construct(
        OutputInterface $parentOutput
    )
    {
        $this->parentOutput = $parentOutput;

        $dirName = BP . '/var/log/tmp/';

        if (!is_dir($dirName)) {
            mkdir($dirName, 0777, true);
        }

        $this->fileName = tempnam($dirName, 'console_log_');
        $this->writer = new Stream($this->fileName);
        $this->logger = new Logger();
        $this->logger->addWriter($this->writer);
    }

    //region OutputInterface

    /**
     * Writes a message to the output.
     *
     * @param string|array $messages The message as an array of lines or a single string
     * @param bool $newline Whether to add a newline
     * @param int $type The type of output (one of the OUTPUT constants)
     *
     * @throws \InvalidArgumentException When unknown output type is given
     *
     * @api
     */
    public function write($message, $newline = false, $type = OutputInterface::OUTPUT_NORMAL)
    {
        if (is_array($message) || is_object($message))
        {
            $message = var_export($message, true);
        }

        $this->fileWrite($message);

        $this->parentOutput->write($message, $newline, $type);
    }

    /**
     * Writes a message to the output and adds a newline at the end.
     *
     * @param string|array $messages The message as an array of lines of a single string
     * @param int $type The type of output (one of the OUTPUT constants)
     *
     * @throws \InvalidArgumentException When unknown output type is given
     *
     * @api
     */
    public function writeln($message, $type = OutputInterface::OUTPUT_NORMAL)
    {
        if (is_array($message) || is_object($message))
        {
            $message = var_export($message, true);
        }

        $this->fileWriteLine($message);

        $this->parentOutput->writeln($message, $type);
    }

    /**
     * Sets the verbosity of the output.
     *
     * @param int $level The level of verbosity (one of the VERBOSITY constants)
     *
     * @api
     */
    public function setVerbosity($level)
    {
        $this->parentOutput->setVerbosity($level);
    }

    /**
     * Gets the current verbosity of the output.
     *
     * @return int The current level of verbosity (one of the VERBOSITY constants)
     *
     * @api
     */
    public function getVerbosity()
    {
        return $this->parentOutput->getVerbosity();
    }

    /**
     * Sets the decorated flag.
     *
     * @param bool $decorated Whether to decorate the messages
     *
     * @api
     */
    public function setDecorated($decorated)
    {
        $this->parentOutput->setDecorated($decorated);
    }

    /**
     * Gets the decorated flag.
     *
     * @return bool true if the output will decorate messages, false otherwise
     *
     * @api
     */
    public function isDecorated()
    {
        return $this->parentOutput->isDecorated();
    }

    /**
     * Sets output formatter.
     *
     * @param OutputFormatterInterface $formatter
     *
     * @api
     */
    public function setFormatter(OutputFormatterInterface $formatter)
    {
        $this->parentOutput->setFormatter($formatter);
    }

    /**
     * Returns current output formatter instance.
     *
     * @return OutputFormatterInterface
     *
     * @api
     */
    public function getFormatter()
    {
        return $this->parentOutput->getFormatter();
    }

    /**
     * Returns whether verbosity is quiet (-q).
     *
     * @return bool true if verbosity is set to VERBOSITY_QUIET, false otherwise
     */
    public function isQuiet()
    {
        return $this->parentOutput->isQuiet();
    }

    /**
     * Returns whether verbosity is verbose (-v).
     *
     * @return bool true if verbosity is set to VERBOSITY_VERBOSE, false otherwise
     */
    public function isVerbose()
    {
        return $this->parentOutput->isVerbose();
    }

    /**
     * Returns whether verbosity is very verbose (-vv).
     *
     * @return bool true if verbosity is set to VERBOSITY_VERY_VERBOSE, false otherwise
     */
    public function isVeryVerbose()
    {
        return $this->parentOutput->isVeryVerbose();
    }

    /**
     * Returns whether verbosity is debug (-vvv).
     *
     * @return bool true if verbosity is set to VERBOSITY_DEBUG, false otherwise
     */
    public function isDebug()
    {
        return $this->parentOutput->isDebug();
    }
    //endregion

    //region Easy Logging
    public function logException(\Exception $exception)
    {
        $this->writeln('<error>' . get_class($exception) . ' - ' . $exception->getMessage() . ' (Code: ' . $exception->getCode() . ')</error>');
        $this->writeln('<error>File: ' . $exception->getFile() . ' / Line: ' . $exception->getLine() . '</error>');

        if ($exception instanceof AbstractAggregateException) {
            $this->writeln('');
            $this->writeln('The following errors occured:');

            foreach ($exception->getErrors() as $error) {
                $this->writeln('- ' . $error->getLogMessage());
            }

            $this->writeln('');
        }
    }

    public function logNotice($message)
    {
        $this->writeln('<notice>' . $message . '</notice>');
    }

    public function logSuccess($message)
    {
        $this->writeln('<info>' . $message . '</info>');
    }

    public function logError($message)
    {
        $this->errors[] = $message;

        $this->writeln('<error>Error: ' . $message . '</error>');
    }

    public function logInfo($message)
    {
        $this->writeln($message);
    }

    //endregion

    protected $fileBuffer = '';

    protected function fileWrite($messages)
    {
        if (!is_array($messages)) {
            $messages = [$messages];
        }

        foreach ($messages as $message) {
            $this->fileBuffer .= $message;
        }
    }

    protected function fileWriteLine($messages)
    {
        if (!is_array($messages)) {
            $messages = [$messages];
        }

        foreach ($messages as $message) {
            $this->fileBuffer .= $message;

            $this->logger->info($this->fileBuffer);

            $this->fileBuffer = '';
        }
    }

    public function getFileName()
    {
        return $this->fileName;
    }

    public function destroyLog()
    {
        $this->logger = null;
        $this->writer = null;

        @unlink($this->fileName);

        $this->fileName = null;
    }

    public function hasError()
    {
        return count($this->errors) > 0;
    }

    public function getErrors()
    {
        return $this->errors;
    }

    public function logEmpty()
    {
        return $this->logInfo('');
    }

    /**
     * System is unusable.
     *
     * @param string $message
     * @param array $context
     *
     * @return void
     */
    public function emergency($message, array $context = [])
    {
        $this->logError($message);
    }

    /**
     * Action must be taken immediately.
     *
     * Example: Entire website down, database unavailable, etc. This should
     * trigger the SMS alerts and wake you up.
     *
     * @param string $message
     * @param array $context
     *
     * @return void
     */
    public function alert($message, array $context = [])
    {
        $this->logError($message);
    }

    /**
     * Critical conditions.
     *
     * Example: Application component unavailable, unexpected exception.
     *
     * @param string $message
     * @param array $context
     *
     * @return void
     */
    public function critical($message, array $context = [])
    {
        $this->logError($message);
    }

    /**
     * Runtime errors that do not require immediate action but should typically
     * be logged and monitored.
     *
     * @param string $message
     * @param array $context
     *
     * @return void
     */
    public function error($message, array $context = [])
    {
        $this->logError($message);
    }

    /**
     * Exceptional occurrences that are not errors.
     *
     * Example: Use of deprecated APIs, poor use of an API, undesirable things
     * that are not necessarily wrong.
     *
     * @param string $message
     * @param array $context
     *
     * @return void
     */
    public function warning($message, array $context = [])
    {
        $this->logNotice($message);
    }

    /**
     * Normal but significant events.
     *
     * @param string $message
     * @param array $context
     *
     * @return void
     */
    public function notice($message, array $context = [])
    {
        $this->logNotice($message);
    }

    /**
     * Interesting events.
     *
     * Example: User logs in, SQL logs.
     *
     * @param string $message
     * @param array $context
     *
     * @return void
     */
    public function info($message, array $context = [])
    {
        $this->logInfo($message);
    }

    /**
     * Detailed debug information.
     *
     * @param string $message
     * @param array $context
     *
     * @return void
     */
    public function debug($message, array $context = [])
    {
        $this->logInfo($message);
    }

    /**
     * Logs with an arbitrary level.
     *
     * @param mixed $level
     * @param string $message
     * @param array $context
     *
     * @return void
     */
    public function log($level, $message, array $context = [])
    {
        $this->logInfo($message);
    }
}
