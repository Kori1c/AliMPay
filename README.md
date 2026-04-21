# AliMPay

一个基于支付宝账单查询的码支付系统，兼容 CodePay 接口，带完整 Web 管理后台。

这版是二开后的 Web 管理版，和早期版本最大的区别是：

- 支付宝参数、商户信息、收款模式、监控参数都可以在后台配置
- 不需要再长期手改 `config/alipay.php`
- 经营码支持直接在后台上传
- 订单、日志、健康状态都能在后台查看

公开仓库地址：

- [https://github.com/Kori1c/AliMPay](https://github.com/Kori1c/AliMPay)

## 现在这版能做什么

- CodePay 兼容下单接口
- Web 后台配置支付宝参数
- 自动生成商户 ID 和商户密钥
- 支持经营码收款
- 支持转账备注收款
- 自动轮询支付宝账单
- 订单过期标记
- 移动端后台适配
- Docker 部署

## 适合谁

- 想自己搭一套支付宝码支付系统的人
- 业务系统已经对接 CodePay，只想换后端的人
- 不想每次都去改 PHP 配置文件的人

## 和老版本的区别

如果你之前看过旧 README，最容易混淆的是这一点：

**现在的正确使用方式是：**

1. 先部署项目
2. 打开 Web 后台
3. 在后台填写支付宝连接
4. 在后台上传经营码、调整参数
5. 保存后直接测试

不是以前那种“改一堆配置文件再开跑”的流程了。

## 环境要求

推荐直接用 Docker。

需要：

- Docker
- Docker Compose

如果你不用 Docker，至少需要：

- PHP 8.1+
- Composer
- SQLite 扩展
- GD 扩展
- ZIP 扩展
- Apache 或 Nginx

## 快速开始

### 1. 克隆项目

```bash
git clone https://github.com/Kori1c/AliMPay.git
cd AliMPay
```

### 2. 生成初始配置文件

当前版本虽然主要通过后台配置，但项目启动时仍然需要有一个基础配置文件存在。

第一次部署只做这一步就够：

```bash
cp config/alipay.example.php config/alipay.php
```

这里不用急着手动填写参数。  
复制出来以后，后面直接去后台保存即可。

### 3. 启动项目

```bash
docker compose up -d --build
```

如果你只是想直接部署，不想自己本地构建镜像，也可以直接使用官方镜像：

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

启动后访问：

- 后台首页：[http://localhost:8080](http://localhost:8080)
- 健康检查：[http://localhost:8080/health.php](http://localhost:8080/health.php)

## 首次登录后台

系统第一次运行时会自动生成：

- `config/codepay.json`：商户配置
- `data/codepay.db`：订单数据库

默认后台密码是：

```text
admin
```

第一次登录后建议马上去后台把管理密码改掉。

## 正确的配置流程

这部分是现在版本最真实的使用顺序。

### 第一步：进入“商户配置 (CodePay)”

这里会自动生成：

- 商户 ID
- 商户密钥

它们是给你自己的业务系统调用 CodePay 接口时使用的。

这两个值不要公开，不要截图发别人，也不要提交到 GitHub。

### 第二步：进入“支付宝连接”

在后台填写这些参数：

- 网关地址
- APP ID
- 收款用户 ID
- 应用私钥
- 支付宝公钥

一般情况下：

- 网关地址填 `https://openapi.alipay.com`
- 签名方式保持 `RSA2`
- 编码保持 `UTF-8`
- 返回格式保持 `json`

填完后直接点“测试连接”。

### 第三步：进入“收款模式”

如果你用经营码收款：

- 打开经营码模式
- 在后台上传经营二维码

如果你用转账备注收款：

- 关闭经营码模式
- 让付款方按订单号备注支付

### 第四步：按需调整“监控参数”

大多数情况下默认值就够用，不建议小白一上来乱改。

真正比较常用的只有：

- 订单超时时间
- 轮询间隔
- 查询历史账单时间范围
- 是否自动处理过期订单

### 第五步：按需使用“备份 / 恢复”

商户配置页右下角现在已经支持：

- 导出备份
- 恢复备份

备份会包含：

- `config/alipay.php`
- `config/codepay.json`
- `data/codepay.db`
- 经营码图片

恢复前系统会自动创建一份当前现场快照，避免误操作后无法回滚。

## 经营码模式说明

适合“客户只扫码付款，不填备注”的场景。

工作逻辑：

- 同金额订单会自动做几分钱偏移
- 系统通过金额 + 时间去匹配账单

比如：

- 第一笔订单 `10.00`
- 第二笔同金额订单可能变成 `10.01`
- 第三笔可能变成 `10.02`

这样系统才能区分谁付的是哪一笔。

经营码图片不需要手动丢进目录，直接在后台上传就行。  
上传后系统会自动刷新预览和支付页引用。

## 转账备注模式说明

适合“付款时可以填写备注”的场景。

工作逻辑：

- 用户付款时填写订单号备注
- 系统通过支付宝账单备注匹配订单

这个模式下不依赖经营码图片。

## 后台都有哪些页面

### 仪表盘

看最近订单、订单统计、基本健康状态。

### 订单流水

可以查看：

- 待支付
- 已支付
- 已过期

待支付订单支持直接“前往支付”打开支付页。

### 系统设置

这里就是整个系统的配置中心：

- 支付宝连接
- 商户配置
- 收款模式
- 监控参数

### 监控状态

这里显示的不是“支付宝连没连上”，而是：

- 账单轮询最近有没有正常运行
- 最后一次活跃时间
- 健康评分

## Docker 部署说明

项目自带：

- [`Dockerfile`](Dockerfile)
- [`docker-compose.yml`](docker-compose.yml)

其中 `docker-compose.yml` 已经挂载好了这几个持久化目录：

- `config/`
- `data/`
- `logs/`
- `qrcode/`

这意味着你重建容器后，下面这些内容不会丢：

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

更新代码后重新部署：

```bash
git pull
docker compose up -d --build
```

## Release 说明

当前仓库已经内置 GitHub Actions 自动发版。

规则是：

- 每次 push 到 `main`
- 如果当前提交还没有版本 tag
- 系统会自动按 `vX.Y.Z` 的补丁号递增创建一个新的 GitHub Release

例如：

- `v1.0.2`
- 下一次推送到 `main` 后会自动生成 `v1.0.3`

对应工作流文件：

- [`.github/workflows/release.yml`](.github/workflows/release.yml)
- [`.github/workflows/docker-image.yml`](.github/workflows/docker-image.yml)

## Docker 镜像发布

仓库现在会自动构建并发布 GitHub Container Registry 镜像：

- 推送到 `main` 时，发布 `latest`
- 推送版本 tag 时，发布对应版本标签
- 每次发布也会附带一个 `sha-提交哈希` 标签，方便排查和回滚

镜像地址：

```text
ghcr.io/kori1c/alimpay
```

常见标签示例：

- `ghcr.io/kori1c/alimpay:latest`
- `ghcr.io/kori1c/alimpay:v1.0.4`
- `ghcr.io/kori1c/alimpay:sha-2930b85`

首次启用后，如果 GitHub Container Registry 包默认不是公开可见，需要在 GitHub 包设置里把它改成 Public。

## 不使用 Docker 的部署方式

### 1. 安装依赖

```bash
composer install --no-dev
```

### 2. 准备基础配置文件

```bash
cp config/alipay.example.php config/alipay.php
```

### 3. 确保目录可写

这些目录必须可写：

- `config/`
- `data/`
- `logs/`
- `qrcode/`

### 4. 配置 Web 服务

如果你用 Apache：

- 开启 `mod_rewrite`
- 允许 `.htaccess`

项目里的 [`.htaccess`](.htaccess) 已经拦截了配置、数据库、日志等敏感文件的直接访问。

## 对接方式

这一版对外仍然走 CodePay 风格接口，所以如果你原来业务系统就是对接 CodePay，迁移成本会比较低。

### 接入前你需要拿到什么

先去后台的“商户配置 (CodePay)”里拿到：

- `merchant_id`
- `merchant_key`

之后你自己的业务系统用这两个值来发起下单和查询。

### 下单接口

可用入口：

- `POST /submit.php`
- `POST /mapi.php`
- `POST /api.php?act=submit&format=json`

如果你希望直接返回支付页，常用的是 `/submit.php`。  
如果你希望拿 JSON 结果自己处理，建议用 `/mapi.php` 或 `/api.php?act=submit&format=json`。

### 下单参数

必填：

```text
pid          商户ID
type         支付方式，固定 alipay
out_trade_no 商户订单号
notify_url   异步通知地址
return_url   支付完成后的同步跳转地址
name         商品名称
money        支付金额
sign         MD5 签名
```

可选：

```text
sitename     站点名称
sign_type    默认 MD5，可不传
```

### 签名规则

签名方式是 MD5，规则和常见 CodePay 一样：

1. 去掉空值参数
2. 去掉 `sign` 和 `sign_type`
3. 按参数名升序排序
4. 拼成 `key=value&key=value`
5. 在字符串末尾直接拼接商户密钥
6. 对结果做 `md5`

示例参数：

```text
money=10.00
name=测试订单
notify_url=https://example.com/notify
out_trade_no=ORDER202604180001
pid=1001xxxxxxxxxxxx
return_url=https://example.com/return
type=alipay
```

待签名字符串：

```text
money=10.00&name=测试订单&notify_url=https://example.com/notify&out_trade_no=ORDER202604180001&pid=1001xxxxxxxxxxxx&return_url=https://example.com/return&type=alipay
```

最终签名：

```text
md5(待签名字符串 + merchant_key)
```

### PHP 签名示例

```php
<?php
$params = [
    'pid' => '你的商户ID',
    'type' => 'alipay',
    'out_trade_no' => 'ORDER202604180001',
    'notify_url' => 'https://example.com/notify',
    'return_url' => 'https://example.com/return',
    'name' => '测试订单',
    'money' => '10.00',
];

$merchantKey = '你的商户密钥';

ksort($params);
$parts = [];
foreach ($params as $key => $value) {
    if ($value !== '' && $value !== null) {
        $parts[] = $key . '=' . $value;
    }
}

$signStr = implode('&', $parts);
$params['sign'] = md5($signStr . $merchantKey);
$params['sign_type'] = 'MD5';
```

### 下单请求示例

```bash
curl -X POST http://localhost:8080/mapi.php \
  -d "pid=你的商户ID" \
  -d "type=alipay" \
  -d "out_trade_no=ORDER202604180001" \
  -d "notify_url=https://example.com/notify" \
  -d "return_url=https://example.com/return" \
  -d "name=测试订单" \
  -d "money=10.00" \
  -d "sign=这里填签名" \
  -d "sign_type=MD5"
```

### 下单返回说明

创建成功后，系统会生成订单、支付页和状态令牌。  
不同入口返回形式略有区别：

- `/submit.php`：直接进入支付页
- `/mapi.php`：返回 JSON
- `/api.php?act=submit&format=json`：返回 JSON

如果是经营码模式，系统可能会自动把实际支付金额调整为 `10.01`、`10.02` 这种带偏移的值，用于区分同金额订单。

### 查询单个订单

商户服务端查询：

```bash
GET /api.php?act=order&pid=商户ID&key=商户密钥&out_trade_no=订单号
```

成功时会返回类似：

```json
{
  "code": 1,
  "msg": "SUCCESS",
  "trade_no": "20260418123000123456",
  "out_trade_no": "ORDER202604180001",
  "type": "alipay",
  "pid": "1001xxxxxxxxxxxx",
  "addtime": "2026-04-18 12:30:00",
  "endtime": "2026-04-18 12:31:08",
  "name": "测试订单",
  "money": "10.00",
  "status": 1
}
```

状态说明：

- `0`：待支付
- `1`：已支付
- `2`：已过期

### 查询多笔订单

```bash
GET /api.php?act=orders&pid=商户ID&key=商户密钥&limit=20
```

### 查询商户信息

```bash
GET /api.php?act=query&pid=商户ID&key=商户密钥
```

这个接口现在主要用于兼容旧的 CodePay 风格对接。

当前版本更重要的商户信息来源仍然是后台“商户配置 (CodePay)”页面。  
另外，后台里原先那个“商户余额”展示字段已经移除，不再作为后台配置项维护。

### 支付回调说明

订单支付成功后，系统会向你下单时传入的 `notify_url` 发起通知。

回调参数包含：

```text
pid
trade_no
out_trade_no
type
name
money
trade_status
sign
sign_type
```

其中：

- `trade_status` 成功时固定为 `TRADE_SUCCESS`
- `sign_type` 为 `MD5`

### 你的回调地址需要怎么返回

你的服务端验证签名成功、并且业务处理成功后，必须原样输出：

```text
success
```

如果没有返回 `success`，系统会认为通知失败。

### 回调处理建议

你的回调逻辑里至少做这几件事：

1. 验证签名
2. 验证订单号是否存在
3. 验证金额是否一致
4. 判断订单是否已处理，避免重复加款
5. 业务处理成功后输出 `success`

项目里 [notify.php](notify.php) 里有一份演示逻辑，可以参考，但上线前建议按你自己的业务系统重写。

### 支付页状态轮询

支付页内部查询订单状态时，不走商户密钥，而是用订单自己的 `status_token`。  
这个令牌是订单级别的，不要自己伪造，也不要拿它替代商户密钥去做服务端管理查询。

### 对接时常见坑

- `type` 不是 `alipay`
- 签名时参数顺序错了
- 签名串里混入了空参数
- 用了错误的商户密钥
- `notify_url` 没有返回纯文本 `success`
- 经营码模式下，你拿“原始金额”去硬比账单金额，忽略了系统自动偏移

## 上线前建议自测一遍

至少走完这套流程：

1. 启动项目
2. 登录后台
3. 配好支付宝连接
4. 点击测试连接
5. 上传经营码或切换到转账模式
6. 创建一笔测试订单
7. 打开支付页
8. 完成支付
9. 确认订单变为已支付
10. 连续创建几笔同金额订单，确认经营码偏移金额正常工作
11. 再放一笔订单超时，确认会变成已过期
12. 导出一次备份，再恢复一次，确认配置、订单和经营码都能回来

## 常见问题

### 1. 为什么 README 里还保留了 `cp config/alipay.example.php config/alipay.php`？

因为当前代码启动时会直接读取 `config/alipay.php`。  
所以这个文件必须先存在，但**真正的参数已经不建议再靠手动改文件维护了**，而是通过后台保存。

### 2. 为什么测试连接成功了，监控状态却不是健康？

“测试连接成功”只代表支付宝参数可用。  
“监控状态”还取决于账单轮询最近是否运行、状态文件是否更新、数据库是否正常。

### 3. 为什么订单会变成已过期？

待支付订单超过设定时间后会自动标记为 `已过期`。  
默认超时时间一般是 300 秒。

### 4. 为什么经营码换了，页面还没变？

当前版本已经做了防缓存处理。  
如果浏览器还显示旧图，先强刷页面，再检查后台预览是否已更新。

### 5. 为什么 push 到 main 后会自动出现新的 Release？

因为仓库已经配置了自动发版工作流。  
只要你把代码推到 `main`，并且当前提交还没有版本 tag，GitHub Actions 就会自动创建一个新的 patch 版本 release。

## 安全提醒

- 不要提交 `config/alipay.php`
- 不要提交 `config/codepay.json`
- 不要提交 `data/codepay.db`
- 不要提交经营码图片
- 不要公开商户密钥
- 默认密码改掉再上线
- 建议生产环境使用 HTTPS

## License

MIT

## 免责声明

本项目仅供学习和合法业务场景使用。请在使用前自行确认相关法律法规、平台规则和支付宝协议要求。
