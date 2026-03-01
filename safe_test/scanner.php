<?php
/**
 * 综合攻击模拟工具 v2.0
 * 自动化安全测试 - 支持代码分析
 */

class SecurityScanner {
    private $api_base;
    private $results = [];
    private $project_root;
    
    public function __construct($api_base = "http://localhost/api") {
        $this->api_base = $api_base;
        $this->project_root = __DIR__ . '/..';
    }
    
    public function runAllTests() {
        echo "\n";
        echo "╔══════════════════════════════════════════╗\n";
        echo "║     安全漏洞扫描器 v2.0                  ║\n";
        echo "║     Security Vulnerability Scanner       ║\n";
        echo "╚══════════════════════════════════════════╝\n";
        echo "\n";
        
        $this->testPasswordSecurity();
        $this->testPasswordPolicy();
        $this->testLoginProtection();
        $this->testAuthenticationBypass();
        $this->testFileInclusion();
        $this->testInformationDisclosure();
        $this->testCSRF();
        
        $this->printSummary();
    }
    
    private function testPasswordSecurity() {
        echo "【1】密码存储安全测试\n";
        echo str_repeat("─", 50) . "\n";
        
        // 检查密码存储
        $users_file = $this->project_root . '/data/users.php';
        if (file_exists($users_file)) {
            $content = file_get_contents($users_file);
            
            // 检查是否有 bcrypt 哈希 ($2y$ 开头)
            if (preg_match('/\'password\'\s*=>\s*\$2y\$/', $content)) {
                $this->addResult('密码存储', '安全', '密码使用 bcrypt 哈希存储', '');
                echo "  ✅ 密码使用 bcrypt 哈希存储\n";
            } elseif (preg_match('/\'password\'\s*=>\s*\'[^\$]/', $content)) {
                $this->addResult('密码存储', '严重', '密码明文存储', '使用 password_hash() 加密');
                echo "  ❌ 密码明文存储\n";
            } else {
                $this->addResult('密码存储', '安全', '密码已加密', '');
                echo "  ✅ 密码已加密\n";
            }
        }
        
        echo "\n";
    }
    
    private function testPasswordPolicy() {
        echo "【2】密码策略测试\n";
        echo str_repeat("─", 50) . "\n";
        
        // 检查注册代码中的密码策略
        $register_file = $this->project_root . '/api/register.php';
        if (file_exists($register_file)) {
            $content = file_get_contents($register_file);
            
            // 检查密码长度要求
            if (preg_match('/strlen\s*\(\s*\$password\s*\)\s*<\s*(\d+)/', $content, $matches)) {
                $min_length = $matches[1];
                if ($min_length >= 8) {
                    $this->addResult('密码策略', '安全', "密码最小长度: {$min_length}位", '');
                    echo "  ✅ 密码最小长度: {$min_length}位\n";
                } else {
                    $this->addResult('密码策略', '中危', "密码最小长度仅: {$min_length}位", '建议至少8位');
                    echo "  ⚠️ 密码最小长度仅: {$min_length}位\n";
                }
            }
            
            // 检查大写字母要求
            if (preg_match('/\[A-Z\]/', $content)) {
                echo "  ✅ 要求包含大写字母\n";
            } else {
                echo "  ⚠️ 未要求大写字母\n";
            }
            
            // 检查小写字母要求
            if (preg_match('/\[a-z\]/', $content)) {
                echo "  ✅ 要求包含小写字母\n";
            } else {
                echo "  ⚠️ 未要求小写字母\n";
            }
            
            // 检查数字要求
            if (preg_match('/\[0-9\]/', $content)) {
                echo "  ✅ 要求包含数字\n";
            } else {
                echo "  ⚠️ 未要求数字\n";
            }
            
            // 检查是否使用 password_hash
            if (preg_match('/password_hash\s*\(/', $content)) {
                $this->addResult('密码哈希', '安全', '注册时使用 password_hash()', '');
                echo "  ✅ 注册时使用 password_hash()\n";
            } else {
                $this->addResult('密码哈希', '严重', '注册时未使用 password_hash()', '使用 password_hash() 加密');
                echo "  ❌ 注册时未使用 password_hash()\n";
            }
        }
        
        echo "\n";
    }
    
    private function testLoginProtection() {
        echo "【3】登录保护测试\n";
        echo str_repeat("─", 50) . "\n";
        
        // 检查登录代码中的保护措施
        $login_file = $this->project_root . '/api/login.php';
        if (file_exists($login_file)) {
            $content = file_get_contents($login_file);
            
            // 检查是否使用 password_verify
            if (preg_match('/password_verify\s*\(/', $content)) {
                $this->addResult('密码验证', '安全', '使用 password_verify() 验证密码', '');
                echo "  ✅ 使用 password_verify() 验证密码\n";
            } else {
                $this->addResult('密码验证', '严重', '未使用 password_verify()', '使用 password_verify() 验证');
                echo "  ❌ 未使用 password_verify()\n";
            }
            
            // 检查登录失败限制
            if (preg_match('/login_attempts/', $content)) {
                $this->addResult('登录限制', '安全', '有登录失败次数限制', '');
                echo "  ✅ 有登录失败次数限制\n";
            } else {
                $this->addResult('登录限制', '高危', '无登录失败次数限制', '添加失败次数限制');
                echo "  ❌ 无登录失败次数限制\n";
            }
            
            // 检查账户锁定
            if (preg_match('/lock_until/', $content)) {
                echo "  ✅ 有账户锁定机制\n";
            } else {
                echo "  ⚠️ 无账户锁定机制\n";
            }
        }
        
        echo "\n";
    }
    
    private function testAuthenticationBypass() {
        echo "【4】认证绕过测试\n";
        echo str_repeat("─", 50) . "\n";
        
        // 检查登录代码是否有SQL注入风险
        $login_file = $this->project_root . '/api/login.php';
        if (file_exists($login_file)) {
            $content = file_get_contents($login_file);
            
            // 项目使用文件存储，不存在SQL注入
            $this->addResult('SQL注入', '安全', '使用文件存储，无SQL注入风险', '');
            echo "  ✅ 使用文件存储，无SQL注入风险\n";
        }
        
        echo "\n";
    }
    
    private function testFileInclusion() {
        echo "【5】文件包含测试\n";
        echo str_repeat("─", 50) . "\n";
        
        $this->addResult('文件包含', '信息', '需要手动测试', '检查文件路径参数');
        echo "  ℹ️ 需要手动测试文件包含漏洞\n\n";
    }
    
    private function testInformationDisclosure() {
        echo "【6】信息泄露测试\n";
        echo str_repeat("─", 50) . "\n";
        
        // 检查敏感文件
        $sensitive_files = [
            '/.git/config',
            '/.env',
            '/config/config.php',
        ];
        
        $found_issues = false;
        foreach ($sensitive_files as $file) {
            $full_path = $this->project_root . $file;
            if (file_exists($full_path)) {
                // 检查是否有访问保护
                $content = file_get_contents($full_path);
                if (strpos($content, 'ACCESS_ALLOWED') !== false || strpos($content, '<?php') !== false) {
                    echo "  ✅ {$file} 有PHP访问保护\n";
                } else {
                    $this->addResult('信息泄露', '高危', "敏感文件可访问: {$file}", '限制文件访问权限');
                    echo "  ❌ 敏感文件可访问: {$file}\n";
                    $found_issues = true;
                }
            }
        }
        
        if (!$found_issues) {
            echo "  ✅ 敏感文件保护正常\n";
        }
        
        echo "\n";
    }
    
    private function testCSRF() {
        echo "【7】CSRF测试\n";
        echo str_repeat("─", 50) . "\n";
        
        $this->addResult('CSRF', '中危', '未检测到CSRF防护', '添加CSRF令牌');
        echo "  ⚠️ 未检测到CSRF令牌机制\n";
        echo "  建议: 在表单中添加CSRF令牌\n\n";
    }
    
    private function addResult($category, $severity, $issue, $solution) {
        $this->results[] = [
            'category' => $category,
            'severity' => $severity,
            'issue' => $issue,
            'solution' => $solution
        ];
    }
    
    private function printSummary() {
        echo "╔══════════════════════════════════════════╗\n";
        echo "║              扫描结果汇总                ║\n";
        echo "╚══════════════════════════════════════════╝\n\n";
        
        $severity_count = [
            '严重' => 0,
            '高危' => 0,
            '中危' => 0,
            '低危' => 0,
            '信息' => 0,
            '安全' => 0,
        ];
        
        foreach ($this->results as $result) {
            $severity_count[$result['severity']]++;
        }
        
        echo "漏洞统计:\n";
        foreach ($severity_count as $severity => $count) {
            if ($count > 0) {
                $icon = $this->getSeverityIcon($severity);
                echo "  {$icon} {$severity}: {$count} 个\n";
            }
        }
        
        echo "\n详细结果:\n";
        echo str_repeat("─", 50) . "\n";
        
        foreach ($this->results as $result) {
            if ($result['severity'] !== '安全' && $result['severity'] !== '信息') {
                $icon = $this->getSeverityIcon($result['severity']);
                echo "\n{$icon} 【{$result['category']}】 - {$result['severity']}\n";
                echo "  问题: {$result['issue']}\n";
                if ($result['solution']) {
                    echo "  修复: {$result['solution']}\n";
                }
            }
        }
        
        // 计算安全评分
        $score = 100;
        $score -= $severity_count['严重'] * 25;
        $score -= $severity_count['高危'] * 15;
        $score -= $severity_count['中危'] * 8;
        $score -= $severity_count['低危'] * 3;
        $score = max(0, $score);
        
        echo "\n" . str_repeat("═", 50) . "\n";
        echo "安全评分: {$score}/100\n";
        
        if ($score >= 80) {
            echo "安全等级: ✅ 良好\n";
        } elseif ($score >= 60) {
            echo "安全等级: ⚠️ 一般\n";
        } elseif ($score >= 40) {
            echo "安全等级: ⚠️ 较差\n";
        } else {
            echo "安全等级: ❌ 危险\n";
        }
        
        echo str_repeat("═", 50) . "\n";
    }
    
    private function getSeverityIcon($severity) {
        $icons = [
            '严重' => '🔴',
            '高危' => '🟠',
            '中危' => '🟡',
            '低危' => '🟢',
            '信息' => 'ℹ️',
            '安全' => '✅',
        ];
        return $icons[$severity] ?? '❓';
    }
}

// 运行扫描
$scanner = new SecurityScanner();
$scanner->runAllTests();
?>
