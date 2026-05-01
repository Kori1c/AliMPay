<?php

namespace AliMPay\Admin;

use Medoo\Medoo;

class OrderAdminService
{
    public static function markExpiredOrders(Medoo $db, string $expiredThreshold): void
    {
        $db->update('codepay_orders', ['status' => 2], [
            'status' => 0,
            'add_time[<]' => $expiredThreshold,
        ]);
    }

    public static function attachPaymentPageUrls(array $orders, int $timeoutSeconds): array
    {
        foreach ($orders as &$order) {
            $isExpired = self::isOrderExpired($order, $timeoutSeconds);
            $isPaid = (int)($order['status'] ?? 0) === 1;

            $order['is_expired'] = $isExpired;
            $order['display_status'] = $isPaid ? 'paid' : ($isExpired ? 'expired' : 'pending');
            $order['status_label'] = $isPaid ? '已支付' : ($isExpired ? '已过期' : '待支付');
            $order['payment_page_url'] = null;
            if ($isExpired || $isPaid || empty($order['out_trade_no']) || empty($order['status_token'])) {
                continue;
            }

            $order['payment_page_url'] = AdminConfigService::adminBaseUrl() . '/submit.php?' . http_build_query([
                'resume_payment' => 1,
                'out_trade_no' => $order['out_trade_no'],
                'status_token' => $order['status_token'],
            ]);
        }
        unset($order);

        return $orders;
    }

    private static function isOrderExpired(array $order, int $timeoutSeconds): bool
    {
        $status = (int)($order['status'] ?? 0);
        if ($status === 2) {
            return true;
        }

        if ($status !== 0 || empty($order['add_time'])) {
            return false;
        }

        $createdAt = strtotime((string)$order['add_time']);
        return $createdAt !== false && $createdAt < time() - $timeoutSeconds;
    }
}
