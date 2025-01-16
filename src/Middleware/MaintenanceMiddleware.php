<?php
declare(strict_types=1);

namespace Ennacx\CakeMiddlewares\Middleware;

use Cake\Http\Exception\ServiceUnavailableException;
use Ennacx\CakeMiddlewares\Enum\MaintenanceCheckMethod;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Middleware that determines whether maintenance is in progress
 */
class MaintenanceMiddleware implements MiddlewareInterface {

    /** @var string Regex that determines IPv4 (contain mask length) */
    private const IP_ADDR_REGEX_PATTERN = '/^(([1-9]?[0-9]|1[0-9]{2}|2[0-4][0-9]|25[0-5])\.){3}([1-9]?[0-9]|1[0-9]{2}|2[0-4][0-9]|25[0-5])\/([1-9]|1[0-9]|2[0-9]|3[0-2])$/';

    /**
     * Default config
     *
     * @var array
     */
    private array $_defaultConfig = [
        'thru_ip_list' => [],

        'check_method' => MaintenanceCheckMethod::FLAG,

        'check_file_path' => TMP . 'maintenance',

        'is_maintenance' => false,

        'check_date_from'   => null,
        'check_date_to'     => null,
        'check_date_format' => 'Y-m-d H:i:s',

        'maintenance_message' => null,

        'use_proxy' => false
    ];

    /**
     * Apply config
     *
     * @var array
     */
    private array $_config;

    /**
     * Access source IP
     *
     * @var string|null
     */
    private ?string $_clientIp = null;

    /**
     * MaintenanceMiddleware constructor
     *
     * @param array $config
     */
    public function __construct(array $config = []){

        $this->_config = array_merge($this->_defaultConfig, $config);
    }

    /**
     * Middleware main routine
     *
     * @param  ServerRequestInterface  $request
     * @param  RequestHandlerInterface $handler
     * @return ResponseInterface
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface {

        // クライアントIPの取得
        if(property_exists($request, 'trustProxy') && method_exists($request, 'clientIP')){
            $request->trustProxy = $this->_config['use_proxy'];

            $temp = $request->clientIP();
            if(!empty($temp)){
                $this->_clientIp = $temp;
            }
            unset($temp);
        }

        // メンテナンス状態かを判断
        if($this->_isMaintenance()){
            $message = $this->_config['maintenance_message'];
            if(empty($message)){
                $message = null;
            }
            throw new ServiceUnavailableException($message);
        } else{
            return $handler->handle($request);
        }
    }

    /**
     * Determine if your app is under maintenance
     *
     * @return boolean True: Maintenance mode / False: Operation mode
     */
    private function _isMaintenance(): bool {

        switch($this->_config['check_method']){
            // ファイルでの判定
            case MaintenanceCheckMethod::FILE:
                if(!$this->_checkFile()){
                    return false;
                }
                break;

            // フラグでの判定
            case MaintenanceCheckMethod::FLAG:
                $flag = $this->_checkFlag();
                if($flag === false){
                    return false;
                }
                break;

            // 日時での判定
            case MaintenanceCheckMethod::DATE:
                if(!$this->_checkDate()){
                    return false;
                }
                break;

            // enum以外を指定した場合
            default:
                return false;
        }

        // スルーできるIPか判定
        if($this->_isThruIP()){
            return false;
        }

        return true;
    }

    /**
     * Check if maintenance mode is enabled by checking if the file exists.
     *
     * @return boolean True: File exists / False: File not exists
     */
    private function _checkFile(): bool {

        $path = $this->_config['check_file_path'];

        return (!empty($path) && is_string($path) && file_exists($path));
    }

    /**
     * The configuration flag value determines whether the device is under maintenance.
     *
     * @return bool|null If it is ```null```, the specified value is invalid.
     */
    private function _checkFlag(): ?bool {

        $flag = $this->_config['is_maintenance'];

        return (is_bool($flag)) ? $flag : null;
    }

    /**
     * Determine whether the maintenance status is in effect within the specified datetime range.
     *
     * @return boolean
     */
    private function _checkDate(): bool {

        // いずれもnullであれば終了
        if($this->_config['check_date_from'] === null && $this->_config['check_date_to'] === null){
            return false;
        }
        // 書式が文字列でなければ終了
        else if(is_string($this->_config['check_date_format'])){
            return false;
        }

        $now  = new \DateTime();
        $from = ($this->_config['check_date_from'] !== null) ? \DateTime::createFromFormat($this->_config['check_date_format'], strval($this->_config['check_date_from'])) : null;
        $to   = ($this->_config['check_date_to']   !== null) ? \DateTime::createFromFormat($this->_config['check_date_format'], strval($this->_config['check_date_to']))   : null;

        // DateTime生成不正で終了
        if($from === false || $to === false){
            return false;
        }

        if($from !== null && $to !== null){
            if($from > $to){
                $temp = clone $from;
                $from = clone $to;
                $to   = clone $temp;
                unset($temp);
            }

            if($now >= $from && $now <= $to){
                return true;
            }
        } else if($from !== null && $now >= $from){
            return true;
        } else if($to !== null && $now <= $to){
            return true;
        }

        return false;
    }

    /**
     * Determine whether the IP address can pass through even during maintenance.
     *
     * @return boolean
     */
    private function _isThruIP(): bool {

        if($this->_clientIp === null){
            return false;
        }
        $clientIpLong = ip2long($this->_clientIp);
        if($clientIpLong === false){
            return false;
        }

        $thruIPArray = $this->_config['thru_ip_list'];
        if(empty($thruIPArray)){
            return false;
        }

        foreach(array_map('trim', $thruIPArray) as $thruIP){
            if(!str_contains($thruIP, '/')){
                $thruIP .= '/32';
            }

            if(!preg_match(self::IP_ADDR_REGEX_PATTERN, $thruIP)){
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
