# 🛡️ 安全测试工具包

## 📁 工具列表

| 文件名 | 类型 | 功能描述 |
|--------|------|----------|
| `scanner.php` | 🔍 综合扫描 | 自动化安全漏洞扫描器 |
| `test_security.php` | 🔍 基础测试 | 基础安全测试脚本 |
| `test_sql_injection.php` | 💉 SQL注入 | SQL注入漏洞测试 |
| `test_xss.php` | 📝 XSS测试 | 跨站脚本攻击测试 |
| `test_brute_force.php` | 🔓 暴力破解 | 暴力破解攻击模拟 |
| `test_privilege.php` | 👤 权限测试 | 权限提升漏洞测试 |
| `migrate_passwords.php` | 🔐 密码迁移 | 明文密码转哈希工具 |
| `security_report.md` | 📊 测试报告 | 详细安全测试报告 |
| `fix_security.md` | 🔧 修复方案 | 漏洞修复指南 |

## 🚀 快速开始

### 1. 运行综合扫描（推荐）
```bash
php safe_test/scanner.php
```

### 2. 单项测试
```bash
# SQL注入测试
php safe_test/test_sql_injection.php

# XSS测试
php safe_test/test_xss.php

# 暴力破解测试
php safe_test/test_brute_force.php

# 权限测试
php safe_test/test_privilege.php
```

### 3. 查看报告
```bash
# 查看安全报告
cat safe_test/security_report.md

# 查看修复方案
cat safe_test/fix_security.md
```

## 🎯 测试覆盖范围

### ✅ 已测试项目
- [x] 密码存储安全
- [x] SQL注入漏洞
- [x] XSS跨站脚本
- [x] 暴力破解防护
- [x] 权限提升漏洞
- [x] 信息泄露
- [x] CSRF防护

### ⚠️ 需手动测试
- [ ] 文件上传漏洞
- [ ] 文件包含漏洞
- [ ] SSRF服务端请求伪造
- [ ] XXE XML外部实体注入
- [ ] 逻辑漏洞

## 📊 当前安全状态

### 发现的漏洞

#### 🔴 严重级别
1. **密码明文存储** - `api/register.php`
2. **无密码哈希验证** - `api/login.php`

#### 🟠 高级别
3. **无登录失败限制** - 可无限尝试登录
4. **无验证码机制** - 易受自动化攻击

#### 🟡 中级别
5. **密码复杂度低** - 仅要求6位
6. **无邮箱验证** - 可用虚假邮箱注册
7. **无CSRF防护** - 可能受跨站请求伪造攻击

### 安全评分

| 指标 | 当前 | 目标 |
|------|------|------|
| 总分 | 35/100 | 80+/100 |
| 等级 | ❌ 危险 | ✅ 良好 |

## 🔧 修复优先级

### 立即修复（1小时内）
1. 使用 `password_hash()` 加密密码
2. 使用 `password_verify()` 验证密码

### 短期修复（1天内）
3. 添加登录失败限制
4. 增强密码复杂度

### 中期修复（1周内）
5. 添加验证码机制
6. 实现邮箱验证
7. 添加CSRF令牌

## 📝 使用示例

### 示例1: 运行综合扫描
```bash
$ php safe_test/scanner.php

╔══════════════════════════════════════════╗
║     安全漏洞扫描器 v1.0                  ║
║     Security Vulnerability Scanner       ║
╚══════════════════════════════════════════╝

【1】密码安全测试
──────────────────────────────────────────
  ❌ 密码明文存储
  ⚠️ 弱密码 '123456' 可注册

【2】认证绕过测试
──────────────────────────────────────────
  ✓ 未发现认证绕过漏洞

...

安全评分: 35/100
安全等级: ❌ 危险
```

### 示例2: 迁移密码
```bash
$ php safe_test/migrate_passwords.php

=== 密码迁移脚本 ===

✓ 迁移 admin
  原密码: admin123
  新哈希: $2y$10$abc123...

迁移完成！
- 已迁移: 3 个用户
- 已跳过: 0 个用户

⚠️ 请立即删除此脚本文件！
```

## ⚠️ 重要提示

1. **仅用于测试** - 这些工具仅用于安全测试目的
2. **本地环境** - 请在本地测试环境运行
3. **备份数据** - 修复前请备份所有数据
4. **删除脚本** - 迁移完成后删除 `migrate_passwords.php`

## 📚 参考资料

- [OWASP Top 10](https://owasp.org/www-project-top-ten/)
- [PHP安全最佳实践](https://www.php.net/manual/zh/security.php)
- [Web安全漏洞防护](https://cheatsheetseries.owasp.org/)

---

*最后更新: <?php echo date('Y-m-d H:i:s'); ?>*
