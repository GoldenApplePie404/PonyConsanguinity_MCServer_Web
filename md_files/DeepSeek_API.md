# DeepSeek API 使用文档

## 目录
1. [快速开始](#快速开始)
2. [模型与价格](#模型与价格)
3. [API 接口详解](#api-接口详解)
4. [高级功能](#高级功能)
5. [最佳实践](#最佳实践)
6. [常见问题](#常见问题)

---

## 快速开始

### 基本配置

DeepSeek API 与 OpenAI API 完全兼容，只需修改配置即可使用：

| 配置项 | 值 |
|--------|-----|
| Base URL | `https://api.deepseek.com` 或 `https://api.deepseek.com/v1` |
| API Key | 需要在 [DeepSeek 平台](https://platform.deepseek.com/api_keys) 申请 |

### 快速调用示例

#### Python 示例
```python
# 安装 OpenAI SDK: pip3 install openai
from openai import OpenAI

client = OpenAI(
    api_key="your-api-key",
    base_url="https://api.deepseek.com"
)

response = client.chat.completions.create(
    model="deepseek-chat",
    messages=[
        {"role": "system", "content": "You are a helpful assistant"},
        {"role": "user", "content": "Hello!"}
    ],
    stream=False
)

print(response.choices[0].message.content)
```

#### cURL 示例
```bash
curl https://api.deepseek.com/chat/completions \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer ${DEEPSEEK_API_KEY}" \
  -d '{
    "model": "deepseek-chat",
    "messages": [
      {"role": "system", "content": "You are a helpful assistant"},
      {"role": "user", "content": "Hello!"}
    ],
    "stream": false
  }'
```

#### Node.js 示例
```javascript
import OpenAI from "openai";

const openai = new OpenAI({
  baseURL: 'https://api.deepseek.com',
  apiKey: process.env.DEEPSEEK_API_KEY,
});

const completion = await openai.chat.completions.create({
  messages: [{ role: "system", content: "You are a helpful assistant." }],
  model: "deepseek-chat",
});

console.log(completion.choices[0].message.content);
```

---

## 模型与价格

### 可用模型

| 模型 | 版本 | 模式 | 上下文长度 | 输出长度 |
|------|------|------|------------|----------|
| deepseek-chat | DeepSeek-V3.2 | 非思考模式 | 128K | 默认4K，最大8K |
| deepseek-reasoner | DeepSeek-V3.2 | 思考模式 | 128K | 默认32K，最大64K |

### 价格详情

| 项目 | 价格 |
|------|------|
| 输入 tokens（缓存命中） | 0.2元 / 百万 tokens |
| 输入 tokens（缓存未命中） | 2元 / 百万 tokens |
| 输出 tokens | 3元 / 百万 tokens |

### 扣费规则
- 扣减费用 = token 消耗量 × 模型单价
- 优先扣减赠送余额，再扣减充值余额
- 价格可能变动，请定期查看官方文档

---

## API 接口详解

### 对话补全 API

**端点：** `POST https://api.deepseek.com/chat/completions`

#### 请求参数

##### 核心参数
- `model` (required): 模型名称，`deepseek-chat` 或 `deepseek-reasoner`
- `messages` (required): 对话消息列表

##### 消息结构
```json
{
  "role": "system|user|assistant|tool",
  "content": "消息内容",
  "name": "可选参与者名称"
}
```

##### 可选参数

**输出控制：**
- `temperature` (0-2): 控制随机性，默认1.0
- `top_p` (0-1): 核采样，默认1.0
- `max_tokens`: 限制输出长度
- `stop`: 停止序列，字符串或字符串数组

**频率与存在惩罚：**
- `frequency_penalty` (-2.0到2.0): 降低重复，默认0
- `presence_penalty` (-2.0到2.0): 鼓励新话题，默认0

**流式输出：**
- `stream` (boolean): 是否启用流式输出
- `stream_options.include_usage` (boolean): 是否返回用量信息

**格式控制：**
- `response_format`: `{"type": "text"|"json_object"}`

**工具调用：**
- `tools`: 工具定义列表
- `tool_choice`: 工具调用策略

**思考模式：**
- `thinking`: `{"type": "enabled"|"disabled"}`

#### 响应结构

**非流式响应：**
```json
{
  "id": "chatcmpl-xxx",
  "object": "chat.completion",
  "created": 1234567890,
  "model": "deepseek-chat",
  "choices": [{
    "index": 0,
    "message": {
      "role": "assistant",
      "content": "回复内容",
      "reasoning_content": "思考内容(仅reasoner模型)"
    },
    "finish_reason": "stop"
  }],
  "usage": {
    "prompt_tokens": 10,
    "completion_tokens": 20,
    "total_tokens": 30,
    "prompt_cache_hit_tokens": 5,
    "prompt_cache_miss_tokens": 5
  }
}
```

**finish_reason 可能值：**
- `stop`: 正常停止
- `length`: 达到长度限制
- `content_filter`: 内容被过滤
- `tool_calls`: 工具调用
- `insufficient_system_resource`: 资源不足

### 余额查询 API

**端点：** `GET https://api.deepseek.com/user/balance`

**响应示例：**
```json
{
  "is_available": true,
  "balance_infos": [{
    "currency": "CNY",
    "total_balance": "100.00",
    "granted_balance": "50.00",
    "topped_up_balance": "50.00"
  }]
}
```

---

## 高级功能

### JSON Output 模式

确保模型输出合法 JSON 格式：

```python
import json
from openai import OpenAI

client = OpenAI(
    api_key="your-api-key",
    base_url="https://api.deepseek.com"
)

system_prompt = """
请解析问题并输出JSON格式。
示例：
问题：世界上最高的山是哪座？
答案：珠穆朗玛峰

输出格式：
{
  "question": "问题内容",
  "answer": "答案内容"
}
"""

response = client.chat.completions.create(
    model="deepseek-chat",
    messages=[
        {"role": "system", "content": system_prompt},
        {"role": "user", "content": "世界上最长的河是哪条？尼罗河。"}
    ],
    response_format={'type': 'json_object'}
)

result = json.loads(response.choices[0].message.content)
print(result)
```

**注意事项：**
1. 系统或用户消息中必须包含 "json" 字样
2. 建议提供 JSON 格式示例
3. 合理设置 max_tokens 避免截断

### Tool Calls 工具调用

让模型调用外部工具增强能力：

```python
from openai import OpenAI

client = OpenAI(
    api_key="your-api-key",
    base_url="https://api.deepseek.com"
)

# 定义工具
tools = [{
    "type": "function",
    "function": {
        "name": "get_weather",
        "description": "获取指定位置的天气信息",
        "parameters": {
            "type": "object",
            "properties": {
                "location": {
                    "type": "string",
                    "description": "城市和州，例如：San Francisco, CA"
                }
            },
            "required": ["location"]
        }
    }
}]

# 发送消息
messages = [{"role": "user", "content": "杭州的天气怎么样？"}]
response = client.chat.completions.create(
    model="deepseek-chat",
    messages=messages,
    tools=tools
)

# 处理工具调用
tool_call = response.choices[0].message.tool_calls[0]
print(f"调用函数: {tool_call.function.name}")
print(f"参数: {tool_call.function.arguments}")

# 执行工具并继续对话
messages.append(response.choices[0].message)
messages.append({
    "role": "tool",
    "tool_call_id": tool_call.id,
    "content": "24℃"
})

response = client.chat.completions.create(
    model="deepseek-chat",
    messages=messages
)

print(f"回复: {response.choices[0].message.content}")
```

**Strict 模式（Beta）：**
确保函数调用严格符合 JSON Schema：
```python
tools = [{
    "type": "function",
    "function": {
        "name": "get_weather",
        "strict": True,  # 开启 strict 模式
        "description": "获取天气",
        "parameters": {
            "type": "object",
            "properties": {
                "location": {"type": "string"},
                "date": {"type": "string"}
            },
            "required": ["location", "date"],
            "additionalProperties": False
        }
    }
}]
```

使用 Strict 模式需：
1. 设置 `base_url="https://api.deepseek.com/beta"`
2. 所有 function 设置 `strict: true`
3. 遵循支持的 JSON Schema 类型

### 思考模式

在输出最终答案前输出思维链，提升准确性：

#### 开启方式
1. 使用 `deepseek-reasoner` 模型
2. 设置 `thinking={"type": "enabled"}`

```python
from openai import OpenAI

client = OpenAI(
    api_key="your-api-key",
    base_url="https://api.deepseek.com"
)

# 方法1：使用 reasoner 模型
response = client.chat.completions.create(
    model="deepseek-reasoner",
    messages=[{"role": "user", "content": "9.11和9.8哪个更大？"}]
)

# 方法2：在 chat 模型中开启思考模式
response = client.chat.completions.create(
    model="deepseek-chat",
    messages=[{"role": "user", "content": "9.11和9.8哪个更大？"}],
    extra_body={"thinking": {"type": "enabled"}}
)

# 获取思维链和最终答案
reasoning = response.choices[0].message.reasoning_content
answer = response.choices[0].message.content

print(f"思考过程：{reasoning}")
print(f"最终答案：{answer}")
```

#### 思考模式特点
- **max_tokens**：默认32K，最大64K
- **不支持的参数**：temperature、top_p、presence_penalty、frequency_penalty、logprobs
- **多轮对话**：只传 content，不传 reasoning_content

### 上下文硬盘缓存

自动缓存机制，降低重复请求成本：

#### 工作原理
- 每个请求构建缓存
- 相同前缀的后续请求命中缓存
- 缓存以 64 tokens 为存储单元

#### 使用场景

**1. 长文本问答：**
```python
# 第一次请求
messages = [
    {"role": "system", "content": "你是一位资深财报分析师..."},
    {"role": "user", "content": f"{财报内容}\n\n请总结关键信息。"}
]

# 第二次请求 - 财报部分会缓存命中
messages = [
    {"role": "system", "content": "你是一位资深财报分析师..."},
    {"role": "user", "content": f"{财报内容}\n\n请分析盈利情况。"}
]
```

**2. 多轮对话：**
```python
# 第一次对话
messages = [
    {"role": "system", "content": "你是一位乐于助人的助手"},
    {"role": "user", "content": "中国的首都是哪里？"}
]
response1 = client.chat.completions.create(model="deepseek-chat", messages=messages)

# 第二次对话 - 前面的消息会缓存命中
messages.append({"role": "assistant", "content": response1.choices[0].message.content})
messages.append({"role": "user", "content": "美国的首都是哪里？"})
response2 = client.chat.completions.create(model="deepseek-chat", messages=messages)

# 查看缓存命中情况
print(f"缓存命中: {response2.usage.prompt_cache_hit_tokens}")
print(f"缓存未命中: {response2.usage.prompt_cache_miss_tokens}")
```

**3. Few-shot 学习：**
```python
few_shots = [
    {"role": "system", "content": "你是一位历史学专家，回答以 Answer: 开头"},
    {"role": "user", "content": "秦始皇统一六国是哪一年？"},
    {"role": "assistant", "content": "Answer:公元前221年"},
    # ... 更多示例
]

# 第一次请求
messages1 = few_shots + [{"role": "user", "content": "明朝开国皇帝是谁？"}]
response1 = client.chat.completions.create(model="deepseek-chat", messages=messages1)

# 第二次请求 - Few-shot 示例会缓存命中
messages2 = few_shots + [{"role": "user", "content": "商朝什么时候灭亡？"}]
response2 = client.chat.completions.create(model="deepseek-chat", messages=messages2)
```

#### 缓存费用
- 缓存命中：0.2元 / 百万 tokens
- 缓存未命中：2元 / 百万 tokens

### 对话前缀续写（Beta）

强制模型从指定前缀开始生成：

```python
from openai import OpenAI

client = OpenAI(
    api_key="your-api-key",
    base_url="https://api.deepseek.com/beta"  # 需要使用 beta URL
)

messages = [
    {"role": "user", "content": "请写快速排序代码"},
    {"role": "assistant", "content": "```python\n", "prefix": True}
]

response = client.chat.completions.create(
    model="deepseek-chat",
    messages=messages,
    stop=["```"]
)

print(response.choices[0].message.content)
```

### FIM 补全（Beta）

填补文本中间内容，适用于代码补全：

```python
from openai import OpenAI

client = OpenAI(
    api_key="your-api-key",
    base_url="https://api.deepseek.com/beta"
)

response = client.completions.create(
    model="deepseek-chat",
    prompt="def fib(a):",
    suffix=" return fib(a-1) + fib(a-2)",
    max_tokens=128
)

print(response.choices[0].text)
```

---

## 最佳实践

### Temperature 参数设置建议

| 场景 | Temperature |
|------|-------------|
| 代码生成/数学解题 | 0.0 |
| 数据抽取/分析 | 1.0 |
| 通用对话 | 1.3 |
| 翻译 | 1.3 |
| 创意写作/诗歌 | 1.5 |

### 优化成本

1. **利用上下文缓存**：
   - 保持请求前缀一致性
   - 使用 Few-shot 学习
   - 多轮对话复用上下文

2. **控制输出长度**：
   - 合理设置 max_tokens
   - 使用 stop 参数提前停止

3. **选择合适模型**：
   - 通用任务用 `deepseek-chat`
   - 复杂推理用 `deepseek-reasoner`

### 错误处理

```python
from openai import OpenAI
from openai import APIError, RateLimitError, APIConnectionError

client = OpenAI(api_key="your-api-key", base_url="https://api.deepseek.com")

try:
    response = client.chat.completions.create(
        model="deepseek-chat",
        messages=[{"role": "user", "content": "Hello"}]
    )
except RateLimitError:
    print("请求过于频繁，请稍后重试")
except APIConnectionError:
    print("网络连接错误")
except APIError as e:
    print(f"API错误: {e}")
```

### 流式输出处理

```python
from openai import OpenAI

client = OpenAI(api_key="your-api-key", base_url="https://api.deepseek.com")

stream = client.chat.completions.create(
    model="deepseek-chat",
    messages=[{"role": "user", "content": "写一首关于春天的诗"}],
    stream=True,
    stream_options={"include_usage": True}
)

for chunk in stream:
    if chunk.choices and chunk.choices[0].delta.content:
        print(chunk.choices[0].delta.content, end="", flush=True)
    if chunk.usage:
        print(f"\n\nToken使用情况: {chunk.usage}")
```

---

## 常见问题

### Q1: DeepSeek API 与 OpenAI API 的区别？
A: API 格式完全兼容，主要区别在于：
- Base URL 不同
- 模型名称不同
- 价格更低
- 支持上下文缓存
- 支持思考模式

### Q2: 如何处理超长上下文？
A:
- 模型支持 128K 上下文
- 输出长度限制：chat 模型最大8K，reasoner 最大64K
- 建议使用上下文缓存降低成本

### Q3: 思考模式何时使用？
A:
- 复杂数学计算
- 逻辑推理任务
- 需要高准确性的场景
- 不适合：简单问答、创意写作

### Q4: 如何提高 JSON 输出的准确性？
A:
- 使用 response_format='json_object'
- 在 prompt 中明确要求 JSON
- 提供示例格式
- 合理设置 max_tokens

### Q5: 缓存命中率如何查看？
A:
```python
usage = response.usage
print(f"缓存命中 tokens: {usage.prompt_cache_hit_tokens}")
print(f"缓存未命中 tokens: {usage.prompt_cache_miss_tokens}")
```

### Q6: 如何获取 API Key？
A: 访问 [DeepSeek 平台](https://platform.deepseek.com/api_keys) 创建 API Key

### Q7: 支持哪些语言？
A: 使用 OpenAI SDK，支持 Python、JavaScript、Go、Ruby、C#、PHP、Java 等主流语言

### Q8: Beta 功能如何使用？
A:
- 设置 `base_url="https://api.deepseek.com/beta"`
- 具体功能按文档要求配置参数

---

## 参考资源

- **官方文档**: https://api-docs.deepseek.com/zh-cn/
- **API Key 申请**: https://platform.deepseek.com/api_keys
- **OpenAI SDK**: https://github.com/openai/openai-python

---

**文档版本**: v1.0
**最后更新**: 2026年3月
