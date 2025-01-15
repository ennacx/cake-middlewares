<?php
declare(strict_types=1);

namespace Ennacx\CakeMiddlewares\Middleware;

use Cake\Core\InstanceConfigTrait;
use Cake\Http\Exception\ServiceUnavailableException;
use Ennacx\CakeMiddlewares\Enum\MaintenanceCheckMethod;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class MaintenanceMiddleware implements MiddlewareInterface {

    use InstanceConfigTrait;

    private const IP_ADDR_REGEX_PATTERN = '/^(([1-9]?[0-9]|1[0-9]{2}|2[0-4][0-9]|25[0-5])\.){3}([1-9]?[0-9]|1[0-9]{2}|2[0-4][0-9]|25[0-5])\/([1-9]|1[0-9]|2[0-9]|3[0-2])$/';

    /**
     * Default config.
     *
     * @var array
     */
    private array $_defaultConfig = [
        'thru_ip_list' => [],

        'check_method' => MaintenanceCheckMethod::FLAG,

        'check_file_path' => TMP . 'maintenance',

        'is_maintenance' => false,
        'maintenance_message' => null,

        'trust_proxy' => false
    ];

    private ?string $_clientIp = null;

    public function __construct($config = []){

        $this->setConfig(array_merge_recursive($this->_defaultConfig, $config));
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface {

        if(property_exists($request, 'trustProxy') && method_exists($request, 'clientIP')){
            $request->trustProxy = $this->getConfig('trust_proxy');

            $temp = $request->clientIP();
            if(!empty($temp)){
                $this->_clientIp = $temp;
            }
            unset($temp);
        }

        if($this->_isMaintenance()){
            $message = $this->getConfig('maintenance_message');
            if(empty($message)){
                $message = null;
            }
            throw new ServiceUnavailableException($message);
        } else{
            return $handler->handle($request);
        }
    }

    /**
     * @return boolean
     */
    private function _isMaintenance(): bool {

        switch($this->getConfig('check_method')){
            case CheckMethod::FILE:
                if(!$this->_checkFile()){
                    return false;
                }
                break;

            case CheckMethod::FLAG:
                $flag = $this->_checkFlag();
                if($flag === false){
                    return false;
                }
                break;

            default:
                return false;
        }

        if($this->_isThruIP()){
            return false;
        }

        return true;
    }

    /**
     * @return bool
     */
    private function _checkFile(): bool {

        $path = $this->getConfig('check_file_path');
        if(!empty($path) && is_string($path) && file_exists($path)){
            return true;
        }

        return false;
    }

    /**
     * @return bool|null
     */
    private function _checkFlag(): ?bool {

        $flag = $this->getConfig('is_maintenance');

        return (is_bool($flag)) ? $flag : null;
    }

    /**
     * @return bool
     */
    private function _isThruIP(): bool {

        if($this->_clientIp === null){
            return false;
        }
        $clientIpLong = ip2long($this->_clientIp);
        if($clientIpLong === false){
            return false;
        }

        $thruIPArray = $this->getConfig('thru_ip_list');
        if(empty($thruIPArray)){
            return false;
        }

        foreach(array_map('trim', $thruIPArray) as $thruIP){
            // サブネットマスクが指定されていない場合 /32 を追加
            if(!str_contains($thruIP, '/')){
                $thruIP .= '/32';
            }
            // IPアドレスの書式チェック
            if(!preg_match(self::IP_ADDR_REGEX_PATTERN, $thruIP)){
                // 書式が不正
                continue;
            }
            list($ip, $mask) = explode('/', $thruIP);
            $ipLong = ip2long($ip);
            if($ipLong === false || !is_numeric($mask)){
                continue;
            }

            $bitShift = 32 - intval($mask);
            if($bitShift < 0 || $bitShift > 32){
                continue;
            }

            if(($clientIpLong >> $bitShift) === ($ipLong >> $bitShift)){
                return true;
            }
        }

        return false;
    }
}
