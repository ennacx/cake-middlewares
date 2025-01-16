<?php
declare(strict_types=1);

namespace Ennacx\CakeMiddlewares\Utils;

use RuntimeException;

/**
 * Utility to switch to, cancel, toggle, and check maintenance mode
 */
final class MaintenanceSwitch {

    /**
     * Maintenance flag file path
     *
     * @var string
     */
    private string $_maintenanceFilePath = TMP . 'maintenance';

    /**
     * Singleton
     *
     * @param string|null $maintenanceFilePath
     */
    private function __construct(?string $maintenanceFilePath = null){

        if($maintenanceFilePath !== null){
            $this->_maintenanceFilePath = $maintenanceFilePath;
        }
    }

    /**
     * Get MaintenanceSwitch instance
     *
     * @param  string|null $maintenanceFilePath
     * @return self
     */
    public static function getInstance(?string $maintenanceFilePath = null): self {

        static $instance;
        if(!isset($instance)){
            $instance = new self($maintenanceFilePath);
        }

        return $instance;
    }

    /**
     * Entering maintenance mode
     *
     * @return boolean
     * @throws RuntimeException
     */
    public function on(): bool {

        if(file_exists($this->_maintenanceFilePath)){
            return false;
        }

        $result = file_put_contents($this->_maintenanceFilePath, '', LOCK_EX);

        if($result === false){
            throw new RuntimeException(sprintf('Maintenance flag file cannot create! Check permission. [%s]', $this->_maintenanceFilePath));
        }

        return true;
    }

    /**
     * Turn off maintenance mode
     *
     * @return boolean
     * @throws RuntimeException
     */
    public function off(): bool {

        if(!file_exists($this->_maintenanceFilePath)){
            return false;
        }

        $result = self::removeFile($this->_maintenanceFilePath);
        if(!$result){
            throw new RuntimeException(sprintf('Maintenance flag file cannot delete! Dead locking. [%s]', $this->_maintenanceFilePath));
        }

        return true;
    }

    /**
     * Toggle maintenance mode
     *
     * @return boolean
     * @throws RuntimeException
     */
    public function toggle(): bool {
        return ($this->isMaintenance()) ? $this->off() : $this->on();
    }

    /**
     * Check if in maintenance mode
     *
     * @return boolean
     */
    public function isMaintenance(): bool {
        return (file_exists($this->_maintenanceFilePath));
    }

    /**
     * Remove to Maintenance flag file
     *
     * @param  string  $filePath
     * @return boolean
     */
    private static function removeFile(string $filePath): bool {

        // 存在しない場合
        if(!file_exists($filePath) || !is_file($filePath)){
            return true;
        }

        return unlink($filePath);
    }
}
