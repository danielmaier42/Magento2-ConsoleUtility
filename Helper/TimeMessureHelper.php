<?php

namespace DanielMaier\ConsoleUtility\Helper;

use Magento\Framework\App\Helper\AbstractHelper;

class TimeMessureHelper extends AbstractHelper
{
    public static $MINUTE = 60;
    public static $HOUR = 60 * 60;
    public static $DAY = 60 * 60 * 24;

    /**
     * @var array
     */
    protected $_startTime = [];

    /**
     * @var array
     */
    protected $_endTime = [];

    /**
     * @param string $context
     * @return float
     */
    public function start($context = 'base')
    {
        $this->_startTime[$context] = microtime(true);
        $this->_endTime[$context] = 0;

        return $this->_startTime[$context];
    }

    /**
     * @param string $context
     * @return string
     */
    public function output($context = 'base')
    {
        $seconds = $this->duration($context);

        $dateTime = new \DateTime();
        $dateTime->setTimestamp($seconds);

        switch (true) {
            case $seconds > self::$MINUTE:
                return $dateTime->format('H:i:s');
            default:
                return number_format($seconds, 2, ',', '.') . ' seconds';
        }
    }

    /**
     * @param string $context
     * @return float
     */
    public function duration($context = 'base')
    {
        if ($this->isRunning($context)) {
            $this->end($context);
        }

        return abs($this->_endTime[$context] - $this->_startTime[$context]);
    }

    /**
     * @param string $context
     * @return bool
     */
    public function isRunning($context = 'base')
    {
        return (
            isset($this->_startTime[$context]) && $this->_endTime[$context] == 0
        );
    }

    /**
     * @param string $context
     * @return float
     */
    public function end($context = 'base')
    {
        $this->_endTime[$context] = microtime(true);

        return $this->_endTime[$context];
    }
}