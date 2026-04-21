<?php

require_once 'vendor/autoload.php';

use AliMPay\Core\CodePay;
use AliMPay\Utils\Logger;

$logger = Logger::getInstance();

try {
    // 兼容POST和GET请求
    $requestData = !empty($_POST) ? $_POST : $_GET;

    $params = [
        'pid' => $requestData['pid'] ?? '',
        'type' => $requestData['type'] ?? '',
        'out_trade_no' => $requestData['out_trade_no'] ?? '',
        'notify_url' => $requestData['notify_url'] ?? '',
        'return_url' => $requestData['return_url'] ?? '',
        'name' => $requestData['name'] ?? '',
        'money' => $requestData['money'] ?? '',
        'sitename' => $requestData['sitename'] ?? '',
        'sign' => $requestData['sign'] ?? '',
        'sign_type' => $requestData['sign_type'] ?? 'MD5'
    ];
    
    // 后台订单列表打开已有订单支付页，不重新创建订单
    if (!empty($requestData['resume_payment'])) {
        $codePay = new CodePay();
        $result = $codePay->getExistingPaymentPageData(
            (string)($requestData['out_trade_no'] ?? ''),
            (string)($requestData['status_token'] ?? '')
        );
        $params = array_merge($params, [
            'pid' => $result['pid'] ?? $params['pid'],
            'type' => $result['type'] ?? $params['type'],
            'out_trade_no' => $result['out_trade_no'] ?? $params['out_trade_no'],
            'notify_url' => $result['notify_url'] ?? $params['notify_url'],
            'return_url' => $result['return_url'] ?? $params['return_url'],
            'name' => $result['name'] ?? $params['name'],
            'money' => $result['money'] ?? $params['money'],
            'sitename' => $result['sitename'] ?? $params['sitename']
        ]);
    } else {
        // 对于直接访问或GET请求，需要重新创建支付
        $logger->info('CodePay Payment Submit Request', [
            'params' => array_merge($params, ['sign' => '***']), // Hide signature in logs
            'method' => $_SERVER['REQUEST_METHOD'],
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ]);
        
        $codePay = new CodePay();
        $result = $codePay->createPayment($params);
    }
    
    if ($result['code'] !== 1) {
        throw new Exception($result['msg']);
    }
    
    // 优先使用实际支付金额
    $displayAmount = $result['payment_amount'] ?? $params['money'];
    $logger->info('Payment page generated successfully.', ['out_trade_no' => $params['out_trade_no']]);
    
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>码支付 - 支付宝支付</title>
	        <style>
	            @keyframes fadeUp {
	                from { opacity: 0; transform: translateY(12px); }
	                to { opacity: 1; transform: translateY(0); }
	            }
	            @keyframes qrReady {
	                from { opacity: 0; transform: scale(0.96); }
	                to { opacity: 1; transform: scale(1); }
	            }
	            @keyframes waitingPulse {
	                0%, 100% { box-shadow: 0 0 0 0 rgba(212, 107, 8, 0.18); }
	                50% { box-shadow: 0 0 0 8px rgba(212, 107, 8, 0); }
	            }
	            @keyframes successPop {
	                0% { transform: scale(0.98); }
	                60% { transform: scale(1.03); }
	                100% { transform: scale(1); }
	            }
	            body {
	                font-family: Arial, sans-serif;
	                background: linear-gradient(180deg, #f4f7fb 0%, #eef4ff 100%);
                margin: 0;
                padding: 18px;
            }
            .container {
                max-width: 1040px;
                margin: 0 auto;
                background: rgba(255, 255, 255, 0.96);
	                border-radius: 24px;
	                padding: 28px;
	                box-shadow: 0 18px 45px rgba(15, 23, 42, 0.08);
	                animation: fadeUp 240ms ease-out both;
	            }
            .header {
                text-align: center;
                margin-bottom: 22px;
            }
            .header h1 {
                color: #1677ff;
                margin: 0;
            }
            .header p {
                margin: 8px 0 0;
                color: #64748b;
            }
            .payment-layout {
                display: grid;
                grid-template-columns: minmax(0, 1.08fr) minmax(320px, 0.92fr);
                gap: 20px;
                align-items: start;
            }
            .payment-main,
            .payment-side {
                min-width: 0;
            }
            .order-info {
                background: linear-gradient(180deg, #f8fafc 0%, #f3f7ff 100%);
                padding: 20px;
                border-radius: 18px;
                margin-bottom: 14px;
                border: 1px solid rgba(191, 219, 254, 0.5);
            }
            .order-info h3 {
                margin: 0 0 14px;
                color: #333;
            }
            .info-item {
                display: flex;
                justify-content: space-between;
                align-items: baseline;
                gap: 14px;
                margin-bottom: 12px;
            }
            .info-item:last-child {
                margin-bottom: 0;
            }
            .info-label {
                color: #64748b;
                flex: 0 0 94px;
            }
            .info-value {
                font-weight: bold;
                color: #1e293b;
                text-align: right;
                word-break: break-all;
            }
            .amount {
                font-size: 34px;
                line-height: 1;
                color: #ff4d4f;
            }
            .status-strip {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 12px;
                margin-bottom: 14px;
            }
            .qr-code {
                text-align: center;
                padding: 18px 18px 16px;
                background: linear-gradient(180deg, #ffffff 0%, #f8fbff 100%);
                border-radius: 20px;
                border: 1px solid rgba(191, 219, 254, 0.52);
            }
            .qr-code p {
                margin: 0 0 14px;
                font-size: 16px;
                font-weight: bold;
                color: #1f2937;
            }
            .qr-code .qr-img-wrapper {
                display: inline-block;
                width: min(100%, 260px);
                aspect-ratio: 1 / 1;
                background: #fff;
                border-radius: 18px;
                box-shadow: 0 10px 30px rgba(15,23,42,0.06);
	                overflow: hidden;
	                position: relative;
	                animation: qrReady 260ms ease-out both;
	                transition: transform 180ms ease, box-shadow 180ms ease;
	            }
	            .qr-code .qr-img-wrapper:hover {
	                transform: translateY(-2px);
	                box-shadow: 0 8px 20px rgba(0,0,0,0.08);
	            }
            .qr-code img {
                width: 100%;
                height: 100%;
                object-fit: contain;
                object-position: center;
                border: 1px solid #ddd;
                background: white;
                display: block;
            }
            .payment-tips {
                background: #eef7ff;
                border: 1px solid #bfdbfe;
                border-radius: 18px;
                padding: 16px 18px;
                margin-top: 14px;
            }
            .payment-tips h4 {
                margin: 0 0 10px;
                color: #1677ff;
            }
            .payment-tips ul {
                margin: 0;
                padding-left: 18px;
            }
            .payment-tips li {
                margin: 5px 0;
                color: #475569;
                line-height: 1.5;
            }
            .alert-warning {
                background-color: #fffbe6;
                border-color: #ffe58f;
                color: #d46b08;
                padding: 12px 15px;
                border-radius: 14px;
                margin-bottom: 16px;
            }
            .buttons {
                display: flex;
                flex-wrap: wrap;
                justify-content: center;
                gap: 10px;
                margin-top: 14px;
            }
	            .btn {
	                background: #1677ff;
	                color: white;
                border: none;
                padding: 11px 18px;
                border-radius: 12px;
                cursor: pointer;
	                text-decoration: none;
	                display: inline-block;
	                transition: transform 160ms ease, background-color 160ms ease, box-shadow 160ms ease;
	            }
	            .btn:hover {
	                background: #0958d9;
	                transform: translateY(-1px);
	                box-shadow: 0 6px 14px rgba(22, 119, 255, 0.18);
	            }
	            .btn:active {
	                transform: translateY(0);
	            }
            .btn-secondary {
                background: #f5f5f5;
                color: #666;
            }
            .btn-secondary:hover {
                background: #e6e6e6;
            }
            .status {
                text-align: center;
                margin-top: 0;
                padding: 12px 14px;
                border-radius: 14px;
                font-weight: bold;
            }
            .status.pending {
	                background: #fff7e6;
	                color: #d46b08;
	                border: 1px solid #ffd591;
	                animation: waitingPulse 1800ms ease-in-out infinite;
	            }
	            .status.success {
	                background: #f6ffed;
	                color: #52c41a;
	                border: 1px solid #b7eb8f;
	                animation: successPop 240ms ease-out both;
	            }
            .status.expired {
                background: #f5f5f5;
                color: #8c8c8c;
                border: 1px solid #d9d9d9;
            }
            .countdown {
                text-align: center;
                margin: 0;
                padding: 12px 14px;
                background: #fff1f0;
                color: #cf1322;
                border: 1px solid #ffa39e;
                border-radius: 14px;
                font-weight: bold;
            }
            .countdown.expired {
                background: #f5f5f5;
                color: #8c8c8c;
                border: 1px solid #d9d9d9;
            }
            .side-actions {
                margin-top: 14px;
            }
            @media (max-width: 900px) {
                body {
                    padding: 10px;
                }
                .container {
                    padding: 18px;
                    border-radius: 18px;
                }
                .payment-layout {
                    grid-template-columns: 1fr;
                    gap: 14px;
                }
                .status-strip {
                    grid-template-columns: 1fr;
                }
                .side-actions {
                    margin-top: 12px;
                }
                .qr-code .qr-img-wrapper {
                    width: min(100%, 220px);
                }
                .amount {
                    font-size: 28px;
                }
            }
            @media (max-width: 640px) {
                .header {
                    margin-bottom: 16px;
                }
                .header h1 {
                    font-size: 28px;
                }
                .order-info,
                .qr-code,
                .payment-tips {
                    padding: 16px;
                    border-radius: 16px;
                }
                .info-item {
                    align-items: flex-start;
                    flex-direction: column;
                    gap: 4px;
                }
                .info-label,
                .info-value {
                    flex: none;
                    text-align: left;
                }
                .buttons {
                    flex-direction: column;
                }
                .btn {
                    width: 100%;
                    box-sizing: border-box;
                    text-align: center;
                }
            }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <h1>码支付</h1>
                <p>安全、快速的支付体验</p>
            </div>

            <?php if (isset($result['amount_adjusted']) && $result['amount_adjusted']): ?>
            <div class="alert-warning">
                <strong>注意：</strong> <?php echo htmlspecialchars($result['adjustment_note']); ?>
            </div>
            <?php endif; ?>
            
            <div class="payment-layout">
                <div class="payment-main">
                    <div class="order-info">
                        <h3>订单信息</h3>
                        <div class="info-item">
                            <span class="info-label">商品名称：</span>
                            <span class="info-value"><?php echo htmlspecialchars($params['name']); ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">订单号：</span>
                            <span class="info-value"><?php echo htmlspecialchars($params['out_trade_no']); ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">内部交易号：</span>
                            <span class="info-value"><?php echo htmlspecialchars($result['trade_no']); ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">支付金额：</span>
                            <span class="info-value amount">¥<?php echo htmlspecialchars(number_format($displayAmount, 2)); ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">支付方式：</span>
                            <span class="info-value">支付宝</span>
                        </div>
                        <?php if (!empty($params['sitename'])): ?>
                        <div class="info-item">
                            <span class="info-label">商户名称：</span>
                            <span class="info-value"><?php echo htmlspecialchars($params['sitename']); ?></span>
                        </div>
                        <?php endif; ?>
                    </div>

                    <div class="status-strip">
                        <div class="status pending" id="paymentStatus">
                            等待支付...
                        </div>
                        
                        <div class="countdown" id="countdownDisplay">
                            剩余支付时间：<span id="countdown">05:00</span>
                        </div>
                    </div>
                </div>

                <div class="payment-side">
                    <?php if (isset($result['business_qr_mode']) && $result['business_qr_mode']): ?>
                        <div class="qr-code">
                            <p>请使用支付宝扫描下方二维码完成支付</p>
                            <div class="qr-img-wrapper">
                                <img src="<?php echo htmlspecialchars($result['qr_code_url']); ?>" alt="经营码收款">
                            </div>
                        </div>
                        <div class="payment-tips">
                            <h4>支付提示</h4>
                            <ul>
                                <?php foreach ($result['payment_tips'] as $tip): ?>
                                    <li><?php echo htmlspecialchars($tip); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php else: ?>
                        <div class="qr-code">
                            <p>请使用支付宝扫描下方二维码完成支付</p>
                            <div class="qr-img-wrapper">
                                <img src="data:image/png;base64,<?php echo $result['qr_code']; ?>" alt="支付宝支付">
                            </div>
                        </div>
                        <div class="payment-tips">
                            <h4>支付提示</h4>
                            <ul>
                                <li>请在5分钟内完成支付，超时订单将自动作废。</li>
                                <li>支付时无需填写备注，系统会自动确认。</li>
                                <li>支付完成后，页面将自动跳转。</li>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <div class="buttons side-actions">
                        <?php if (!(isset($result['business_qr_mode']) && $result['business_qr_mode'])): ?>
                        <a href="<?php echo htmlspecialchars($result['payment_url']); ?>" class="btn" id="openInAlipay">打开支付宝App支付</a>
                        <?php endif; ?>
                        <button class="btn btn-secondary" onclick="checkOrderStatus()">我已支付，查询订单状态</button>
                    </div>
                </div>
            </div>
        </div>

        <script>
            if ('serviceWorker' in navigator) {
                navigator.serviceWorker.getRegistrations()
                    .then(registrations => registrations.forEach(registration => registration.unregister()))
                    .catch(() => {});
            }

            let countdownInterval;
            let statusInterval;
            let orderFinished = false;
            
            function startCountdown(duration) {
                let timer = duration, minutes, seconds;
                const display = document.getElementById('countdown');
                const countdownDisplay = document.getElementById('countdownDisplay');

                countdownInterval = setInterval(function () {
                    minutes = parseInt(timer / 60, 10);
                    seconds = parseInt(timer % 60, 10);

                    minutes = minutes < 10 ? "0" + minutes : minutes;
                    seconds = seconds < 10 ? "0" + seconds : seconds;

                    display.textContent = minutes + ":" + seconds;

                    if (--timer < 0) {
                        orderFinished = true;
                        clearInterval(countdownInterval);
                        clearInterval(statusInterval);
                        document.getElementById('paymentStatus').textContent = '订单已超时';
                        document.getElementById('paymentStatus').className = 'status expired';
                        countdownDisplay.textContent = '订单已过期，请重新下单';
                        countdownDisplay.className = 'countdown expired';
                    }
                }, 1000);
            }

            function checkOrderStatus() {
                if (orderFinished) {
                    return;
                }

                const statusElement = document.getElementById('paymentStatus');
                const outTradeNo = <?php echo json_encode($result['out_trade_no'] ?? '', JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
                const pid = <?php echo json_encode($result['pid'] ?? '', JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
                const statusToken = <?php echo json_encode($result['status_token'] ?? '', JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;

                statusElement.textContent = '正在查询订单状态...';

                const query = new URLSearchParams({
                    action: 'order',
                    out_trade_no: outTradeNo,
                    pid: pid,
                    status_token: statusToken
                });

                fetch('/api.php?' + query.toString())
                    .then(response => response.json())
                    .then(data => {
                        if (data.code === 1 && data.status === 1) {
                            orderFinished = true;
                            statusElement.textContent = '支付成功';
                            statusElement.className = 'status success';
                            clearInterval(countdownInterval);
                            clearInterval(statusInterval);
                            
                            // Redirect to return_url if available
                            const returnUrl = '<?php echo htmlspecialchars($params['return_url']); ?>';
                            if (returnUrl) {
                                window.location.href = returnUrl;
                            }
                        } else if (data.code === 1 && data.status === 2) {
                            orderFinished = true;
                            statusElement.textContent = '订单已过期';
                            statusElement.className = 'status expired';
                            clearInterval(countdownInterval);
                            clearInterval(statusInterval);
                        } else if (data.code === 1 && data.status === 0) {
                            statusElement.textContent = '等待支付...';
                            statusElement.className = 'status pending';
                        } else {
                            statusElement.textContent = '查询失败，请稍后重试';
                        }
                    })
                    .catch(error => {
                        console.error('Error checking order status:', error);
                        statusElement.textContent = '查询时发生错误';
                    });
            }

            // Start countdown
            startCountdown(300); // 5 minutes

            // Periodically check order status every 5 seconds
            statusInterval = setInterval(checkOrderStatus, 5000);
            
            // Initial check
            checkOrderStatus();
        </script>
    </body>
    </html>
    <?php
} catch (Exception $e) {
    $logger->error('Payment page failed to generate.', ['error' => $e->getMessage()]);
    
    // Display a user-friendly error page
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>支付错误</title>
        <style>
            body { font-family: sans-serif; text-align: center; padding: 50px; }
            .error-container { max-width: 600px; margin: 0 auto; background: #fff1f0; border: 1px solid #ffa39e; padding: 20px; border-radius: 8px; }
            h1 { color: #cf1322; }
            p { color: #595959; }
        </style>
    </head>
    <body>
        <div class="error-container">
            <h1>支付请求失败</h1>
            <p>抱歉，我们无法处理您的支付请求。请检查您的参数是否正确，或联系网站管理员。</p>
            <p><strong>错误信息：</strong> <?php echo htmlspecialchars($e->getMessage()); ?></p>
        </div>
    </body>
    </html>
    <?php
} 
