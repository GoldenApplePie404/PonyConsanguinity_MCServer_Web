<?php
if (!defined('ACCESS_ALLOWED')) {
    header('HTTP/1.1 403 Forbidden');
    exit;
}

// 当前密码已做破坏处理，非正式密码（无法正常登录），仅展示用户数据文件结构
// 注意users_.php的小尾巴“_”，正式的用户数据文件是users.php，users_.php仅用于展示数据结构，避免泄露真实密码

return array (
  'ゴールデンアップルパイ' => 
  array (
    'id' => '96584776-0ca0-4cbd-9bd2-e747475e3a09',
    'username' => 'ゴールデンアップルパイ',
    'password' => '$2y$10$iYLiD9XB4IwjBV38lyV9ieyy3qwrqrqwrqwrgMUyA0gb2tzMQzQIbi', 
    'email' => '2928433540@qq.com',
    'email_verified' => true,
    'verify_token' => NULL,
    'verify_expires' => NULL,
    'verify_sent_at' => '2026-06-08 03:19:49',
    'verify_resend_count' => 3,
    'created_at' => '2026-06-08 03:16:38',
    'role' => 'user',
    'login_attempts' => 0,
    'lock_until' => NULL,
    'points' => 30,
    'experience' => 45,
    'check_t' => '2026-07-13',
    'reset_token' => NULL,
    'reset_expires' => NULL,
    'reset_sent_at' => NULL,
  ),
  '金苹果派' => 
  array (
    'id' => '969f7f7f-3f33-403b-9ec7-7527b0f56026',
    'username' => '金苹果派',
    'password' => '$2y$10$ZSKVhUKMaEBT4N5USBnCou3sMSqwtqtwqtqwfqsR5tmFwqrwqrOgT6',     
    'email' => 'czhdqqyx6044@qq.com',
    'email_verified' => true,
    'created_at' => '2026-08-15 10:49:09',
    'role' => 'admin',
    'login_attempts' => 0,
    'lock_until' => NULL,
    'eypa_uid' => 61,
    'eypa_nickname' => '金苹果派',
    'eypa_avatar' => 'https://cdn.eqmemory.cn/uploads/2026/06/20260624192104641-IMG_20260624_192025.png',
    'eypa_bound_at' => '2026-08-15 10:49:09',
    'last_login' => '2026-08-15 02:59:32',
    'verify_token' => NULL,
    'verify_expires' => NULL,
    'verify_sent_at' => '2026-08-15 10:51:31',
    'verify_resend_count' => 1,
    'points' => 10,
    'experience' => 15,
    'check_t' => '2026-08-15',
  ),
);
?>