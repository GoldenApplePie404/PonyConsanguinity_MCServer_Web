# 爱发电开发者文档


爱发电为开发者提供了 **API**、**Webhook**、**OAuth2.0** 和 **网页嵌入** 等多种方式来集成其平台功能。

*   **开发者后台地址**: [https://afdian.com/dashboard/dev](https://afdian.com/dashboard/dev)
*   **涉及功能**: API 与 Webhook、OAuth2、网页嵌入

---

## 目录

*   [Webhook](#webhook)
*   [API](#api)
*   [OAuth2 关联授权](#oauth2-关联授权)
*   [网页嵌入 (Frontend Embedding)](#网页嵌入-frontend-embedding)
*   [URL 参数 (URL Parameters)](#url-参数-url-parameters)
*   [爱发电与 Kook/DC 联动](#爱发电与-kookdc-联动)
*   [附录: 错误码](#附录-错误码)
*   [附录: 字段说明](#附录-字段说明)

---

## Webhook

### 概述

Webhook 功能允许您在开发者后台配置一个 URL 地址。每当平台上产生新订单时，爱发电服务器会向您配置的 URL 发送 POST 请求，将订单数据实时推送给您的应用。

> **注意**: 如果您的服务器出现异常或未返回正确的确认信息，可能会导致推送失败。因此，建议将 Webhook 与 API 结合使用，以确保数据的完整性和准确性。

### 配置与使用

1.  在 [开发者后台](https://afdian.com/dashboard/dev) 设置用于接收数据的通知 URL。
2.  爱发电服务器会向该 URL 发送 JSON 格式的数据。
3.  您的应用必须在接收到数据后，在短时间内返回特定的 JSON 结构 `{"ec":200,"em":""}`，以告知爱发电服务器已成功接收。若未返回或返回非 `ec: 200`，爱发电将视为推送失败。
4.  为应对网络抖动或服务器异常，系统未来可能会支持对失败请求进行重试，因此建议您的处理逻辑具备**幂等性**，能够安全地处理重复的推送数据。

### 数据格式

**爱发电推送的数据示例:**

```json
{
  "ec": 200,
  "em": "ok",
  "data": {
    "type": "order", // 目前固定为 "order"
    "order": {
      // 订单详细信息，具体字段含义见“附录: 字段说明”
      "out_trade_no": "202106232138371083454010626",
      "custom_order_id": "Steam12345",
      "user_id": "adf397fe8374811eaacee52540025c377",
      "user_private_id": "fdf981fu8f7g891euacee57430321c377",
      "plan_id": "a45353328af911eb973052540025c377",
      "month": 1,
      "total_amount": "5.00",
      "show_amount": "5.00",
      "status": 2,
      "remark": "",
      "redeem_id": "",
      "product_type": 0,
      "discount": "0.00",
      "sku_detail": [
        {
          "sku_id": "b082342c4aba11ebb5cb52540025c377",
          "count": 1,
          "name": "15000 赏金/货币 兑换码",
          "album_id": "",
          "pic": "https://pic1.afdiancdn.com/user/8a8e408a3aeb11eab26352540025c377/common/sfsfsff.jpg"
        }
      ],
      "address_person": "",
      "address_phone": "",
      "address_address": ""
    }
  },
  "sign": "..." // 见下方签名验证
}
```

**您的应用需要响应的数据示例:**

```json
{
  "ec": 200,
  "em": ""
}
```

### 签名验证 (Signature Verification)

为确保数据来源的安全性，爱发电会在推送的数据中加入 `sign` 字段。您可以使用爱发电提供的公钥对接收的数据进行验证。

*   **公钥 (Public Key):**

```
-----BEGIN PUBLIC KEY-----
MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAwwdaCg1Bt+UKZKs0R54y
lYnuANma49IpgoOwNmk3a0rhg/PQuhUJ0EOZSowIC44l0K3+fqGns3Ygi4AfmEfS
4EKbdk1ahSxu7Zkp2rHMt+R9GarQFQkwSS/5x1dYiHNVMiR8oIXDgjmvxuNes2Cr
8fw9dEF0xNBKdkKgG2qAawcN1nZrdyaKWtPVT9m2Hl0ddOO9thZmVLFOb9NVzgYf
jEgI+KWX6aY19Ka/ghv/L4t1IXmz9pctablN5S0CRWpJW3Cn0k6zSXgjVdKm4uN7
jRlgSRaf/Ind46vMCm3N2sgwxu/g3bnooW+db0iLo13zzuvyn727Q3UDQ0MmZcEW
MQIDAQAB
-----END PUBLIC KEY-----
```

*   **签名字符串 (Sign String)**:
    签名字符串 `sign_str` 是由推送数据中 `data.order` 对象下的 `out_trade_no`, `user_id`, `plan_id`, `total_amount` 这四个字段的值，按照此顺序依次拼接而成的字符串。

    例如，对于上面的示例数据：
    `sign_str` = `"202106232138371083454010626" + "adf397fe8374811eaacee52540025c377" + "a45353328af911eb973052540025c377" + "5.00"`

*   **验证方法**:

```php
/**
 * @param string $sign_str 待验证的签名字符串 (由 out_trade_no, user_id, plan_id, total_amount 拼接)
 * @param string $sign 推送数据中的 sign 字段值
 * @return bool 验证结果，1为成功，0为失败
 */
public function verifySign($sign_str, $sign) {
    $publicKey = "-----BEGIN PUBLIC KEY-----
... (上面的公钥内容) ...
-----END PUBLIC KEY-----";

    $key = openssl_get_publickey($publicKey);
    // 使用 SHA256 算法进行 RSA 验证
    return openssl_verify($sign_str, base64_decode($sign), $key, 'SHA256');
}
```

---

## API

### 概述

API 功能需要开发者主动向爱发电平台发起 HTTP 请求，以获取特定的数据，如历史订单列表或赞助者信息。

使用 API 需要以下凭证，可在开发者后台找到：
*   `user_id`: 用于标识您的开发者身份。
*   `api_token` (或简称 `token`): 用于签名和身份验证。

所有请求均使用 `POST` 方法，数据格式支持 `application/x-www-form-urlencoded` 或 `application/json`。平台返回的数据格式统一为 JSON。

### 请求签名 (Request Signature)

为保证请求数据的完整性和安全性，爱发电平台要求对所有 API 请求进行签名。

#### 参数构成

发送请求时，需包含以下四个顶级参数：

| 参数名 | 类型 | 必填 | 说明 |
| :--- | :--- | :--- | :--- |
| `user_id` | String | 是 | 开发者后台的 user_id |
| `params` | String | 是 | 具体业务参数组成的 **JSON 字符串** |
| `ts` | Integer | 是 | 发起请求时的 **秒级时间戳** |
| `sign` | String | 是 | 根据 `token` 和其他三个参数计算得出的签名 |

#### 签名算法

`sign` 的计算公式为：`MD5(token + 拼接字符串)`。

**拼接字符串**的规则是：将 `params`, `ts`, `user_id` 这三个参数名和它们对应的值按字母顺序排列（`params`, `ts`, `user_id`），然后将参数名和参数值直接相连，中间无任何分隔符。

最终计算公式为：
`sign = MD5( "{token}" + "params" + "{params_value}" + "ts" + "{ts_value}" + "user_id" + "{user_id_value}" )`

**示例:**

假设：
*   `user_id` = `abc`
*   `params` = `{"a": 333}`
*   `ts` = `1624339905`
*   `token` = `123` (注意：此值不作为请求参数发送，仅用于签名计算)

拼接字符串为: `params{"a":333}ts1624339905user_idabc`
计算 `sign`: `MD5("123" + "params{"a":333}ts1624339905user_idabc")` = `a4acc28b81598b7e5d84ebdc3e91710c`

**发送的请求体 (JSON 格式) 示例:**

```json
{
  "user_id": "abc",
  "params": "{\"a\": 333}",
  "ts": 1624339905,
  "sign": "a4acc28b81598b7e5d84ebdc3e91710c"
}
```

#### 签名校验

您可以使用 `/api/open/ping` 接口来测试您的签名逻辑是否正确。

*   **请求地址:** `https://afdian.com/api/open/ping`
*   **请求方式:** `POST`
*   **请求体:** 包含 `user_id`, `params`, `ts`, `sign` 的表单或 JSON。

**响应示例:**

*   **成功 (`ec: 200`):**
    ```json
    {
      "ec": 200,
      "em": "pong",
      "data": {
        "uid": "xxxxxxx",
        "request": {
          "user_id": "xxxxxxx",
          "params": "{\"a\":333}",
          "ts": 1636732646,
          "sign": "a9fc8cafd2c1e2902cac00fc26f38e2d"
        }
      }
    }
    ```

*   **签名失败 (`ec: 400005`):**
    ```json
    {
      "ec": 400005,
      "em": "sign validation failed",
      "data": {
        "explain": "plz check the desc",
        "debug": {
          "kv_string": "params{\"a\":333}ts1636732646user_idxxxxx" // 提供了用于调试的拼接字符串
        },
        "request": {
          "user_id": "xxxxxxx",
          "params": "{\"a\":333}",
          "ts": 1636732646,
          "sign": "a9fc8cafd2c1e290cac00fc26f38e2d"
        }
      }
    }
    ```
    您可以复制 `debug.kv_string` 的值，然后用您的 `token` 和 MD5 算法计算 `sign` 进行对比。

*   **时间戳过期 (`ec: 400002`):**
    ```json
    {
      "ec": 400002,
      "em": "time was expired",
      "data": {
        "explain": "ts is outdated, 3600s latency was allowed" // 时间戳有效期为1小时(3600秒)
      }
    }
    ```

### API 接口列表

#### 1. 查询历史订单

*   **请求地址:** `https://afdian.com/api/open/query-order`
*   **请求方式:** `POST`

**请求参数 (`params` JSON 内容):**

| 参数名 | 类型 | 必填 | 说明 |
| :--- | :--- | :--- | :--- |
| `page` | Integer | 否 | 页码，用于分页查询，默认为 1。按订单创建时间倒序排列。 |
| `out_trade_no` | String | 否 | 指定订单号查询。支持查询多个，用英文逗号 `,` 分隔。 |
| `per_page` | Integer | 否 | 每页返回数量，默认为 50，范围 1-100。 |

> **注意:** `page` 和 `out_trade_no` 不能同时使用。

**响应示例:**

```json
{
  "ec": 200,
  "em": "",
  "data": {
    "list": [
      {
        // 订单详情对象，字段含义见“附录: 字段说明”
        "out_trade_no": "202106232138371083454010626",
        "custom_order_id": "Steam12345",
        "user_id": "adf397fe8374811eaacee52540025c377",
        "user_private_id": "33这个是每个用户唯一的，相当于微信的 unionid",
        "plan_id": "a45353328af911eb973052540025c377",
        "month": 1,
        "total_amount": "5.00",
        "show_amount": "5.00",
        "status": 2,
        "remark": "",
        "redeem_id": "",
        "product_type": 0,
        "discount": "0.00",
        "sku_detail": [...],
        "address_person": "",
        "address_phone": "",
        "address_address": ""
      }
    ],
    "total_count": 167, // 总订单数
    "total_page": 11   // 总页数
  }
}
```

#### 2. 查询赞助者

*   **请求地址:** `https://afdian.com/api/open/query-sponsor`
*   **请求方式:** `POST`

**请求参数 (`params` JSON 内容):**

| 参数名 | 类型 | 必填 | 说明 |
| :--- | :--- | :--- | :--- |
| `page` | Integer | 否 | 页码，用于分页查询，默认为 1。按建立赞助关系时间倒序排列。 |
| `user_id` | String | 否 | 查询指定用户的赞助情况。支持查询多个，用英文逗号 `,` 分隔。 |
| `per_page` | Integer | 否 | 每页返回数量，默认为 20，范围 1-100。 |

> **注意:** `page` 和 `user_id` 不能同时使用。

**响应示例:**

```json
{
  "ec": 200,
  "em": "",
  "data": {
    "total_count": 14, // 总赞助人数
    "total_page": 2,   // 总页数
    "list": [
      {
        "sponsor_plans": [], // 此用户曾经赞助过的计划列表
        "current_plan": {    // 此用户当前的赞助计划
          "name": ""         // 如果 name 为空字符串，则表示当前无有效计划
        },
        "all_sum_amount": "0.00", // 累计赞助金额
        "create_time": 1581011280, // 成为赞助者的 Unix 时间戳
        "last_pay_time": 1598852327 // 最近一次支付的 Unix 时间戳
        // ...
      },
      {
        "sponsor_plans": [
          // ... 计划详情 ...
        ],
        "current_plan": {
          // ... 当前计划详情 ...
        },
        "all_sum_amount": "13.00",
        "first_pay_time": 1576776221, // 首次支付的 Unix 时间戳
        "last_pay_time": 1581083107,
        "user": { // 赞助者用户信息
          "user_id": "sfff",
          "name": "sfsf：十五种幸福（新版）",
          "avatar": "https://pic1.afdiancdn.com/user/sdfsfsf/avatar/c13b6125cbd9fbe7810c79256df1f5b2_w4032_h3024_s3215.jpeg"
        }
      }
    ]
  }
}
```

#### 3. 查询随机自动回复 (2025年5月14日新增)

*   **请求地址:** `https://afdian.com/api/open/query-random-reply`
*   **请求方式:** `POST`

**请求参数 (`params` JSON 内容):**

| 参数名 | 类型 | 必填 | 说明 |
| :--- | :--- | :--- | :--- |
| `out_trade_no` | String | 是 | 指定订单号查询。支持查询多个，用英文逗号 `,` 分隔。 |

**响应示例:**

```json
{
    "ec": 200,
    "em": "success",
    "data": {
        "list": [
            {
                "out_trade_no": "202505141538455397541020050",
                "content": "999" // 随机回复内容
            }
        ]
    }
}
```

#### 4. 更新方案自动回复 (2025年8月14日新增)

*   **请求地址:** `https://afdian.com/api/open/update-plan-reply`
*   **请求方式:** `POST`

**请求参数 (`params` JSON 内容):**

| 参数名 | 类型 | 必填 | 说明 |
| :--- | :--- | :--- | :--- |
| `plan_id` | String | 二选一 | 方案 ID。用于更新订阅类方案的回复。 |
| `sku_id` | String | 二选一 | SKU ID。用于更新售卖类商品的回复。 |
| `auto_reply` | String | 否 | 新的自动回复内容。如果非空，将覆盖原有内容；如果为空或不传，则不更新。 |
| `auto_random_reply` | String | 否 | 新的自动随机回复内容。如果非空，才会更新；如果为空或不传，则不更新。 |
| `update_random_reply_type` | String | 条件必填 | 更新随机回复的方式。当 `auto_random_reply` 不为空时，此项必填。可选值: `append` (追加), `overwrite` (覆盖)。 |

> **注意:** `plan_id` 和 `sku_id` 只能选择一个传入。

#### 5. 发送私信 (2025年8月14日新增)

*   **请求地址:** `https://afdian.com/api/open/send-msg`
*   **请求方式:** `POST`
*   **频率限制:** 10次/秒，1000次/小时

**请求参数 (`params` JSON 内容):**

| 参数名 | 类型 | 必填 | 说明 |
| :--- | :--- | :--- | :--- |
| `recipient` | String | 是 | 接收私信的用户 ID。 |
| `content` | String | 是 | 私信内容。 |

#### 6. 查询方案信息 (2025年8月14日新增)

*   **请求地址:** `https://afdian.com/api/open/query-plan`
*   **请求方式:** `POST`

**请求参数 (`params` JSON 内容):**

| 参数名 | 类型 | 必填 | 说明 |
| :--- | :--- | :--- | :--- |
| `plan_id` | String | 是 | 方案 ID。 |

**响应示例:**

```json
{
    "ec": 200,
    "em": "获取方案成功",
    "data": {
        "plan": {
            "plan_id": "436af0d0e0xxxxxxxxxxxxxx25c377",
            "price": "5.00",
            "name": "测试售卖动态2",
            "product_type": 1, // 0-订阅 1-商品 2-捆绑包 3-自选包 4-售票
            "desc": "",
            "reply_content": "", // 方案自动回复
            "replay_random_content": "", // 方案自动随机回复
            "independent": 0, // 是否独立方案 0-非独立 1-独立
            "permanent": 0, // 是否永久方案 0-非永久 1-永久
            "pay_month": 1, // 1-月费，3-季费，12-年费
            "skus": [ // SKU 列表，仅商品、捆绑包、自选包、售票类型有此字段
                {
                    "sku_id": "436eba6cexxxxxxxxxxxxxx025c377",
                    "plan_id": "436af0d0e0xxxxxxxxxxxxxx25c377",
                    "name": "型号1",
                    "desc": "",
                    "stock": "",
                    "price": "5.00",
                    "reply_content": "", // SKU 自动回复
                    "reply_random_content": "" // SKU 自动随机回复
                }
            ]
        }
    }
}
```

---

## OAuth2 关联授权

### 概述

OAuth2.0 允许您的应用请求访问用户的爱发电账户信息（如用户ID、昵称、头像），常用于实现“使用爱发电账号登录”等功能。爱发电支持标准的 `authorization_code` 授权码模式。

### 申请流程

接入方需要向爱发电官方申请，提供以下信息：

1.  **应用名称:**
2.  **应用用途:**
3.  **可信域名:**
4.  **clientSecret:** (可选，如不提供，官方将随机生成)

申请格式如下，可通过私信发送给官方：

```
您好，我想申请 OAuth2 功能

应用名称：
应用用途：
可信域名：
clientSecret：（可选）

（私信前，如果您还没有认证，麻烦先完成认证，这样沟通效率更高）
```

申请通过后，官方会提供 `client_id` 和 `client_secret`。

### 授权流程

整个流程分为三步：

#### Step 1: 引导用户授权

构建授权 URL 并引导用户访问。用户将在爱发电页面上看到您的应用名称和图标，并决定是否授权。

**授权 URL:**

```
https://afdian.com/oauth2/authorize?
response_type=code&
scope=basic&
client_id={your_client_id}&
redirect_uri={url_encoded_redirect_uri}&
state={state}
```

**参数说明:**

| 参数名 | 是否必需 | 说明 |
| :--- | :--- | :--- |
| `response_type` | 是 | 固定值 `code`。 |
| `scope` | 是 | 固定值 `basic`。 |
| `client_id` | 是 | 申请时获得的 `client_id`。 |
| `redirect_uri` | 是 | 授权回调地址。必须与申请时提交的可信域名匹配。支持 `http` 和 `https`，但生产环境强烈推荐使用 `https`。 |
| `state` | 是 | 用于防止 CSRF 攻击的随机字符串，爱发电会原样返回。 |

#### Step 2: 接收授权码 (Code)

用户同意授权后，爱发电服务器会将用户重定向到您之前设置的 `redirect_uri`，并在 URL 中附带 `code` 和 `state` 参数。

**重定向示例:**

```
{your_redirect_uri}?code=AUTHORIZATION_CODE&state=STATE
```

您的应用需要从 URL 中提取 `code` 和 `state`。请注意验证 `state` 参数以防范 CSRF 攻击。

#### Step 3: 获取 Access Token 和用户信息

使用上一步获得的 `code`，向爱发电服务器请求换取 `access_token` 和用户信息。

**请求地址:** `https://afdian.com/api/oauth2/access_token`

**请求方式:** `POST`

**请求参数 (Form 表单格式):**

| 参数名 | 类型 | 必填 | 说明 |
| :--- | :--- | :--- | :--- |
| `grant_type` | String | 是 | 固定值 `authorization_code`。 |
| `client_id` | String | 是 | 申请时获得的 `client_id`。 |
| `client_secret` | String | 是 | 申请时获得的 `client_secret`。 |
| `code` | String | 是 | Step 2 中获取的授权码。 |
| `redirect_uri` | String | 是 | 必须与 Step 1 中使用的 `redirect_uri` 完全一致。 |

> **重要:** 此步骤 **必须在您的服务器端** 发起请求，以保护 `client_secret` 的安全。

**响应示例:**

```json
{
  "ec": 200,
  "em": "ok",
  "data": {
    "user_id": "网站的用户ID",
    "user_private_id": "同 OpenAPI 的 user_private_id，如尚未用到，可忽略",
    "name": "用户昵称",
    "avatar": "头像链接"
  }
}
```

---

## 网页嵌入 (Frontend Embedding)

您可以轻松地将爱发电的赞助模块嵌入到您的网站、博客或论坛中，以吸引访问者成为您的赞助者。

### 赞助页面嵌入 (Widget)

**效果**: 在您的页面上嵌入一个爱发电的赞助页面小部件。

**使用方法**: 将以下代码嵌入到您想展示的位置：

```html
<iframe src="https://afdian.com/leaflet?slug={1}" width="640" scrolling="no" height="200" frameborder="0"> </iframe>
```

**参数说明**:
*   将代码中的 `{1}` 替换为您爱发电个人主页地址后缀（即 `https://afdian.com/@your_slug` 中的 `your_slug`）。

**示例**:
为爱发电官方账号 (slug: `afdian`) 嵌入代码:
```html
<iframe src="https://afdian.com/leaflet?slug=afdian" width="640" scrolling="no" height="200" frameborder="0"> </iframe>
```

**移动端优化代码**:
以下代码可以根据屏幕大小调整宽度，更适合在手机上浏览：
```html
<iframe id="afdian_leaflet_{1}" src="https://afdian.com/leaflet?slug={1}" width="100%" scrolling="no" height="200" frameborder="0"> </iframe>
<script>
document.body.clientWidth < 700 ? document.getElementById("afdian_leaflet_{1}").width = "100%" : document.getElementById("afdian_leaflet_{1}").width = "640"
</script>
```
*(感谢创作者 [小黑纸菌](https://afdian.com/@blmcpia) 的支持)*

### 赞助按钮

**效果**: 在您的页面上放置一个醒目的“赞助我”按钮。

**使用方法**: 将以下代码嵌入到您想展示按钮的位置：

```html
<a href="https://afdian.com/a/{your_slug}">
    <img width="200" src="https://pic1.afdiancdn.com/static/img/welcome/button-sponsorme.png" alt="赞助我">
</a>
```

**参数说明**:
*   将代码中的 `{your_slug}` 替换为您爱发电个人主页地址后缀。

---

## URL 参数 (URL Parameters)

您可以在爱发电的下单页面 URL 中添加特定参数，以实现预设订单信息等自动化效果。

**基础格式**:
```
https://afdian.com/order/create?plan_id={plan_id}&parameter1=value1&parameter2=value2...
```

**可用参数列表**:

| 参数名 | 含义 | 示例 |
| :--- | :--- | :--- |
| `plan_id` | **必需**。目标方案的 ID。 | `aad691f291c211ea81dc52540025c377` |
| `remark` | 订单留言 | `remark=支持爱发电越来越好~` |
| `month` | 选择发电的月数 | `month=3` (表示赞助3个月) |
| `custom_order_id` | 自定义订单号 | `custom_order_id=MyCustomID123` |
| `custom_price` | 自选金额发电的金额数 (单位: 分) | `custom_price=500` (表示支付5.00元) |

**示例**:

1.  为 Lain音酱 的某个 20 元档位（ID: `aad691f291c211ea81dc52540025c377`）发电 3 个月并留言:
    ```
    https://afdian.com/order/create?plan_id=aad691f291c211ea81dc52540025c377&product_type=0&month=3&remark=%E6%94%AF%E6%8C%81%E7%88%B1%E5%8F%91%E7%94%B5%E8%B6%8A%E6%9D%A5%E8%B6%8A%E5%A5%BD~
    ```

---

## 爱发电与 Kook/DC 联动

您可以利用爱发电的 URL 参数和 Webhook 功能，实现与 Kook、Discord 等社群平台的自动化联动，例如为付费用户自动授予角色。

### 联动流程示例

1.  **创建机器人**: 在 Kook 或 Discord 中创建一个机器人 Bot。
2.  **生成带参链接**: 在机器人中设置一个指令或消息，其内容是一个指向爱发电订阅方案的链接，并使用 URL 参数预填 `remark`，例如 `remark={用户名}_{用户ID}`。
3.  **用户点击与支付**: 用户点击机器人发送的链接，跳转到爱发电支付页面。此时，用户的 Kook/DC 用户名和 ID 已经自动填写到留言框中。
4.  **Webhook 接收订单**: 您的服务器配置 Webhook 监听爱发电的订单推送。
5.  **解析订单信息**: 当收到新订单时，解析订单中的 `remark` 字段，提取出 Kook/DC 用户名和 ID。
6.  **执行操作**: 根据提取的信息，调用 Kook/DC 的机器人 API，为对应的用户授予指定的角色。
7.  **管理有效期**: 开发者可以自行设定一个定时任务或倒计时逻辑，根据订单的 `month` 信息计算到期时间，并在到期后移除对应用户的角色。

*(图示: [来自原文档的流程图])*

---

## 附录: 错误码

| 错误码 (ec) | 说明 |
| :--- | :--- |
| 200 | 请求成功 |
| 400001 | 参数不完整 (params incomplete) |
| 400002 | 时间戳过期 (time was expired) |
| 400003 | params 不是有效的 JSON 字符串 (params was not valid json string) |
| 400004 | 未找到有效的 Token (no valid token found) |
| 400005 | 签名验证失败 (sign validation failed) |

---

## 附录: 字段说明

### 订单 (Order) 字段

| 字段名 | 含义 |
| :--- | :--- |
| `out_trade_no` | 订单号 |
| `custom_order_id` | 自定义信息 |
| `user_id` | 下单用户 ID |
| `user_private_id` | 用户唯一标识，类似微信 UnionID |
| `plan_id` | 方案 ID，如为自选方案，则为空 |
| `month` | 赞助月份 |
| `total_amount` | 真实付款金额，若有兑换码抵扣，则为 0.00 |
| `show_amount` | 显示金额，若有折扣则为折扣前金额 |
| `status` | 订单状态，2 为交易成功。目前 Webhook 仅推送此状态。 |
| `remark` | 订单留言 |
| `redeem_id` | 兑换码 ID |
| `product_type` | 产品类型，0 表示常规方案，1 表示售卖方案 |
| `discount` | 折扣金额 |
| `sku_detail` | SKU 详情数组 (仅售卖类商品有此字段) |
| `address_person` | 收件人姓名 |
| `address_phone` | 收件人电话 |
| `address_address` | 收件人地址 |

### 赞助者 (Sponsor) 字段

| 字段名 | 含义 |
| :--- | :--- |
| `sponsor_plans` | 用户曾经赞助过的计划列表 (数组) |
| `current_plan` | 用户当前的赞助计划详情对象 |
| `all_sum_amount` | 累计赞助总金额 (折扣前金额，若有兑换码则为虚拟金额) |
| `create_time` | 成为赞助者的 Unix 时间戳 |
| `first_pay_time` | 首次支付的 Unix 时间戳 |
| `last_pay_time` | 最近一次支付的 Unix 时间戳 |
| `user.user_id` | 赞助者用户唯一 ID |
| `user.name` | 赞助者昵称 |
| `user.avatar` | 赞助者头像链接 |

---