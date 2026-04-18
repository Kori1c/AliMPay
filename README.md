# AliMPay

一个基于支付宝账单查询的码支付系统，兼容 CodePay 接口，提供网页管理后台、订单监控、经营码收款和转账备注收款两种模式。

这个仓库适合两类人：

- 想自己部署一套支付宝码支付系统的人
- 已经有站点，只想对接 CodePay 协议下单的人

仓库已经刻意排除了所有隐私数据：支付宝私钥、公钥配置、商户密钥、二维码图片、数据库、日志都不会提交到 Git。

## 功能概览

- CodePay 兼容下单接口
- 支付页展示与订单状态轮询
- 后台管理面板
- 经营码模式与转账模式
- 自动轮询支付宝账单
- 订单过期标记
- 移动端适配
- Docker 一键部署

## 目录说明

```text
.
├── admin_api.php           # 后台管理接口
├── api.php                 # CodePay 兼容接口
├── index.php               # Web 管理后台
├── submit.php              # 支付页面
├── notify.php              # 商户回调示例
├── health.php              # 健康检查接口
├── qrcode.php              # 支付页经营码输出
├── qrcode_view.php         # 后台经营码预览
├── src/                    # 核心逻辑
├── config/                 # 配置目录（真实配置不会进仓库）
├── data/                   # SQLite 数据与运行状态
├── logs/                   # 日志
└── qrcode/                 # 经营码图片目录
```

## 环境要求

推荐使用 Docker，最省心。

基础要求：

- Docker 24+
- Docker Compose Plugin

如果不用 Docker，也至少需要：

- PHP 8.1+
- Composer
- SQLite 扩展
- GD 扩展
- `mod_rewrite`（Apache）或等价重写支持

## 最快启动方式

### 1. 克隆项目

```bash
git clone https://github.com/MiaM1ku/AliMPay.git
cd AliMPay
```

### 2. 创建支付宝配置文件

把示例配置复制成真实配置：

```bash
cp config/alipay.example.php config/alipay.php
```

然后参考 [`config/alipay.example.php`](config/alipay.example.php) 的结构，写入你自己的参数到 `config/alipay.php`：

- `server_url`
- `app_id`
- `private_key`
- `alipay_public_key`
- `transfer_user_id`

生产环境通常使用：

```php
'server_url' => 'https://openapi.alipay.com',
```

### 3. 启动服务

```bash
docker compose up -d --build
```

启动后访问：

- 后台首页：[http://localhost:8080](http://localhost:8080)
- 健康检查：[http://localhost:8080/health.php](http://localhost:8080/health.php)

## 首次进入后台

系统首次运行时会自动生成商户配置文件 `config/codepay.json`。

后台默认管理密码是：

```text
admin
```

第一次登录后，请立刻到“商户配置 (CodePay)”里修改管理密码。

## 配置说明

### 一、支付宝连接

这是最核心的一组参数。

必填项：

- `app_id`：支付宝开放平台应用 ID
- `private_key`：你的应用私钥
- `alipay_public_key`：支付宝开放平台提供的支付宝公钥

通常建议：

- `sign_type` 保持 `RSA2`
- `charset` 保持 `UTF-8`
- `format` 保持 `json`

### 二、商户配置 (CodePay)

系统会自动生成：

- `merchant_id`
- `merchant_key`

这两个参数是给你自己的业务系统调用 CodePay 接口时使用的。

请不要把它们写进前端代码、截图、聊天记录或公开仓库。

### 三、收款模式

#### 1. 经营码模式

适合“客户扫码付款，不填备注”的场景。

工作方式：

- 同金额订单自动错开几分钱
- 系统用“金额 + 时间”匹配账单

你可以在后台上传经营码图片，也可以把文件放到：

```text
qrcode/business_qr.png
```

#### 2. 转账备注模式

适合“客户转账时填写订单号备注”的场景。

工作方式：

- 客户付款时带订单号备注
- 系统通过备注匹配订单

### 四、监控参数

通常小白不需要频繁改。

建议先保持默认，只在你明确知道含义时再调整：

- `order_timeout`
- `query_minutes_back`
- `check_interval`
- `auto_cleanup`

## Docker 部署说明

仓库自带的 [`Dockerfile`](Dockerfile) 会在构建时自动安装 Composer 依赖，不需要你手动保留 `vendor/`。

[`docker-compose.yml`](docker-compose.yml) 默认会把下面这些目录挂到宿主机，方便持久化：

- `config/`
- `data/`
- `logs/`
- `qrcode/`

这意味着你重建容器后，下面这些东西不会丢：

- 支付宝配置
- 商户配置
- 订单数据库
- 日志
- 经营码图片

### 常用命令

启动：

```bash
docker compose up -d --build
```

查看日志：

```bash
docker compose logs -f
```

停止：

```bash
docker compose down
```

更新代码后重建：

```bash
git pull
docker compose up -d --build
```

## 不用 Docker 的部署方式

### 1. 安装依赖

```bash
composer install --no-dev
```

### 2. 准备目录权限

确保下面目录可写：

- `config/`
- `data/`
- `logs/`
- `qrcode/`

### 3. Web 服务器指向项目根目录

如果你用 Apache，请确保：

- 开启 `mod_rewrite`
- 允许 `.htaccess`

仓库里的 [`.htaccess`](.htaccess) 已经做了基础保护，会拦截配置、数据库、日志等敏感文件的直接访问。

## 接口对接说明

### 创建订单

向 `/submit.php` 或 `/mapi.php` 发起请求，参数遵循 CodePay 常见格式：

```text
pid          商户ID
type         支付方式，通常为 alipay
out_trade_no 商户订单号
notify_url   异步通知地址
return_url   同步跳转地址
name         商品名称
money        金额
sign         MD5 签名
```

### 查询订单

```bash
GET /api.php?act=order&pid=商户ID&out_trade_no=订单号&status_token=订单状态令牌
```

### 查询商户信息

```bash
GET /api.php?act=query&pid=商户ID&key=商户密钥
```

## 建议上线前自测一遍

至少跑完这一套：

1. 后台保存支付宝配置
2. 测试“支付宝连接”
3. 创建一笔待支付订单
4. 打开支付页
5. 完成真实支付或模拟支付
6. 确认订单变为“已支付”
7. 确认 `notify_url` 收到回调
8. 再测试一笔超时订单，确认会变成“已过期”

## 安全建议

- 不要提交 `config/alipay.php`
- 不要提交 `config/codepay.json`
- 不要提交 `data/codepay.db`
- 不要提交经营码图片
- 不要把商户密钥发给客户
- 后台初始密码改掉后再上线
- 生产环境建议走 HTTPS
- 定期备份 `config/` 和 `data/`

## 常见问题

### 1. 为什么测试连接成功，但监控状态还是异常？

“支付宝连接成功”只说明配置没问题；“监控状态”还依赖轮询任务最近是否正常运行、数据库是否正常、账单查询是否成功。

### 2. 为什么订单会变成已过期？

待支付订单超过 `order_timeout` 后会被标记为 `已过期`，默认是 300 秒。

### 3. 为什么经营码换了，页面没变？

新版已经对经营码预览和支付页做了防缓存处理。仍然没变化时，尝试强刷浏览器，或重新上传一次图片。

### 4. 为什么我不建议把整个项目目录直接公开共享？

因为真实部署目录里通常会混入：

- 支付宝配置
- 商户配置
- 订单数据库
- 经营码图片
- 日志

这些都属于敏感信息。

## License

MIT

## 免责声明

本项目仅供学习与合法业务场景使用。请在使用前自行确认当地法律法规、平台规则与支付宝相关协议要求。
