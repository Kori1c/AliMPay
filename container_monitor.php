<?php
require_once __DIR__ . '/vendor/autoload.php';

use AliMPay\Core\AlipayClient;
use AliMPay\Core\BillQuery;
use AliMPay\Core\PaymentMonitor;
use AliMPay\Core\CodePay;
use AliMPay\Utils\Logger;

// 设置北京时间
date_default_timezone_set('Asia/Shanghai');

$logger = Logger::getInstance();
$logger->info("Container background monitor started");

$lockFile = __DIR__ . '/data/container_monitor.lock';
$monitorInterval = 30; // 30秒间隔
$maxRunTime = 3600; // 最多运行1小时后自动退出，让API重新启动

// 使用改进的锁管理器
class ImprovedLockManager {
    private $lockFile;
    private $lockHandle;
    private $timeout;
    private $logger;
    private $hasLock = false;
    
    public function __construct($lockFile, $timeout = 300) {
        $this->lockFile = $lockFile;
        $this->timeout = $timeout;
        $this->logger = Logger::getInstance();
        
        $lockDir = dirname($lockFile);
        if (!is_dir($lockDir)) {
            mkdir($lockDir, 0755, true);
        }
    }
    
    public function tryLock() {
        try {
            $this->lockHandle = fopen($this->lockFile, 'w');
            
            if (!$this->lockHandle) {
                return false;
            }
            
            if (flock($this->lockHandle, LOCK_EX | LOCK_NB)) {
                $this->hasLock = true;
                $lockInfo = [
                    'pid' => getmypid(),
                    'timestamp' => time(),
                    'timeout' => $this->timeout
                ];
                
                fwrite($this->lockHandle, json_encode($lockInfo));
                fflush($this->lockHandle);
                
                return true;
            } else {
                fclose($this->lockHandle);
                $this->lockHandle = null;
                return false;
            }
            
        } catch (Exception $e) {
            $this->logger->error('Failed to acquire background lock', ['error' => $e->getMessage()]);
            return false;
        }
    }
    
    public function releaseLock() {
        try {
            if (!$this->hasLock) {
                if ($this->lockHandle) {
                    fclose($this->lockHandle);
                    $this->lockHandle = null;
                }
                return;
            }

            if ($this->lockHandle) {
                flock($this->lockHandle, LOCK_UN);
                fclose($this->lockHandle);
                $this->lockHandle = null;
            }
            
            if (file_exists($this->lockFile)) {
                unlink($this->lockFile);
            }

            $this->hasLock = false;

        } catch (Exception $e) {
            $this->logger->error('Failed to release background lock', ['error' => $e->getMessage()]);
        }
    }
    
    public function __destruct() {
        $this->releaseLock();
    }
}

$lockManager = new ImprovedLockManager($lockFile, $maxRunTime);

if (!$lockManager->tryLock()) {
    $logger->info("Another background monitor is already running");
    exit(0);
}

// 注册清理函数
register_shutdown_function(function() use ($lockManager) {
    $lockManager->releaseLock();
});

$startTime = time();
$cycleCount = 0;

try {
    $alipayClient = new AlipayClient();
    $billQuery = new BillQuery($alipayClient);
    $codePay = new CodePay();
    $db = $codePay->getDb();
    $merchantInfo = $codePay->getMerchantInfo();
    
    $paymentMonitor = new PaymentMonitor($billQuery, $db, $merchantInfo);
    
    while (true) {
        $currentTime = time();
        $runTime = $currentTime - $startTime;
        
        // 如果运行时间超过最大时间，退出让API重新启动
        if ($runTime >= $maxRunTime) {
            $logger->info("Container monitor reached max runtime, exiting gracefully", [
                "run_time" => $runTime,
                "cycles_completed" => $cycleCount
            ]);
            break;
        }
        
        $cycleCount++;
        
        try {
            $paymentMonitor->runMonitoringCycle();
            $logger->debug("Container monitor cycle completed", ["cycle" => $cycleCount]);

            // Update status file for health check
            $statusData = [
                'status' => 'completed',
                'last_run' => time(),
                'last_run_formatted' => date('Y-m-d H:i:s'),
                'message' => 'Automatic monitoring cycle completed',
                'updated_by' => 'container_monitor'
            ];
            file_put_contents(__DIR__ . '/data/monitor_status.json', json_encode($statusData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);

        } catch (Exception $e) {
            $logger->error("Container monitor cycle failed", [
                "cycle" => $cycleCount,
                "error" => $e->getMessage()
            ]);
        }
        
        sleep($monitorInterval);
    }
    
} catch (Exception $e) {
    $logger->error("Container monitor initialization failed", ["error" => $e->getMessage()]);
}

$lockManager->releaseLock();
$logger->info("Container background monitor stopped", ["total_cycles" => $cycleCount]);
?>
