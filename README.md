# AliMPay

AliMPay 是一个基于支付宝账单查询的码支付系统，兼容 CodePay 接口，并带有可视化 Web 管理后台。

适合这几类使用方式：

- 你想自己部署一套支付宝码支付系统
- 你现有业务已经对接 CodePay，只想更换后端
- 你希望用后台维护支付宝参数、收款模式和经营码，而不是长期手改配置文件

系统当前支持：

- CodePay 兼容下单接口
- Web 后台管理支付宝连接和商户配置
- 经营码模式
- 转账备注模式
- 订单查询和状态轮询
- 备份与恢复
- Docker 部署

## 部署方式

推荐直接使用 Docker 部署。

### 环境要求

Docker 部署需要：

- Docker
- Docker Compose

非 Docker 部署至少需要：

- PHP 8.1+
- Composer
- SQLite 扩展
- GD 扩展
- ZIP 扩展
- Apache 或 Nginx

### 方式一：Docker Compose 部署

1. 克隆项目

```bash
git clone https://github.com/Kori1c/AliMPay.git
cd AliMPay
```

2. 准备基础配置文件

```bash
cp config/alipay.example.php config/alipay.php
```

说明：

- 这个文件第一次启动前必须存在
- 真实支付宝参数建议在后台保存，不建议长期手改文件

3. 启动服务

```bash
docker compose up -d --build
```

4. 打开后台

- 后台首页：[http://localhost:8080](http://localhost:8080)
- 健康检查：[http://localhost:8080/health.php](http://localhost:8080/health.php)

5. 首次登录

- 默认后台密码：`admin`
- 首次登录后请立即修改后台密码

### 方式二：直接使用官方镜像

如果你不想本地构建镜像，可以直接使用官方镜像：

```bash
docker pull ghcr.io/kori1c/alimpay:latest
```

示例 `docker-compose.yml`：

```yaml
services:
  alimpay:
    image: ghcr.io/kori1c/alimpay:latest
    container_name: alimpay-app
    ports:
      - "8080:80"
    volumes:
      - ./config:/var/www/html/config
      - ./data:/var/www/html/data
      - ./logs:/var/www/html/logs
      - ./qrcode:/var/www/html/qrcode
    restart: unless-stopped
    environment:
      - TZ=Asia/Shanghai
    command: >
      sh -c "nohup php /var/www/html/container_monitor.php > /var/www/html/logs/monitor.log 2>&1 & apache2-foreground"
```

镜像地址：

```text
ghcr.io/kori1c/alimpay
```

常用标签：

- `ghcr.io/kori1c/alimpay:latest`
- `ghcr.io/kori1c/alimpay:v1.0.4`

### 方式三：非 Docker 部署

1. 安装依赖

```bash
composer install --no-dev
cp config/alipay.example.php config/alipay.php
```

2. 把项目上传到网站目录

常见做法：

- 宝塔面板新建站点后，把项目文件放到站点根目录
- Nginx 或 Apache 把站点根目录指向项目目录

3. 确保这些目录可写

- `config/`
- `data/`
- `logs/`
- `qrcode/`

4. 配置 Web 服务

- Apache 需要开启 `mod_rewrite`
- Apache 需要允许 `.htaccess`
- Nginx 需要把站点根目录指向项目目录
- 生产环境建议配好 HTTPS

5. 配置自动轮询任务

非 Docker 部署时，账单自动轮询需要额外配置计划任务。

推荐使用 `cron`：

```cron
* * * * * flock -n /tmp/alimpay-monitor-cron.lock /usr/bin/php /你的站点目录/container_monitor.php >/dev/null 2>&1
```

说明：

- 把 `/你的站点目录/` 改成你自己的实际部署路径
- `flock` 用来避免重复启动多个监控进程
- 如果你的 PHP CLI 路径不是 `/usr/bin/php`，请改成实际路径
- 配置完成后，系统会自动持续轮询支付宝账单

### 持久化目录说明

无论你使用本地构建镜像还是官方镜像，都建议持久化这几个目录：

- `config/`
- `data/`
- `logs/`
- `qrcode/`

这些目录里会保存：

- 支付宝配置
- 商户配置
- 订单数据库
- 日志
- 经营码图片

## 支付宝信息获取方式

系统启动后，先登录后台，再完成下面几步。

### 1. 获取商户信息

进入后台的“商户配置”页面后，系统会自动生成：

- 商户 ID
- 商户密钥

这两个值是给你自己的业务系统调用 CodePay 接口时使用的。

请注意：

- 不要公开商户密钥
- 不要截图外发

### 2. 获取支付宝连接所需参数

进入后台的“支付宝连接”页面，需要填写这些信息：

- 网关地址
- APP ID
- 收款用户 ID
- 应用私钥
- 支付宝公钥

一般情况下：

- 网关地址填写 `https://openapi.alipay.com`
- 签名方式使用 `RSA2`
- 编码使用 `UTF-8`
- 返回格式使用 `json`

### 3. 这些支付宝参数从哪里来

通常需要从支付宝开放平台获取：

- `APP ID`
- 应用私钥
- 支付宝公钥

通常需要从你的收款账号信息中确认：

- 收款用户 ID

建议获取顺序：

1. 登录支付宝开放平台
2. 创建或进入你的应用
3. 在应用配置里找到 `APP ID`
4. 生成或上传应用私钥
5. 获取支付宝公钥
6. 确认实际收款账号对应的用户 ID
7. 回到 AliMPay 后台保存并点击“测试连接”

如果测试连接通过，说明支付宝连接参数基本可用。

### 4. 收款模式如何选择

AliMPay 支持两种收款模式：

- 转账备注模式
- 经营码模式

转账备注模式适合：

- 付款时可以填写订单号备注
- 你希望系统按备注识别订单

经营码模式适合：

- 客户只扫码付款，不填备注
- 你希望系统通过金额偏移区分同金额订单

经营码模式开启后：

- 需要在后台上传经营码图片
- 同金额订单可能自动偏移为 `10.01`、`10.02` 这类金额

## 注意事项

### 上线前必须做的事

- 首次登录后立即修改后台密码
- 保存支付宝参数后先执行“测试连接”
- 选择正确的收款模式
- 如果使用经营码模式，先上传经营码再测试订单
- 自测一笔完整订单流程后再正式对外

### 对接业务系统时要注意

- 如果你的业务系统原本就是对接 CodePay，可以直接接入这套系统
- 下单签名要和你的请求参数保持完全一致
- 如果请求里带了额外参数，这些参数也要一起参与签名
- 异步回调地址必须能被公网访问
- 回调处理成功后必须按系统要求返回成功标记
- 你的业务系统最好再次校验签名、订单号和金额

### 经营码模式注意事项

- 同金额订单会自动做小额偏移
- 实际支付金额可能与原始下单金额不同
- 对接方核账时要以系统返回的实际支付金额为准
- 二维码更换后如果页面没刷新，先强制刷新浏览器缓存

### 备份与恢复

后台支持导出备份和恢复备份。

备份内容通常包含：

- 支付宝连接配置
- 商户配置
- 订单数据库
- 经营码图片

恢复前建议先保留当前现场快照，避免误恢复后无法回滚。

### 安全提醒

- 不要公开商户密钥、私钥、公钥和收款用户信息
- 生产环境建议全站使用 HTTPS
- 建议定期导出备份

## License

MIT

## 免责声明

本项目仅供学习和合法业务场景使用。请在使用前自行确认相关法律法规、平台规则和支付宝协议要求。
